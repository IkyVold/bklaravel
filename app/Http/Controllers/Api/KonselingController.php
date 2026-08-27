<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesBk;
use App\Models\GuruBk;
use App\Models\Konseling;
use App\Models\Notifikasi;
use App\Models\Siswa;
use App\Services\KonselingReportService;
use App\Services\ScheduleService;
use App\Support\KategoriKonseling;
use App\Support\StatusPenanganan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class KonselingController extends Controller
{
    use AuthorizesBk;

    public function __construct(private ScheduleService $schedule, private KonselingReportService $reports)
    {
    }

    /** Transisi status yang diizinkan */
    // PERBAIKAN (revisi 24 Agustus 2026, poin 5): 'Selesai' SENGAJA dihapus
    // dari daftar transisi 'Proses' di sini. Endpoint updateStatus() ini
    // adalah pengubah status generik tanpa field laporan sama sekali —
    // sebelumnya seseorang bisa PUT status=Selesai langsung ke sini dan
    // melewati seluruh aturan laporan (termasuk wajib sesi lanjutan untuk
    // Monitoring) yang ditegakkan KonselingReportService. Konseling hanya
    // boleh menjadi Selesai lewat endpoint laporan(), tidak pernah lewat
    // updateStatus().
    private const TRANSITIONS = [
        'Menunggu' => ['Proses', 'Dibatalkan', 'Ditolak'],
        'Proses'   => ['Dibatalkan'],
        'Selesai'  => [],
        'Dibatalkan' => [],
        'Ditolak'  => [],
    ];

    /**
     * Nilai status_konfirmasi yang dianggap "sudah dikonfirmasi". Disamakan
     * persis dengan Web/KonselingController@batalGuru — lihat PERBAIKAN
     * (revisi 25 Agustus 2026, poin 5) di updateStatus() di bawah.
     */
    private const CONFIRMED_STATUSES = ['Terkonfirmasi', 'Dikonfirmasi', 'Tervalidasi'];

    public function listBySiswa(Request $request, string $nis): JsonResponse
    {
        // PERBAIKAN (revisi 25 Agustus 2026, poin 1): dulu di sini hanya
        // dipakai assertSiswaOwnsNis(), yang meloloskan SELURUH staff
        // (Guru BK, Kepsek, Admin) tanpa pembatasan lebih lanjut, lalu
        // query di bawahnya mengembalikan SEMUA konseling siswa tsb apa
        // adanya. Akibatnya:
        //  - Guru BK A bisa memanggil endpoint ini dan memperoleh
        //    konsultasi siswa dengan Guru BK B (bukan hanya miliknya).
        //  - Kepsek/Admin memperoleh data lengkap (deskripsi/kesimpulan/dst),
        //    melewati sanitasi yang sudah diterapkan di listAll()/getDetail()
        //    lewat Konseling::untukMonitoringKepsek().
        // Sekarang: siswa tetap pakai ownership NIS; Guru BK hanya
        // mendapat baris miliknya sendiri (guru_id match, dengan fallback
        // nama HANYA untuk data lama guru_id null — konsisten dengan
        // guruOwnsKonseling() di AuthorizesBk); Kepsek & Admin (poin 3)
        // disaring lewat untukMonitoringKepsek() seperti endpoint lain.
        if ($this->isSiswa($request)) {
            $this->assertSiswaOwnsNis($request, $nis);
        } elseif (!$this->isStaff($request)) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak'], 403);
        }

        $siswa = Siswa::where('nis', $nis)->first();
        if (!$siswa) {
            return response()->json(['success' => false, 'message' => 'Siswa tidak ditemukan'], 404);
        }

        $query = Konseling::where('siswa_id', $siswa->id);

        if ($this->isGuru($request)) {
            $user = $request->user();
            $nama = $user->nama ?? '';
            $query->where(function ($q) use ($user, $nama) {
                $q->where('guru_id', $user->id)
                  ->orWhere(function ($qq) use ($nama) {
                      $qq->whereNull('guru_id')
                         ->where('guru_bk', $nama);
                  });
            });
        }

        $rows = $query->orderByDesc('created_at')->get();

        // PERBAIKAN (revisi 25 Agustus 2026, poin 3): Admin disertakan di
        // sini juga (dulu hanya Kepsek) — lihat penjelasan lengkap di
        // getDetail() dan Konseling::untukMonitoringKepsek().
        if ($this->isKepsek($request) || $this->isAdmin($request)) {
            return response()->json([
                'success' => true,
                'data' => $rows->map->untukMonitoringKepsek()->values(),
            ]);
        }

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function listByGuru(Request $request): JsonResponse
    {
        if (!$this->isRole($request, 'guru', 'admin', 'kepsek')) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak'], 403);
        }

        $user = $request->user();
        $q = Konseling::with('siswa:id,nis,nama,kelas')->orderByDesc('created_at');

        if ($this->isGuru($request)) {
            // PERBAIKAN (revisi 25 Agustus 2026, poin 7): dulu di sini
            // masih pakai OR independen (guru_id COCOK ATAU nama COCOK),
            // walau konseling sudah punya guru_id yang menunjuk Guru BK
            // tertentu. Kalau ada dua Guru BK dengan nama sama persis,
            // Guru B bisa memperoleh record milik Guru A lewat daftar ini
            // hanya karena namanya kebetulan sama — padahal authorization
            // individual (guruOwnsKonseling() di AuthorizesBk) dan
            // listBySiswa() (poin 1) sudah diperbaiki memakai pola yang
            // benar. Sekarang disamakan: begitu konseling punya guru_id,
            // itu SATU-SATUNYA sumber kebenaran; fallback nama HANYA
            // dipakai untuk data lama yang guru_id-nya null.
            $nama = $user->nama ?? '';
            $q->where(function ($qq) use ($user, $nama) {
                $qq->where('guru_id', $user->id)
                   ->orWhere(function ($qqq) use ($nama) {
                       $qqq->whereNull('guru_id')
                           ->where('guru_bk', $nama);
                   });
            });
        }

        $rows = $q->get();

        // PERBAIKAN (revisi 26 Agustus 2026, poin 1): endpoint ini dulu
        // hanya memfilter query saat pemanggilnya Guru BK. Kalau yang
        // login Admin/Kepsek, filter di atas dilewati sepenuhnya dan
        // baris ini langsung mengembalikan SELURUH konseling apa adanya
        // — termasuk deskripsi/kesimpulan/rekomendasi/catatan laporan.
        // Ini membuka jalan pintas untuk melewati pembatasan privasi yang
        // sudah diterapkan di /konseling-all, /konseling/{nis}, dan
        // /konseling/detail/{id} (semuanya sudah memakai
        // untukMonitoringKepsek() untuk Admin/Kepsek — lihat listAll()).
        // Endpoint ini sendiri secara desain memang khusus daftar milik
        // Guru BK, jadi Admin/Kepsek yang lolos otorisasi di atas (untuk
        // keperluan monitoring) tetap harus menerima data yang sudah
        // disanitasi dengan pola yang sama persis, bukan isi konsultasi
        // mentah.
        if ($this->isKepsek($request) || $this->isAdmin($request)) {
            return response()->json([
                'success' => true,
                'data' => $rows->map->untukMonitoringKepsek()->values(),
            ]);
        }

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function listAll(Request $request): JsonResponse
    {
        // Hanya kepsek & admin — data sensitif
        if (!$this->isRole($request, 'kepsek', 'admin')) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak. Hanya Kepala Sekolah / Admin.'], 403);
        }

        $rows = Konseling::with('siswa:id,nis,nama,kelas')
            ->orderByDesc('created_at')
            ->get();

        // PERBAIKAN (revisi 24 Agustus 2026 — "Klaim kerahasiaan vs akses
        // Kepala Sekolah"): endpoint ini juga dapat dipanggil Kepsek, dan
        // sebelumnya mengembalikan kolom Konseling APA ADANYA — termasuk
        // deskripsi/kesimpulan/rekomendasi/catatan laporan setiap baris.
        // Sama seperti getDetail() di atas, Kepsek sekarang hanya
        // menerima data administratif (lihat Konseling::untukMonitoringKepsek()).
        //
        // PERBAIKAN (revisi 25 Agustus 2026, poin 3): Admin dulu masih
        // dikecualikan dan menerima seluruh baris apa adanya. Sekarang
        // disanitasi sama persis dengan Kepsek — lihat penjelasan lengkap
        // di getDetail() dan Konseling::untukMonitoringKepsek().
        if ($this->isKepsek($request) || $this->isAdmin($request)) {
            return response()->json([
                'success' => true,
                'data' => $rows->map->untukMonitoringKepsek()->values(),
            ]);
        }

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function getDetail(Request $request, int $id): JsonResponse
    {
        $row = Konseling::with('siswa:id,nis,nama,kelas,foto_profile')->find($id);
        if (!$row) {
            return response()->json(['success' => false, 'message' => 'Tidak ditemukan'], 404);
        }

        $this->assertCanViewKonseling($request, $row);

        // PERBAIKAN (revisi 24 Agustus 2026 — "Klaim kerahasiaan vs akses
        // Kepala Sekolah"): dulu Kepsek menerima $row utuh di sini,
        // termasuk deskripsi masalah/kesimpulan/rekomendasi/catatan
        // laporan — padahal halaman siswa menjanjikan isi konsultasi
        // hanya untuk siswa & Guru BK yang dipilih. Lihat
        // Konseling::untukMonitoringKepsek() untuk daftar field yang
        // aman dilihat (data administratif, bukan isi konsultasi).
        //
        // PERBAIKAN (revisi 25 Agustus 2026, poin 3): Admin dulu masih
        // dikecualikan dari sanitasi ini dan menerima $row utuh — padahal
        // klaim kerahasiaan UI tidak mengecualikan Admin, dan tidak ada
        // alasan proses bisnis bagi Admin (administrator teknis/sistem)
        // untuk membaca substansi kasus konseling. Sekarang Admin
        // disanitasi dengan cara yang sama persis dengan Kepsek. Hanya
        // Guru BK pemilik dan siswa pemilik yang tetap menerima data
        // penuh — merekalah peserta sesi yang sebenarnya.
        if ($this->isKepsek($request) || $this->isAdmin($request)) {
            return response()->json(['success' => true, 'data' => $row->untukMonitoringKepsek()]);
        }

        return response()->json(['success' => true, 'data' => $row]);
    }

    public function store(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'siswa_id' => 'required_without:nis|integer',
            // PERBAIKAN (revisi 26 Agustus 2026, poin 8): diseragamkan
            // dengan aturan NIS di seluruh sistem (tepat 4 digit angka).
            'nis' => 'required_without:siswa_id|digits:4',
            'guru_id' => 'nullable|integer',
            'guru_bk' => 'nullable|string|max:100',
            'tanggal' => 'required|date|after_or_equal:today',
            // PERBAIKAN (revisi 27 Agustus 2026, poin 7): dulu
            // 'string|max:10' menerima string bebas apa pun ("abc",
            // "25:90", "123456") yang lolos ke ScheduleService dan
            // dipakai langsung di strtotime($jam) — input tidak valid
            // bisa menghasilkan perilaku tak terduga sebelum sampai ke
            // kolom TIME di database. date_format:H:i memastikan hanya
            // format jam 24-jam yang valid (00:00-23:59) yang lolos,
            // sesuai format yang memang dikirim semua form (dropdown/
            // input type="time") di aplikasi ini.
            'jam' => 'required|date_format:H:i',
            'jenis' => 'required|in:Luring,Daring',
            'kategori' => ['required', 'string', Rule::in(KategoriKonseling::ALL)],
            'deskripsi' => 'required|string|min:20',
            'tipe_jadwal' => 'nullable|string|in:Rutin,Nonrutin',
            'jadwal_rutin_id' => 'nullable|integer',
            // PERBAIKAN (revisi 24 Agustus 2026, poin 11): opsional — kalau
            // tidak diisi, ScheduleService memakai DEFAULT_DURATION_MINUTES
            // (60 menit) saat cek overlap.
            'durasi_menit' => 'nullable|integer|min:5|max:480',
        ]);

        if ($v->fails()) {
            return response()->json(['success' => false, 'message' => $v->errors()->first()], 400);
        }

        $data = $v->validated();

        // Resolve siswa
        if (!empty($data['siswa_id'])) {
            $siswa = Siswa::find($data['siswa_id']);
        } else {
            $siswa = Siswa::where('nis', $data['nis'])->first();
        }
        if (!$siswa) {
            return response()->json(['success' => false, 'message' => 'Siswa tidak ditemukan'], 404);
        }

        // PERBAIKAN (revisi 25 Agustus 2026, poin 4): route sudah dikunci
        // 'ability:siswa' (lihat routes/api.php), tapi pengecekan eksplisit
        // di sini tetap dipertahankan sebagai lapis kedua — konsisten
        // dengan pola defense-in-depth yang sudah dipakai di codebase ini
        // (mis. assertGuruCanManageKonseling() tetap dipanggil di
        // controller walau route juga sudah dibatasi). assertSiswaOwns()
        // di bawah TIDAK cukup sendirian di sini karena ia memang sengaja
        // dirancang meloloskan seluruh staff (dipakai juga di konteks lain
        // yang butuh staff punya akses lebih luas) — endpoint pengajuan
        // reguler ini harus benar-benar tertutup untuk staff: Guru BK
        // sudah punya jalur sendiri (POST /api/konseling/walkin), dan
        // Kepsek/Admin tidak punya alasan bisnis mengajukan konsultasi
        // atas nama siswa.
        if ($this->isStaff($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Endpoint ini hanya untuk siswa. Guru BK gunakan POST /api/konseling/walkin.',
            ], 403);
        }

        // Siswa hanya boleh ajukan untuk dirinya
        $this->assertSiswaOwns($request, $siswa);

        // Resolve guru
        $guru = null;
        if (!empty($data['guru_id'])) {
            $guru = GuruBk::find($data['guru_id']);
        }
        if (!$guru && !empty($data['guru_bk'])) {
            $guru = GuruBk::where('nama', $data['guru_bk'])->first();
        }
        if (!$guru) {
            return response()->json(['success' => false, 'message' => 'Guru BK tidak ditemukan'], 404);
        }
        if (!($guru->is_active ?? true)) {
            return response()->json(['success' => false, 'message' => 'Guru BK tidak aktif'], 400);
        }

        // Cek konflik jadwal (guru & siswa di waktu yang sama) — satu aturan
        // bersama untuk web & API, lihat ScheduleService. Sekarang berbasis
        // overlap interval [jam, jam+durasi), bukan lagi jam mulai persis
        // sama (revisi 24 Agustus 2026, poin 11).
        //
        // PERBAIKAN (revisi 26 Agustus 2026, poin 8): cek konflik dan
        // Konseling::create() sekarang dibungkus ScheduleService::runLocked()
        // supaya dua pengajuan yang datang hampir bersamaan untuk guru/siswa
        // yang sama tidak lagi bisa sama-sama lolos melihat slot kosong.
        $result = $this->schedule->runLocked($guru->id, $siswa->id, function () use ($siswa, $guru, $data) {
            $conflict = $this->schedule->hasConflict(
                $siswa->id,
                $guru->id,
                $guru->nama,
                $data['tanggal'],
                $data['jam'],
                $data['durasi_menit'] ?? null
            );

            if ($conflict) {
                return null;
            }

            return Konseling::create([
                'siswa_id' => $siswa->id,
                'guru_id' => $guru->id,
                'guru_bk' => $guru->nama,
                'tanggal' => $data['tanggal'],
                'jam' => $data['jam'],
                'durasi_menit' => $data['durasi_menit'] ?? null,
                'jenis' => $data['jenis'],
                'kategori' => $data['kategori'],
                'deskripsi' => $data['deskripsi'],
                'kelas_siswa' => $siswa->kelas,
                'tipe_jadwal' => $data['tipe_jadwal'] ?? 'Nonrutin',
                'jadwal_rutin_id' => $data['jadwal_rutin_id'] ?? null,
                'status' => 'Menunggu',
                'status_konfirmasi' => 'Menunggu',
                'created_at' => now(),
                // UUID untuk chat room — tidak prediktabel
                'chat_session_id' => (string) Str::uuid(),
            ]);
        });

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'Jadwal bentrok. Siswa atau Guru BK sudah memiliki konseling di tanggal/jam tersebut.',
            ], 409);
        }

        return response()->json(['success' => true, 'data' => $result], 201);
    }

    public function walkin(Request $request): JsonResponse
    {
        if (!$this->isRole($request, 'guru', 'admin')) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak'], 403);
        }

        // PERBAIKAN (revisi 24 Agustus 2026, poin 6): dulu 'guru_id' pada
        // record walk-in SELALU diisi $user->id, siapa pun yang memanggil.
        // Kalau yang login Admin, guru_id malah berisi ID Admin — bukan ID
        // Guru BK — sehingga secara model bisnis konseling ini "milik"
        // Admin. Sekarang: kalau pemanggil Guru BK, ia hanya boleh mencatat
        // walk-in atas namanya sendiri (guru_id TIDAK diambil dari input
        // client, selalu dari token). Kalau pemanggil Admin, ia WAJIB
        // memilih guru_id yang valid & aktif — server yang memverifikasi,
        // bukan sekadar dipercaya dari form.
        $rules = [
            'siswa_id' => 'required_without:nis|integer',
            // PERBAIKAN (revisi 26 Agustus 2026, poin 8): diseragamkan
            // dengan aturan NIS di seluruh sistem (tepat 4 digit angka).
            'nis' => 'required_without:siswa_id|digits:4',
            'kategori' => ['required', 'string', Rule::in(KategoriKonseling::ALL)],
            'deskripsi' => 'required|string|min:10',
            'jenis' => 'nullable|in:Luring,Daring',
            'catatan_walkin' => 'nullable|string',
            'durasi_menit' => 'nullable|integer|min:5|max:480',
        ];
        if ($this->isAdmin($request)) {
            $rules['guru_id'] = 'required|integer';
        }

        $v = Validator::make($request->all(), $rules);
        if ($v->fails()) {
            return response()->json(['success' => false, 'message' => $v->errors()->first()], 400);
        }
        $data = $v->validated();

        if (!empty($data['siswa_id'])) {
            $siswa = Siswa::find($data['siswa_id']);
        } else {
            $siswa = Siswa::where('nis', $data['nis'])->first();
        }
        if (!$siswa) {
            return response()->json(['success' => false, 'message' => 'Siswa tidak ditemukan'], 404);
        }

        $user = $request->user();

        if ($this->isAdmin($request)) {
            // Admin mencatat walk-in ATAS NAMA Guru BK yang dipilih — guru_id
            // wajib menunjuk Guru BK yang benar-benar ada & masih aktif.
            $guru = GuruBk::find($data['guru_id']);
            if (!$guru) {
                return response()->json(['success' => false, 'message' => 'Guru BK tidak ditemukan'], 404);
            }
            if (!($guru->is_active ?? true)) {
                return response()->json(['success' => false, 'message' => 'Guru BK tidak aktif'], 400);
            }
        } else {
            // Guru BK mencatat walk-in-nya sendiri. $user di sini adalah
            // record GuruBk itu sendiri (lihat AuthController::loginStaff —
            // token diterbitkan langsung pada model GuruBk), jadi aman
            // dipakai langsung sebagai identitas guru.
            $guru = $user;
        }

        // Cek konflik jadwal — aturan yang sama dipakai di store(), sekarang
        // juga dipakai di sini. Sebelumnya walk-in API langsung
        // Konseling::create() tanpa cek ini, sehingga bentrok bisa terjadi.
        //
        // PERBAIKAN (revisi 26 Agustus 2026, poin 8): dibungkus runLocked()
        // — lihat catatan di store() di atas.
        $result = $this->schedule->runLocked($guru->id, $siswa->id, function () use ($siswa, $guru, $data) {
            $conflict = $this->schedule->hasConflict(
                $siswa->id,
                $guru->id,
                $guru->nama,
                now()->toDateString(),
                now()->format('H:i'),
                $data['durasi_menit'] ?? null
            );
            if ($conflict) {
                return null;
            }

            return Konseling::create([
                'siswa_id' => $siswa->id,
                'guru_id' => $guru->id,
                'guru_bk' => $guru->nama,
                'tanggal' => now()->toDateString(),
                'jam' => now()->format('H:i'),
                'durasi_menit' => $data['durasi_menit'] ?? null,
                'jenis' => $data['jenis'] ?? 'Luring',
                'kategori' => $data['kategori'],
                'deskripsi' => $data['deskripsi'],
                'kelas_siswa' => $siswa->kelas,
                'status' => 'Proses',
                'status_konfirmasi' => 'Dikonfirmasi',
                'input_manual' => true,
                'catatan_walkin' => $data['catatan_walkin'] ?? null,
                'created_at' => now(),
                'chat_session_id' => (string) Str::uuid(),
            ]);
        });

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'Jadwal bentrok. Siswa atau Guru BK sudah memiliki konseling di tanggal/jam tersebut.',
            ], 409);
        }

        return response()->json(['success' => true, 'data' => $result], 201);
    }

    public function konfirmasi(Request $request, int $id): JsonResponse
    {
        $row = Konseling::find($id);
        if (!$row) {
            return response()->json(['success' => false, 'message' => 'Tidak ditemukan'], 404);
        }
        $this->assertGuruCanManageKonseling($request, $row);

        if (!in_array($row->status, ['Menunggu'], true)) {
            return response()->json(['success' => false, 'message' => 'Status tidak memungkinkan konfirmasi'], 400);
        }

        $v = Validator::make($request->all(), [
            'tanggal' => 'nullable|date',
            // PERBAIKAN (revisi 27 Agustus 2026, poin 7): lihat catatan
            // di store() — jam yang lolos di sini juga dipakai
            // strtotime() lewat ScheduleService, jadi harus divalidasi
            // format yang sama.
            'jam' => 'nullable|date_format:H:i',
            'status_konfirmasi' => 'nullable|string|in:Dikonfirmasi,Ditolak',
            'alasan_batal' => 'nullable|string|max:500',
        ]);
        if ($v->fails()) {
            return response()->json(['success' => false, 'message' => $v->errors()->first()], 400);
        }
        $data = $v->validated();

        $konfirmasi = $data['status_konfirmasi'] ?? 'Dikonfirmasi';

        if ($konfirmasi === 'Ditolak') {
            $row->status = 'Ditolak';
            $row->status_konfirmasi = 'Ditolak';
            $row->alasan_batal = $data['alasan_batal'] ?? 'Ditolak oleh Guru BK';
            $row->save();
        } else {
            // Cek konflik jika ubah tanggal/jam — via ScheduleService bersama.
            // PERBAIKAN (revisi 26 Agustus 2026, poin 8): cek konflik +
            // penyimpanan sekarang dibungkus runLocked() supaya konfirmasi
            // yang bersamaan dengan pengajuan/konfirmasi lain untuk guru
            // atau siswa yang sama tidak lagi bisa sama-sama lolos.
            $tgl = $data['tanggal'] ?? $row->tanggal;
            $jam = $data['jam'] ?? $row->jam;

            $ok = $this->schedule->runLocked($row->guru_id, $row->siswa_id, function () use ($row, $tgl, $jam) {
                if ($this->schedule->hasConflictFor($row, $tgl, $jam)) {
                    return false;
                }

                $row->tanggal = $tgl;
                $row->jam = $jam;
                $row->status = 'Proses';
                $row->status_konfirmasi = 'Dikonfirmasi';
                $row->tanggal_konfirmasi = now()->toDateString();
                $row->jam_konfirmasi = now()->format('H:i');
                $row->save();

                return true;
            });

            if (!$ok) {
                return response()->json(['success' => false, 'message' => 'Jadwal bentrok'], 409);
            }
        }

        if ($row->siswa) {
            // PERBAIKAN (revisi 24 Agustus 2026, poin 11): dulu di sini
            // 'data' diisi json_encode(['konseling_id' => ...]) padahal
            // kolom 'data' pada model Notifikasi sudah di-cast 'array'.
            // Mengirim string JSON ke attribute yang sudah di-cast array
            // berisiko double-encoding (accessor mengembalikan string
            // mentah, bukan array, sehingga $notifikasi->data['konseling_id']
            // tidak bekerja). Sekarang pakai Notifikasi::buatUntuk() —
            // helper yang sama dipakai jalur laporan/web — supaya seluruh
            // pemanggil membentuk payload 'data' dengan cara yang sama
            // persis (array asli, bukan string).
            Notifikasi::buatUntuk(
                (string) $row->siswa->nis,
                'siswa',
                $konfirmasi === 'Ditolak' ? 'Pengajuan Ditolak' : 'Jadwal Konseling Dikonfirmasi',
                $konfirmasi === 'Ditolak'
                    ? ('Pengajuan ditolak. Alasan: ' . ($row->alasan_batal ?? '-'))
                    : 'Jadwal konseling Anda telah dikonfirmasi.',
                'konseling',
                $row->id,
            );
        }

        return response()->json(['success' => true, 'message' => 'Konfirmasi berhasil', 'data' => $row]);
    }

    public function laporan(Request $request, int $id): JsonResponse
    {
        $row = Konseling::find($id);
        if (!$row) {
            return response()->json(['success' => false, 'message' => 'Tidak ditemukan'], 404);
        }
        $this->assertGuruCanManageKonseling($request, $row);

        if (!in_array($row->status, ['Proses', 'Selesai'], true)) {
            return response()->json(['success' => false, 'message' => 'Laporan hanya untuk konseling yang sedang/sudah diproses'], 400);
        }

        // PERBAIKAN (revisi 24 Agustus 2026, poin 5): field laporan di sini
        // dulu semuanya nullable dan langsung men-set status = 'Selesai'
        // tanpa aturan "Monitoring wajib sesi lanjutan" — beda dengan jalur
        // Web yang sudah mewajibkannya sejak sebelumnya. Sekarang validasi
        // format tetap di sini (request API), tapi SEMUA business rule
        // (wajib kesimpulan/rekomendasi, window edit, wajib sesi lanjutan
        // untuk Monitoring, transaksi) tunggal di KonselingReportService —
        // jangan duplikasi logika laporan di controller ini.
        $v = Validator::make($request->all(), [
            'laporan_kesimpulan' => 'nullable|string|min:5',
            'laporan_rekomendasi' => 'nullable|string|min:5',
            // PERBAIKAN (revisi 26 Agustus 2026, poin 6): dulu
            // 'nullable|string|max:80' — menerima string bebas apa pun
            // selama request memang mengirimkannya. Sekarang, kalau
            // dikirim, wajib salah satu dari StatusPenanganan::ALL —
            // sama persis dengan aturan di jalur Web.
            'laporan_status_penanganan' => ['nullable', 'string', Rule::in(StatusPenanganan::ALL)],
            'laporan_catatan_tambahan' => 'nullable|string',
            'buat_lanjutan' => 'nullable|boolean',
            'lanjutan_tanggal' => 'nullable|date|after_or_equal:today',
            // PERBAIKAN (revisi 27 Agustus 2026, poin 7): sama seperti
            // 'jam' di atas — nilai ini dipakai KonselingReportService
            // saat membuat sesi lanjutan dan akhirnya sampai ke
            // ScheduleService/strtotime().
            'lanjutan_jam' => 'nullable|date_format:H:i',
            'lanjutan_jenis' => 'nullable|string|in:Luring,Daring',
        ]);
        if ($v->fails()) {
            return response()->json(['success' => false, 'message' => $v->errors()->first()], 400);
        }

        $user = $request->user();

        try {
            $msg = $this->reports->simpan($row, $v->validated(), $user->nama ?? $user->username ?? 'Guru BK');
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => $msg, 'data' => $row->fresh()]);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $row = Konseling::find($id);
        if (!$row) {
            return response()->json(['success' => false, 'message' => 'Tidak ditemukan'], 404);
        }
        $this->assertGuruCanManageKonseling($request, $row);

        $status = $request->input('status');
        $alasan = $request->input('alasan_batal') ?? $request->input('cancel_reason');

        if (!$status) {
            return response()->json(['success' => false, 'message' => 'Status wajib diisi'], 400);
        }

        $current = $row->status ?? 'Menunggu';
        $allowed = self::TRANSITIONS[$current] ?? [];

        if (!in_array($status, $allowed, true)) {
            return response()->json([
                'success' => false,
                'message' => "Transisi status dari '{$current}' ke '{$status}' tidak diizinkan.",
            ], 400);
        }

        if (in_array($status, ['Dibatalkan', 'Ditolak'], true) && empty($alasan)) {
            return response()->json(['success' => false, 'message' => 'Alasan pembatalan/penolakan wajib diisi'], 400);
        }

        // PERBAIKAN (revisi 25 Agustus 2026, poin 5): dulu di sini tidak ada
        // pengecekan status_konfirmasi sama sekali. Jalur web
        // (Web/KonselingController@batalGuru) sudah menolak pembatalan
        // begitu status_konfirmasi masuk kategori "sudah dikonfirmasi", tapi
        // endpoint API generik ini luput — konseling dengan
        // status=Proses & status_konfirmasi=Dikonfirmasi masih bisa diubah
        // jadi Dibatalkan lewat PUT /api/konseling/{id}/status, melewati
        // aturan bisnis yang sama. Sekarang aturannya disamakan: kalau
        // sudah dikonfirmasi, pembatalan harus lewat laporan (menyelesaikan
        // sesi), bukan lewat endpoint status generik ini.
        if ($status === 'Dibatalkan' && in_array($row->status_konfirmasi ?? '', self::CONFIRMED_STATUSES, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Jadwal yang sudah dikonfirmasi tidak dapat dibatalkan. Gunakan laporan untuk menyelesaikan sesi.',
            ], 400);
        }

        $row->status = $status;
        if ($alasan) {
            $row->alasan_batal = $alasan;
        }
        $row->save();

        // Notifikasi
        // PERBAIKAN (revisi 24 Agustus 2026, poin 11): sama seperti pada
        // konfirmasi() di atas — pakai Notifikasi::buatUntuk() alih-alih
        // json_encode() manual ke kolom 'data' yang sudah di-cast array.
        if ($row->siswa && in_array($status, ['Dibatalkan', 'Ditolak'], true)) {
            Notifikasi::buatUntuk(
                (string) $row->siswa->nis,
                'siswa',
                'Konseling Dibatalkan',
                'Konseling dibatalkan. Alasan: ' . ($alasan ?? '-'),
                'konseling',
                $row->id,
            );
        }

        return response()->json(['success' => true, 'message' => 'Status diperbarui', 'data' => $row]);
    }
}
