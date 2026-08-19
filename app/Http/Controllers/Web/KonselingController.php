<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\GuruBk;
use App\Models\JadwalRutin;
use App\Models\Konseling;
use App\Models\Notifikasi;
use App\Models\Siswa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Schema;

class KonselingController extends Controller
{
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
            'kategori' => 'required|string|max:50',
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
            'status' => 'Proses',
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

    /**
     * Batalkan pengajuan & ajukan ulang (Konsul Ulang) — match Status.jsx handleKonsulUlang
     */
    public function destroySiswa(int $id)
    {
        $siswaId = Session::get('auth_id');
        $row = Konseling::where('id', $id)->where('siswa_id', $siswaId)->firstOrFail();

        // Hanya boleh batalkan jika belum selesai
        if (($row->status ?? '') === 'Selesai') {
            return redirect()->route('siswa.status', $row->id)
                ->with('error', 'Konseling yang sudah selesai tidak dapat dibatalkan.');
        }

        $row->delete();

        return redirect()->route('siswa.konseling.create')
            ->with('success', 'Pengajuan dibatalkan. Silakan pilih Guru BK untuk mengajukan ulang.');
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

        $prosesCount = (clone $base)->where('status', 'Proses')
            ->where(function ($w) {
                $w->whereNull('status_konfirmasi')
                    ->orWhere('status_konfirmasi', 'Belum Dikonfirmasi')
                    ->orWhere('status_konfirmasi', '');
            })->count();

        $stats = [
            'all' => (clone $base)->count(),
            'proses' => $prosesCount,
            'terkonfirmasi' => (clone $base)->whereIn('status_konfirmasi', ['Terkonfirmasi', 'Dikonfirmasi', 'Tervalidasi'])->count(),
            'selesai' => (clone $base)->where('status', 'Selesai')->count(),
            'dibatalkan' => (clone $base)->where('status', 'Dibatalkan')->count(),
        ];

        $query = clone $base;
        if ($filter === 'proses') {
            $query->where('status', 'Proses')
                ->where(function ($w) {
                    $w->whereNull('status_konfirmasi')
                        ->orWhere('status_konfirmasi', 'Belum Dikonfirmasi')
                        ->orWhere('status_konfirmasi', '');
                });
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
        $row = Konseling::with('siswa')->findOrFail($id);
        $role = Session::get('auth_role');

        // siswa hanya boleh lihat milik sendiri
        if ($role === 'siswa' && $row->siswa_id != Session::get('auth_id')) {
            abort(403);
        }

        // Match React DetailHistory + getDetail (sesi_sebelumnya / sesi_lanjutan)
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

        if ($role === 'guru') {
            return view('guru.konseling-detail', compact('row'));
        }
        return view('shared.konseling-show', compact('row', 'role'));
    }

    /**
     * Batalkan pengajuan (status → Dibatalkan) — match DetailHistory handleBatal
     */
    public function batalSiswa(Request $request, int $id)
    {
        $siswaId = Session::get('auth_id');
        $row = Konseling::where('id', $id)->where('siswa_id', $siswaId)->firstOrFail();

        if (($row->status ?? '') !== 'Proses') {
            return redirect()->route('siswa.konseling.show', $row->id)
                ->with('error', 'Hanya pengajuan berstatus Proses yang dapat dibatalkan.');
        }

        $data = $request->validate([
            'alasan' => 'required|string|min:10|max:1000',
        ], [
            'alasan.required' => 'Alasan pembatalan wajib diisi.',
            'alasan.min' => 'Alasan pembatalan minimal 10 karakter.',
        ]);

        $row->status = 'Dibatalkan';
        if (Schema::hasColumn('konseling', 'alasan_batal')) {
            $row->alasan_batal = $data['alasan'];
        }
        $row->save();

        return redirect()->route('siswa.konseling.show', $row->id)
            ->with('success', 'Pengajuan konseling berhasil dibatalkan.');
    }

    public function konfirmasi(Request $request, int $id)
    {
        $row = $this->findGuruKonseling($id);

        $data = $request->validate([
            'tanggal_konfirmasi' => 'required|date',
            'jam_konfirmasi' => 'required|string|max:10',
            'status_konfirmasi' => 'nullable|string|max:30',
        ], [
            'tanggal_konfirmasi.required' => 'Tanggal konfirmasi wajib diisi.',
            'jam_konfirmasi.required' => 'Jam konfirmasi wajib diisi.',
        ]);

        // Samakan label status dengan React: Terkonfirmasi
        $row->status_konfirmasi = $data['status_konfirmasi'] ?? 'Terkonfirmasi';
        if (in_array($row->status_konfirmasi, ['Dikonfirmasi', 'Tervalidasi'], true)) {
            $row->status_konfirmasi = 'Terkonfirmasi';
        }
        $row->tanggal_konfirmasi = $data['tanggal_konfirmasi'];
        $row->jam_konfirmasi = $data['jam_konfirmasi'];
        // Juga update jadwal utama agar siswa melihat jadwal yang dikonfirmasi
        $row->tanggal = $data['tanggal_konfirmasi'];
        $row->jam = $data['jam_konfirmasi'];
        if (($row->status ?? '') === 'Proses' || empty($row->status)) {
            $row->status = 'Proses';
        }
        $row->save();

        if ($row->siswa) {
            $payload = [
                'judul' => 'Jadwal Konseling Dikonfirmasi',
                'pesan' => 'Pengajuan konseling Anda dikonfirmasi: ' . ($data['status_konfirmasi'] ?? ''),
                'tipe' => 'konseling',
                'created_at' => now(),
            ];
            if (Schema::hasColumn('notifikasi', 'siswa_id')) {
                $payload['siswa_id'] = $row->siswa_id;
            }
            if (Schema::hasColumn('notifikasi', 'konseling_id')) {
                $payload['konseling_id'] = $row->id;
            }
            if (Schema::hasColumn('notifikasi', 'is_read')) {
                $payload['is_read'] = false;
            }
            try {
                Notifikasi::create($payload);
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

        if ($hasLaporan) {
            if (!$row->laporan_created_at) {
                // fallback: izinkan edit
            } else {
                $created = \Carbon\Carbon::parse($row->laporan_created_at);
                $jamBerlalu = $created->diffInMinutes(now()) / 60;
                if ($jamBerlalu > $windowHours) {
                    return back()->with('error', "Laporan terkunci. Batas edit {$windowHours} jam setelah pertama disimpan sudah lewat.");
                }
            }
            $row->laporan_kesimpulan = $data['laporan_kesimpulan'];
            $row->laporan_rekomendasi = $data['laporan_rekomendasi'];
            $row->laporan_status_penanganan = $data['laporan_status_penanganan'];
            $row->laporan_catatan_tambahan = $data['laporan_catatan_tambahan'] ?? '-';
            $row->save();
            $msg = 'Laporan berhasil diperbarui.';
        } else {
            if (!$isConfirmed || ($row->status ?? '') === 'Dibatalkan') {
                return back()->with('error', 'Laporan hanya untuk sesi yang sudah dikonfirmasi dan belum dibatalkan.');
            }
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

        // Sesi lanjutan otomatis jika status penanganan Monitoring
        $buatLanjutan = $request->boolean('buat_lanjutan')
            || ($data['laporan_status_penanganan'] === 'Monitoring');
        if ($buatLanjutan && !empty($data['lanjutan_tanggal']) && !empty($data['lanjutan_jam']) && !$hasLaporan) {
            $this->createSesiLanjutan($row, $data);
            $msg .= ' Sesi lanjutan telah dibuat.';
        } elseif ($data['laporan_status_penanganan'] === 'Monitoring' && !$hasLaporan && (empty($data['lanjutan_tanggal']) || empty($data['lanjutan_jam']))) {
            return back()->with('error', 'Status Monitoring: isi tanggal & jam sesi lanjutan.');
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

        // Notifikasi siswa jika memungkinkan
        try {
            if ($parent->siswa_id && Schema::hasTable('notifikasi')) {
                $n = ['judul' => 'Sesi Konseling Lanjutan', 'pesan' => 'Guru BK menjadwalkan sesi lanjutan pada ' . $data['lanjutan_tanggal'] . ' jam ' . $data['lanjutan_jam'], 'tipe' => 'konseling', 'created_at' => now()];
                if (Schema::hasColumn('notifikasi', 'siswa_id')) $n['siswa_id'] = $parent->siswa_id;
                if (Schema::hasColumn('notifikasi', 'konseling_id')) $n['konseling_id'] = $child->id;
                if (Schema::hasColumn('notifikasi', 'is_read')) $n['is_read'] = false;
                Notifikasi::create($n);
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
            'kategori' => 'required|string|max:50',
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

        if (($row->status ?? '') !== 'Proses') {
            return back()->with('error', 'Hanya pengajuan berstatus Proses yang dapat dibatalkan.');
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
