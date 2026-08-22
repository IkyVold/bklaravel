<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\GuruBk;
use App\Models\JadwalRutin;
use App\Models\Konseling;
use App\Models\Notifikasi;
use App\Models\Siswa;
use App\Services\ScheduleService;
use App\Support\KategoriKonseling;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class KonselingController extends Controller
{
    public function __construct(private ScheduleService $schedule)
    {
    }

    public function indexSiswa()
    {
        $id = Session::get('auth_id');
        $rows = Konseling::where('siswa_id', $id)->orderByDesc('created_at')->get();
        return view('siswa.konseling-index', compact('rows'));
    }

    public function createSiswa(Request $request)
    {
        // is_active: true/1 — jangan ketat boolean agar data lama tetap tampil
        $guruList = GuruBk::where(function ($q) {
            $q->where('is_active', 1)->orWhere('is_active', true);
        })->orderBy('nama')->get();
        if ($guruList->isEmpty()) {
            $guruList = GuruBk::orderBy('nama')->get();
        }

        // Prefer guru_id (stabil), fallback nama — match backend Node
        $guruId = $request->query('guru_id');
        $selectedGuruModel = null;
        if ($guruId !== null && $guruId !== '') {
            $selectedGuruModel = $guruList->firstWhere('id', (int) $guruId)
                ?: GuruBk::find((int) $guruId);
        }
        $selectedGuru = trim((string) $request->query('guru', ''));
        if (!$selectedGuruModel && $selectedGuru !== '') {
            $selectedGuruModel = $guruList->first(function ($g) use ($selectedGuru) {
                return strcasecmp((string) $g->nama, $selectedGuru) === 0;
            });
        }

        if (!$selectedGuruModel) {
            return view('siswa.konseling-pilih', compact('guruList'));
        }

        $selectedGuru = $selectedGuruModel->nama;
        $selectedGuruId = $selectedGuruModel->id;

        // Aman jika tabel jadwal_rutin belum di-migrate
        $slotsRutin = collect();
        try {
            if (Schema::hasTable('jadwal_rutin')) {
                $slotsRutin = JadwalRutin::where('guru_id', $selectedGuruId)
                    ->where('is_active', true)
                    ->orderBy('hari')
                    ->orderBy('jam_mulai')
                    ->get();
            }
        } catch (\Throwable $e) {
            $slotsRutin = collect();
        }

        return view('siswa.konseling-create', compact(
            'guruList', 'selectedGuru', 'selectedGuruId', 'slotsRutin'
        ));
    }

    public function storeSiswa(Request $request)
    {
        $data = $request->validate([
            'guru_id' => 'nullable|integer',
            'guru_bk' => 'required|string|max:100',
            'tipe_jadwal' => 'required|string|in:Rutin,Nonrutin',
            'jadwal_rutin_id' => 'nullable|integer',
            'jenis' => 'required|string|in:Luring,Daring',
            'kategori' => ['required', 'string', Rule::in(KategoriKonseling::ALL)],
            'deskripsi' => 'required|string|min:20',
            'tanggal' => 'required|date|after_or_equal:today',
            'jam' => 'required|string|max:10',
        ], [
            'deskripsi.min' => 'Deskripsi minimal 20 karakter agar Guru BK dapat memahami masalah Anda.',
            'tanggal.after_or_equal' => 'Tanggal konseling tidak boleh sebelum hari ini.',
            'guru_bk.required' => 'Guru BK wajib dipilih.',
        ]);

        // Resolve guru stabil lewat ID (prioritas) lalu nama — match Node backend
        $guru = null;
        if (!empty($data['guru_id'])) {
            $guru = GuruBk::find((int) $data['guru_id']);
        }
        if (!$guru) {
            $guru = GuruBk::where('nama', $data['guru_bk'])->first();
        }
        if (!$guru) {
            return back()->withInput()->withErrors(['guru_bk' => 'Guru BK tidak ditemukan. Pilih ulang dari daftar.']);
        }

        $siswa = Siswa::findOrFail(Session::get('auth_id'));

        $tipe = $data['tipe_jadwal'] ?? 'Nonrutin';
        $jadwalRutinId = null;
        $jenis = $data['jenis'];
        $tanggal = $data['tanggal'];
        $jam = $data['jam'];

        // Jika Rutin: wajib pilih slot & validasi slot milik guru
        if ($tipe === 'Rutin') {
            if (!Schema::hasTable('jadwal_rutin')) {
                return back()->withInput()->withErrors([
                    'tipe_jadwal' => 'Fitur jadwal rutin belum diaktifkan di database. Pilih Nonrutin atau hubungi admin.',
                ]);
            }
            $slotId = (int) ($data['jadwal_rutin_id'] ?? 0);
            $slot = JadwalRutin::where('id', $slotId)
                ->where('guru_id', $guru->id)
                ->where('is_active', true)
                ->first();
            if (!$slot) {
                return back()->withInput()->withErrors([
                    'jadwal_rutin_id' => 'Pilih slot jadwal rutin yang tersedia dari Guru BK ini.',
                ]);
            }
            $jadwalRutinId = $slot->id;
            $jenis = $slot->jenis ?: $jenis;
            $jam = substr((string) $slot->jam_mulai, 0, 5);
            $phpHari = (int) date('N', strtotime($tanggal));
            if ($phpHari !== (int) $slot->hari) {
                $namaHari = JadwalRutin::HARI[$slot->hari] ?? $slot->hari;
                return back()->withInput()->withErrors([
                    'tanggal' => "Tanggal harus jatuh pada hari {$namaHari} sesuai slot rutin yang dipilih.",
                ]);
            }
        }

        // Cek konflik jadwal — satu aturan bersama dengan API (ScheduleService).
        // Guru BK maupun siswa tidak boleh mempunyai dua sesi aktif pada
        // tanggal/jam yang sama.
        if ($this->schedule->hasConflict($siswa->id, $guru->id, $guru->nama, $tanggal, $jam)) {
            return back()->withInput()->withErrors([
                'jam' => 'Jadwal bentrok. Anda atau Guru BK tersebut sudah memiliki konseling di tanggal/jam yang sama.',
            ]);
        }

        // Status awal disamakan dengan API: pengajuan baru selalu Menunggu
        // konfirmasi Guru BK, baru berubah menjadi Proses setelah dikonfirmasi.
        $payload = [
            'siswa_id' => $siswa->id,
            'guru_bk' => $guru->nama,
            'jenis' => $jenis,
            'tipe_jadwal' => $tipe,
            'jadwal_rutin_id' => $jadwalRutinId,
            'kategori' => $data['kategori'],
            'deskripsi' => $data['deskripsi'],
            'tanggal' => $tanggal,
            'jam' => $jam,
            'kelas_siswa' => $siswa->kelas,
            'status' => 'Menunggu',
            'status_konfirmasi' => 'Belum Dikonfirmasi',
            'created_at' => now(),
        ];
        if (Schema::hasColumn('konseling', 'guru_id')) {
            $payload['guru_id'] = $guru->id;
        }
        // Aman jika kolom belum di-migrate
        if (!Schema::hasColumn('konseling', 'tipe_jadwal')) {
            unset($payload['tipe_jadwal'], $payload['jadwal_rutin_id']);
        }

        $row = Konseling::create($payload);

        // Notifikasi ke Guru BK (match notifikasi_guru Node)
        try {
            $username = $guru->username ?? '';
            if ($username) {
                \App\Http\Controllers\Web\NotifikasiWebController::notifyGuru(
                    $username,
                    'Pengajuan Konseling Baru',
                    ($siswa->nama ?? 'Siswa') . ' mengajukan konseling (' . $data['kategori'] . ') pada ' . $data['tanggal'] . ' jam ' . $data['jam'],
                    $row->id,
                    'pengajuan'
                );
            }
        } catch (\Throwable $e) {}

        return redirect()->route('siswa.status', $row->id)
            ->with('success', 'Pengajuan konseling berhasil dikirim kepada ' . $guru->nama . '.');
    }

    /**
     * Halaman Status Konseling (Live Tracking) — match Status.jsx
     */
    public function statusSiswa(int $id)
    {
        $siswaId = Session::get('auth_id');
        $row = Konseling::where('id', $id)->where('siswa_id', $siswaId)->firstOrFail();

        $guru = GuruBk::where('nama', $row->guru_bk)->first()
            ?: (Schema::hasColumn('konseling', 'guru_id') && $row->guru_id
                ? GuruBk::find($row->guru_id)
                : null);

        [$sesiSebelumnya, $sesiLanjutan] = $this->loadSesiRantai($row);

        return view('siswa.status', compact('row', 'guru', 'sesiSebelumnya', 'sesiLanjutan'));
    }

    public function indexGuru(Request $request)
    {
        $auth = Session::get('auth_user', []);
        $nama = $auth['nama'] ?? '';
        $guruId = Session::get('auth_id'); // id guru di session
        $filter = $request->query('filter', 'all');
        $q = trim((string) $request->query('q', ''));

        // Match Node listByGuru: prefer guru_id, fallback nama (data lama)
        $base = Konseling::with('siswa:id,nis,nama,kelas')
            ->where(function ($w) use ($guruId, $nama) {
                if (Schema::hasColumn('konseling', 'guru_id') && $guruId) {
                    $w->where('guru_id', $guruId)
                        ->orWhere(function ($w2) use ($nama) {
                            $w2->whereNull('guru_id')->where('guru_bk', $nama);
                        });
                } else {
                    $w->where('guru_bk', $nama);
                }
            });

        // "Menunggu Konfirmasi" = pengajuan baru berstatus Menunggu (belum
        // diproses Guru BK sama sekali). Setelah dikonfirmasi status
        // berubah menjadi Proses — lihat konfirmasi().
        $prosesCount = (clone $base)->where('status', 'Menunggu')->count();

        $stats = [
            'all' => (clone $base)->count(),
            'proses' => $prosesCount,
            'terkonfirmasi' => (clone $base)->whereIn('status_konfirmasi', ['Terkonfirmasi', 'Dikonfirmasi', 'Tervalidasi'])->count(),
            'selesai' => (clone $base)->where('status', 'Selesai')->count(),
            'dibatalkan' => (clone $base)->where('status', 'Dibatalkan')->count(),
        ];

        $query = clone $base;
        if ($filter === 'proses') {
            $query->where('status', 'Menunggu');
        } elseif ($filter === 'terkonfirmasi') {
            $query->whereIn('status_konfirmasi', ['Terkonfirmasi', 'Dikonfirmasi', 'Tervalidasi'])
                ->where('status', '!=', 'Dibatalkan');
        } elseif ($filter === 'selesai') {
            $query->where('status', 'Selesai');
        } elseif ($filter === 'dibatalkan') {
            $query->where('status', 'Dibatalkan');
        }

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('deskripsi', 'like', "%{$q}%")
                    ->orWhere('kategori', 'like', "%{$q}%")
                    ->orWhere('jenis', 'like', "%{$q}%")
                    ->orWhereHas('siswa', function ($s) use ($q) {
                        $s->where('nama', 'like', "%{$q}%")
                            ->orWhere('nis', 'like', "%{$q}%")
                            ->orWhere('kelas', 'like', "%{$q}%");
                    });
            });
        }

        $rows = $query->orderByDesc('id')->get();
        $activeTab = 'konseling';
        $currentFilter = $filter;

        return view('guru.konseling-index', compact(
            'rows', 'stats', 'prosesCount', 'activeTab', 'currentFilter', 'q'
        ));
    }


    /**
     * Nav "Status" tanpa id — ke pengajuan terbaru, atau History jika kosong
     */
    public function statusIndex()
    {
        $siswaId = Session::get('auth_id');
        $latest = Konseling::where('siswa_id', $siswaId)->orderByDesc('id')->first();
        if (!$latest) {
            return redirect()->route('siswa.konseling.index')
                ->with('error', 'Belum ada pengajuan konseling. Ajukan terlebih dahulu.');
        }
        return redirect()->route('siswa.status', $latest->id);
    }

    public function show(int $id)
    {
        $role = Session::get('auth_role');

        // Guru BK: ambil scoped ke miliknya sendiri lewat findGuruKonseling().
        // Jangan pakai findOrFail generik di sini — itu yang dulu membuat
        // Guru A bisa melihat kasus Guru B hanya dengan mengganti ID di URL.
        if ($role === 'guru') {
            $row = $this->findGuruKonseling($id);
            return view('guru.konseling-detail', compact('row'));
        }

        $row = Konseling::with('siswa')->findOrFail($id);

        // siswa hanya boleh lihat milik sendiri
        if ($role === 'siswa') {
            if ($row->siswa_id != Session::get('auth_id')) {
                abort(403);
            }
            $guru = GuruBk::where('nama', $row->guru_bk)->first()
                ?: (Schema::hasColumn('konseling', 'guru_id') && $row->guru_id
                    ? GuruBk::find($row->guru_id)
                    : null);
            [$sesiSebelumnya, $sesiLanjutan] = $this->loadSesiRantai($row);
            return view('siswa.history-detail', compact('row', 'guru', 'sesiSebelumnya', 'sesiLanjutan'));
        }

        return view('shared.konseling-show', compact('row', 'role'));
    }

    /**
     * Satu-satunya jalur pembatalan pengajuan siswa (soft cancel — status →
     * Dibatalkan). Menggantikan destroySiswa() yang dulu hard-delete;
     * "Konsul Ulang" di halaman status sekarang memakai route yang sama ini.
     */
    public function batalSiswa(Request $request, int $id)
    {
        $siswaId = Session::get('auth_id');
        $row = Konseling::where('id', $id)->where('siswa_id', $siswaId)->firstOrFail();

        if (!in_array($row->status ?? '', ['Menunggu', 'Proses'], true)) {
            return redirect()->route('siswa.konseling.show', $row->id)
                ->with('error', 'Hanya pengajuan berstatus Menunggu atau Proses yang dapat dibatalkan.');
        }

        $data = $request->validate([
            'alasan' => 'required|string|min:10|max:1000',
        ], [
            'alasan.required' => 'Alasan pembatalan wajib diisi.',
            'alasan.min' => 'Alasan pembatalan minimal 10 karakter.',
        ]);

        $auth = Session::get('auth_user', []);
        $row->status = 'Dibatalkan';
        if (Schema::hasColumn('konseling', 'alasan_batal')) {
            $row->alasan_batal = $data['alasan'];
        }
        if (Schema::hasColumn('konseling', 'dibatalkan_oleh')) {
            $row->dibatalkan_oleh = $auth['nama'] ?? 'Siswa';
        }
        if (Schema::hasColumn('konseling', 'waktu_batal')) {
            $row->waktu_batal = now();
        }
        $row->save();

        // Konsul ulang: siswa langsung diarahkan ke form pengajuan baru
        if ($request->boolean('ajukan_ulang')) {
            return redirect()->route('siswa.konseling.create')
                ->with('success', 'Pengajuan dibatalkan. Silakan pilih Guru BK untuk mengajukan ulang.');
        }

        return redirect()->route('siswa.konseling.show', $row->id)
            ->with('success', 'Pengajuan konseling berhasil dibatalkan.');
    }

    public function konfirmasi(Request $request, int $id)
    {
        $row = $this->findGuruKonseling($id);

        // Hanya pengajuan yang masih Menunggu yang boleh dikonfirmasi —
        // mencegah record Dibatalkan/Selesai diproses ulang lewat form ini.
        if (($row->status ?? '') !== 'Menunggu') {
            return back()->with('error', 'Hanya pengajuan berstatus Menunggu yang dapat dikonfirmasi.');
        }

        $data = $request->validate([
            'tanggal_konfirmasi' => 'required|date',
            'jam_konfirmasi' => 'required|string|max:10',
            'status_konfirmasi' => 'nullable|string|max:30',
        ], [
            'tanggal_konfirmasi.required' => 'Tanggal konfirmasi wajib diisi.',
            'jam_konfirmasi.required' => 'Jam konfirmasi wajib diisi.',
        ]);

        $konfirmasi = $data['status_konfirmasi'] ?? 'Terkonfirmasi';
        if (in_array($konfirmasi, ['Dikonfirmasi', 'Tervalidasi'], true)) {
            $konfirmasi = 'Terkonfirmasi';
        }

        if ($konfirmasi === 'Terkonfirmasi') {
            // Cek konflik jadwal — sama seperti API, memakai tanggal/jam
            // baru yang dipilih Guru BK saat konfirmasi.
            if ($this->schedule->hasConflictFor($row, $data['tanggal_konfirmasi'], $data['jam_konfirmasi'])) {
                return back()->withInput()->with('error', 'Jadwal bentrok. Guru BK atau siswa sudah memiliki konseling lain di tanggal/jam tersebut.');
            }
        }

        $row->status_konfirmasi = $konfirmasi;
        $row->tanggal_konfirmasi = $data['tanggal_konfirmasi'];
        $row->jam_konfirmasi = $data['jam_konfirmasi'];
        // Juga update jadwal utama agar siswa melihat jadwal yang dikonfirmasi
        $row->tanggal = $data['tanggal_konfirmasi'];
        $row->jam = $data['jam_konfirmasi'];
        // Transisi state: Menunggu -> Proses, disamakan dengan API.
        $row->status = 'Proses';
        $row->save();

        if ($row->siswa) {
            // Skema tunggal (penerima_id/penerima_role/dibaca/data), sama
            // dengan Api\KonselingController — bukan lagi siswa_id/is_read.
            try {
                Notifikasi::buatUntuk(
                    (string) $row->siswa->nis,
                    'siswa',
                    'Jadwal Konseling Dikonfirmasi',
                    'Pengajuan konseling Anda dikonfirmasi: ' . ($data['status_konfirmasi'] ?? ''),
                    'konseling',
                    $row->id,
                );
            } catch (\Throwable $e) {
                // jangan gagalkan konfirmasi hanya karena notif
            }
        }

        return back()->with('success', 'Konfirmasi disimpan.');
    }

    public function laporan(Request $request, int $id)
    {
        $row = $this->findGuruKonseling($id);

        // Harus sudah dikonfirmasi & masih Proses (atau edit laporan Selesai dalam window)
        $sk = $row->status_konfirmasi ?? '';
        $isConfirmed = in_array($sk, ['Terkonfirmasi', 'Dikonfirmasi', 'Tervalidasi'], true);
        $hasLaporan = !empty($row->laporan_created_at) || !empty($row->laporan_kesimpulan);

        $data = $request->validate([
            'laporan_kesimpulan' => 'required|string|min:5',
            'laporan_rekomendasi' => 'required|string|min:5',
            'laporan_status_penanganan' => 'required|string|max:80',
            'laporan_catatan_tambahan' => 'nullable|string',
            // Sesi lanjutan (jika Monitoring)
            'buat_lanjutan' => 'nullable|boolean',
            'lanjutan_tanggal' => 'nullable|date|after_or_equal:today',
            'lanjutan_jam' => 'nullable|string|max:10',
            'lanjutan_jenis' => 'nullable|string|in:Luring,Daring',
        ], [
            'laporan_kesimpulan.required' => 'Kesimpulan konseling wajib diisi.',
            'laporan_rekomendasi.required' => 'Rekomendasi / tindak lanjut wajib diisi.',
        ]);

        $windowHours = 72;
        $user = Session::get('auth_user', []);

        // --- Validasi dilakukan SEBELUM ada perubahan apa pun ke database ---

        if ($hasLaporan && $row->laporan_created_at) {
            $created = \Carbon\Carbon::parse($row->laporan_created_at);
            $jamBerlalu = $created->diffInMinutes(now()) / 60;
            if ($jamBerlalu > $windowHours) {
                return back()->with('error', "Laporan terkunci. Batas edit {$windowHours} jam setelah pertama disimpan sudah lewat.");
            }
        }

        if (!$hasLaporan && (!$isConfirmed || ($row->status ?? '') === 'Dibatalkan')) {
            return back()->with('error', 'Laporan hanya untuk sesi yang sudah dikonfirmasi dan belum dibatalkan.');
        }

        // Sesi lanjutan wajib tanggal & jam kalau status penanganan Monitoring —
        // dicek DI SINI, sebelum apa pun disimpan. Dulu ini dicek setelah
        // status sudah terlanjur jadi Selesai, sehingga error muncul padahal
        // data sudah tersimpan.
        $buatLanjutan = $request->boolean('buat_lanjutan')
            || ($data['laporan_status_penanganan'] === 'Monitoring');
        $lanjutanLengkap = !empty($data['lanjutan_tanggal']) && !empty($data['lanjutan_jam']);

        if ($data['laporan_status_penanganan'] === 'Monitoring' && !$hasLaporan && !$lanjutanLengkap) {
            return back()->with('error', 'Status Monitoring: isi tanggal & jam sesi lanjutan.');
        }

        // --- Semua valid. Simpan laporan, ubah status, buat sesi lanjutan,
        // dan notifikasi dalam satu transaksi — gagal satu, rollback semua. ---

        try {
            $msg = DB::transaction(function () use ($row, $data, $hasLaporan, $user, $buatLanjutan, $lanjutanLengkap) {
            if ($hasLaporan) {
                $row->laporan_kesimpulan = $data['laporan_kesimpulan'];
                $row->laporan_rekomendasi = $data['laporan_rekomendasi'];
                $row->laporan_status_penanganan = $data['laporan_status_penanganan'];
                $row->laporan_catatan_tambahan = $data['laporan_catatan_tambahan'] ?? '-';
                $row->save();
                $msg = 'Laporan berhasil diperbarui.';
            } else {
                $row->laporan_kesimpulan = $data['laporan_kesimpulan'];
                $row->laporan_rekomendasi = $data['laporan_rekomendasi'];
                $row->laporan_status_penanganan = $data['laporan_status_penanganan'];
                $row->laporan_catatan_tambahan = $data['laporan_catatan_tambahan'] ?? '-';
                $row->laporan_tanggal = now()->toDateString();
                $row->laporan_waktu = now()->format('H:i:s');
                $row->laporan_dibuat_oleh = $user['nama'] ?? 'Guru BK';
                $row->laporan_created_at = now();
                $row->status = 'Selesai';
                $row->save();
                $msg = 'Laporan disimpan & konseling diselesaikan.';
            }

            if ($buatLanjutan && $lanjutanLengkap && !$hasLaporan) {
                $this->createSesiLanjutan($row, $data);
                $msg .= ' Sesi lanjutan telah dibuat.';
            }

                return $msg;
            });
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('guru.konseling.show', $row->id)->with('success', $msg);
    }

    /**
     * Buat sesi lanjutan dari parent yang sudah Selesai (match createLanjutan Node).
     */
    protected function createSesiLanjutan(Konseling $parent, array $data): Konseling
    {
        $user = Session::get('auth_user', []);
        $guruId = Session::get('auth_id');
        $deskripsi = 'Sesi lanjutan dari konseling #' . $parent->id . '. ' . ($data['laporan_rekomendasi'] ?? '');
        $deskripsi = mb_substr(trim($deskripsi), 0, 500);
        if (mb_strlen($deskripsi) < 20) {
            $deskripsi = str_pad($deskripsi, 20, '.');
        }

        // Sesi lanjutan juga harus lolos cek bentrok — Guru BK yang sama
        // bisa saja sudah menjadwalkan sesi lain di tanggal/jam tersebut.
        // Dilempar sebagai exception biasa supaya DB::transaction() di
        // laporan() otomatis rollback; ditangkap & ditampilkan di sana.
        if ($this->schedule->hasConflict(
            $parent->siswa_id,
            $guruId,
            $user['nama'] ?? $parent->guru_bk,
            $data['lanjutan_tanggal'],
            $data['lanjutan_jam']
        )) {
            throw new \RuntimeException('Sesi lanjutan gagal dibuat: jadwal bentrok dengan konseling lain pada tanggal/jam tersebut.');
        }

        $payload = [
            'siswa_id' => $parent->siswa_id,
            'guru_bk' => $user['nama'] ?? $parent->guru_bk,
            'jenis' => $data['lanjutan_jenis'] ?? ($parent->jenis === 'Daring' ? 'Daring' : 'Luring'),
            'kategori' => $parent->kategori ?? 'Lainnya',
            'deskripsi' => $deskripsi,
            'tanggal' => $data['lanjutan_tanggal'],
            'jam' => $data['lanjutan_jam'],
            'kelas_siswa' => $parent->kelas_siswa,
            'status' => 'Proses',
            'status_konfirmasi' => 'Terkonfirmasi', // jadwal sudah ditentukan guru
            'tanggal_konfirmasi' => $data['lanjutan_tanggal'],
            'jam_konfirmasi' => $data['lanjutan_jam'],
            'created_at' => now(),
        ];
        if (Schema::hasColumn('konseling', 'guru_id') && $guruId) {
            $payload['guru_id'] = $guruId;
        }
        if (Schema::hasColumn('konseling', 'pengajuan_sebelumnya_id')) {
            $payload['pengajuan_sebelumnya_id'] = $parent->id;
        }

        $child = Konseling::create($payload);

        // Notifikasi siswa jika memungkinkan — skema tunggal, sama dengan
        // Api\KonselingController (penerima_id/penerima_role/dibaca/data).
        try {
            $nis = $parent->siswa->nis ?? null;
            if ($nis) {
                Notifikasi::buatUntuk(
                    (string) $nis,
                    'siswa',
                    'Sesi Konseling Lanjutan',
                    'Guru BK menjadwalkan sesi lanjutan pada ' . $data['lanjutan_tanggal'] . ' jam ' . $data['lanjutan_jam'],
                    'konseling',
                    $child->id,
                );
            }
        } catch (\Throwable $e) {}

        return $child;
    }


    public function walkinForm()
    {
        return redirect()->route('guru.konseling.index', ['open' => 'walkin']);
    }

    public function walkinStore(Request $request)
    {
        $data = $request->validate([
            'nis' => 'required|string|max:20',
            'tanggal' => 'required|date',
            'jam' => 'required|string|max:10',
            'jenis' => 'required|string|max:30',
            'kategori' => ['required', 'string', Rule::in(KategoriKonseling::ALL)],
            'deskripsi' => 'required|string|min:10',
            'catatan_walkin' => 'nullable|string',
            'langsung_laporan' => 'nullable|boolean',
        ]);

        $siswa = Siswa::where('nis', $data['nis'])->first();
        if (!$siswa) {
            return back()->withInput()->withErrors(['nis' => 'Siswa dengan NIS tersebut belum terdaftar.']);
        }

        $auth = Session::get('auth_user', []);
        $namaGuru = $auth['nama'] ?? 'Guru BK';
        $guruId = Session::get('auth_id');

        // Tatap Muka → Luring (internal), Daring tetap
        $jenis = $data['jenis'];
        if ($jenis === 'Tatap Muka' || $jenis === 'Walk-in') {
            $jenis = 'Luring';
        }

        // Walk-in langsung terkonfirmasi (guru & siswa bertemu langsung),
        // tapi tetap harus dicek supaya tidak bentrok dengan sesi lain yang
        // sudah lebih dulu terjadwal pada slot yang sama.
        if ($this->schedule->hasConflict($siswa->id, $guruId, $namaGuru, $data['tanggal'], $data['jam'])) {
            return back()->withInput()->withErrors([
                'jam' => 'Jadwal bentrok. Siswa atau Guru BK sudah memiliki konseling lain di tanggal/jam tersebut.',
            ]);
        }

        $payload = [
            'siswa_id' => $siswa->id,
            'guru_bk' => $namaGuru,
            'jenis' => $jenis,
            'kategori' => $data['kategori'],
            'deskripsi' => $data['deskripsi'],
            'tanggal' => $data['tanggal'],
            'jam' => $data['jam'],
            'kelas_siswa' => $siswa->kelas,
            'status' => 'Proses',
            'status_konfirmasi' => 'Terkonfirmasi',
            'tanggal_konfirmasi' => $data['tanggal'],
            'jam_konfirmasi' => $data['jam'],
            'tipe_jadwal' => 'Nonrutin',
            'input_manual' => true,
            'created_at' => now(),
        ];
        if (Schema::hasColumn('konseling', 'guru_id') && $guruId) {
            $payload['guru_id'] = $guruId;
        }
        if (Schema::hasColumn('konseling', 'catatan_walkin')) {
            $payload['catatan_walkin'] = $data['catatan_walkin'] ?? null;
        }

        $row = Konseling::create($payload);

        if ($request->boolean('langsung_laporan')) {
            return redirect()->route('guru.konseling.show', $row->id)->with('success', 'Walk-in dicatat. Lanjutkan isi laporan.');
        }

        return redirect()->route('guru.konseling.index')->with('success', 'Data konseling walk-in berhasil disimpan.');
    }


    public function batalGuru(Request $request, int $id)
    {
        $row = $this->findGuruKonseling($id);
        $data = $request->validate(['alasan' => 'nullable|string|max:1000']);

        if (!in_array($row->status ?? '', ['Menunggu', 'Proses'], true)) {
            return back()->with('error', 'Hanya pengajuan berstatus Menunggu atau Proses yang dapat dibatalkan.');
        }
        $sk = $row->status_konfirmasi ?? '';
        // Setelah dikonfirmasi TIDAK bisa dibatalkan (sesuai permintaan bisnis)
        if (in_array($sk, ['Terkonfirmasi', 'Dikonfirmasi', 'Tervalidasi'], true)) {
            return back()->with('error', 'Jadwal yang sudah dikonfirmasi tidak dapat dibatalkan. Gunakan laporan untuk menyelesaikan sesi.');
        }

        $row->status = 'Dibatalkan';
        if (Schema::hasColumn('konseling', 'alasan_batal')) {
            $row->alasan_batal = $data['alasan'] ?? 'Dibatalkan oleh Guru BK';
        }
        $row->save();
        return redirect()->route('guru.konseling.index')->with('success', 'Pengajuan dibatalkan.');
    }



    /**
     * Parent + children sesi — match Node getDetail / findParentBrief / findChildrenByParentId
     */
    protected function loadSesiRantai(Konseling $row): array
    {
        $sesiSebelumnya = null;
        $sesiLanjutan = collect();

        if (!Schema::hasColumn('konseling', 'pengajuan_sebelumnya_id')) {
            return [$sesiSebelumnya, $sesiLanjutan];
        }

        if ($row->pengajuan_sebelumnya_id) {
            $sesiSebelumnya = Konseling::select(
                'id', 'status', 'status_konfirmasi', 'tanggal', 'jam', 'kategori', 'jenis',
                'laporan_status_penanganan', 'pengajuan_sebelumnya_id'
            )->find($row->pengajuan_sebelumnya_id);
        }

        $sesiLanjutan = Konseling::select(
            'id', 'status', 'status_konfirmasi', 'tanggal', 'jam', 'kategori', 'jenis'
        )->where('pengajuan_sebelumnya_id', $row->id)->orderBy('id')->get();

        return [$sesiSebelumnya, $sesiLanjutan];
    }

    protected function findGuruKonseling(int $id): Konseling
    {
        $auth = Session::get('auth_user', []);
        $nama = $auth['nama'] ?? '';
        $guruId = Session::get('auth_id');

        $q = Konseling::with('siswa')->where('id', $id);
        $q->where(function ($w) use ($guruId, $nama) {
            if (Schema::hasColumn('konseling', 'guru_id') && $guruId) {
                $w->where('guru_id', $guruId)
                    ->orWhere(function ($w2) use ($nama) {
                        $w2->whereNull('guru_id')->where('guru_bk', $nama);
                    });
            } else {
                $w->where('guru_bk', $nama);
            }
        });

        return $q->firstOrFail();
    }

    public function indexAll()
    {
        $rows = Konseling::with('siswa:id,nis,nama,kelas')
            ->orderByDesc('created_at')
            ->paginate(20);
        return view('kepsek.konseling-index', compact('rows'));
    }
}
