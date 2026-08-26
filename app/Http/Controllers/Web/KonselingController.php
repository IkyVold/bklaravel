<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\GuruBk;
use App\Models\JadwalRutin;
use App\Models\Konseling;
use App\Models\Notifikasi;
use App\Models\Siswa;
use App\Services\KonselingReportService;
use App\Services\ScheduleService;
use App\Support\KategoriKonseling;
use App\Support\StatusPenanganan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class KonselingController extends Controller
{
    public function __construct(private ScheduleService $schedule, private KonselingReportService $reports)
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
            'durasi_menit' => 'nullable|integer|min:5|max:480',
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

        if (!($guru->is_active ?? true)) {
            return back()->withInput()->withErrors(['guru_bk' => 'Guru BK tidak aktif. Pilih Guru BK lain dari daftar.']);
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

        $row = $this->schedule->runLocked($guru->id, $siswa->id, function () use ($siswa, $guru, $tanggal, $jam, $jenis, $tipe, $jadwalRutinId, $data) {
            if ($this->schedule->hasConflict($siswa->id, $guru->id, $guru->nama, $tanggal, $jam, $data['durasi_menit'] ?? null)) {
                return null;
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
            if (Schema::hasColumn('konseling', 'durasi_menit')) {
                $payload['durasi_menit'] = $data['durasi_menit'] ?? null;
            }
            // Aman jika kolom belum di-migrate
            if (!Schema::hasColumn('konseling', 'tipe_jadwal')) {
                unset($payload['tipe_jadwal'], $payload['jadwal_rutin_id']);
            }

            return Konseling::create($payload);
        });

        if (!$row) {
            return back()->withInput()->withErrors([
                'jam' => 'Jadwal bentrok. Anda atau Guru BK tersebut sudah memiliki konseling di tanggal/jam yang sama.',
            ]);
        }

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
            'status_konfirmasi' => ['nullable', Rule::in(['Terkonfirmasi'])],
        ], [
            'tanggal_konfirmasi.required' => 'Tanggal konfirmasi wajib diisi.',
            'jam_konfirmasi.required' => 'Jam konfirmasi wajib diisi.',
            'status_konfirmasi.in' => 'Nilai status konfirmasi tidak valid.',
        ]);

        // Field ini sekarang hanya bisa null (default) atau 'Terkonfirmasi'
        // (lolos Rule::in di atas) — normalisasi Dikonfirmasi/Tervalidasi
        // yang lama tidak diperlukan lagi karena nilai lain sudah ditolak
        // Validator sebelum sampai ke sini.
        $konfirmasi = 'Terkonfirmasi';

        $ok = $this->schedule->runLocked($row->guru_id, $row->siswa_id, function () use ($row, $data, $konfirmasi) {
            if ($this->schedule->hasConflictFor($row, $data['tanggal_konfirmasi'], $data['jam_konfirmasi'])) {
                return false;
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

            return true;
        });

        if (!$ok) {
            return back()->withInput()->with('error', 'Jadwal bentrok. Guru BK atau siswa sudah memiliki konseling lain di tanggal/jam tersebut.');
        }

        if ($row->siswa) {
            // Skema tunggal (penerima_id/penerima_role/dibaca/data), sama
            // dengan Api\KonselingController — bukan lagi siswa_id/is_read.
            try {
                Notifikasi::buatUntuk(
                    (string) $row->siswa->nis,
                    'siswa',
                    'Jadwal Konseling Dikonfirmasi',
                    'Pengajuan konseling Anda dikonfirmasi: ' . $konfirmasi,
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

        $data = $request->validate([
            'laporan_kesimpulan' => 'required|string|min:5',
            'laporan_rekomendasi' => 'required|string|min:5',
            'laporan_status_penanganan' => ['required', 'string', Rule::in(StatusPenanganan::ALL)],
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

        $user = Session::get('auth_user', []);

        try {
            $msg = $this->reports->simpan($row, $data, $user['nama'] ?? 'Guru BK');
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('guru.konseling.show', $row->id)->with('success', $msg);
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
            'durasi_menit' => 'nullable|integer|min:5|max:480',
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

        $row = $this->schedule->runLocked($guruId ? (int) $guruId : null, $siswa->id, function () use ($siswa, $guruId, $namaGuru, $jenis, $data) {
            if ($this->schedule->hasConflict($siswa->id, $guruId, $namaGuru, $data['tanggal'], $data['jam'], $data['durasi_menit'] ?? null)) {
                return null;
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
            if (Schema::hasColumn('konseling', 'durasi_menit')) {
                $payload['durasi_menit'] = $data['durasi_menit'] ?? null;
            }

            return Konseling::create($payload);
        });

        if (!$row) {
            return back()->withInput()->withErrors([
                'jam' => 'Jadwal bentrok. Siswa atau Guru BK sudah memiliki konseling lain di tanggal/jam tersebut.',
            ]);
        }

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
