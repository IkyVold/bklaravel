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
    private const TRANSITIONS = [
        'Menunggu' => ['Proses', 'Dibatalkan', 'Ditolak'],
        'Proses'   => ['Dibatalkan'],
        'Selesai'  => [],
        'Dibatalkan' => [],
        'Ditolak'  => [],
    ];

    private const CONFIRMED_STATUSES = ['Terkonfirmasi', 'Dikonfirmasi', 'Tervalidasi'];

    public function listBySiswa(Request $request, string $nis): JsonResponse
    {
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

        if ($this->isKepsek($request) || $this->isAdmin($request)) {
            return response()->json(['success' => true, 'data' => $row->untukMonitoringKepsek()]);
        }

        return response()->json(['success' => true, 'data' => $row]);
    }

    public function store(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'siswa_id' => 'required_without:nis|integer',
            'nis' => 'required_without:siswa_id|string',
            'guru_id' => 'nullable|integer',
            'guru_bk' => 'nullable|string|max:100',
            'tanggal' => 'required|date|after_or_equal:today',
            'jam' => 'required|string|max:10',
            'jenis' => 'required|in:Luring,Daring',
            'kategori' => ['required', 'string', Rule::in(KategoriKonseling::ALL)],
            'deskripsi' => 'required|string|min:20',
            'tipe_jadwal' => 'nullable|string|in:Rutin,Nonrutin',
            'jadwal_rutin_id' => 'nullable|integer',
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

        $rules = [
            'siswa_id' => 'required_without:nis|integer',
            'nis' => 'required_without:siswa_id|string',
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
            'jam' => 'nullable|string|max:10',
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

        $v = Validator::make($request->all(), [
            'laporan_kesimpulan' => 'nullable|string|min:5',
            'laporan_rekomendasi' => 'nullable|string|min:5',
            'laporan_status_penanganan' => ['nullable', 'string', Rule::in(StatusPenanganan::ALL)],
            'laporan_catatan_tambahan' => 'nullable|string',
            'buat_lanjutan' => 'nullable|boolean',
            'lanjutan_tanggal' => 'nullable|date|after_or_equal:today',
            'lanjutan_jam' => 'nullable|string|max:10',
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
