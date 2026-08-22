<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesBk;
use App\Models\GuruBk;
use App\Models\Konseling;
use App\Models\Notifikasi;
use App\Models\Siswa;
use App\Services\ScheduleService;
use App\Support\KategoriKonseling;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class KonselingController extends Controller
{
    use AuthorizesBk;

    public function __construct(private ScheduleService $schedule)
    {
    }

    /** Transisi status yang diizinkan */
    private const TRANSITIONS = [
        'Menunggu' => ['Proses', 'Dibatalkan', 'Ditolak'],
        'Proses'   => ['Selesai', 'Dibatalkan'],
        'Selesai'  => [],
        'Dibatalkan' => [],
        'Ditolak'  => [],
    ];

    public function listBySiswa(Request $request, string $nis): JsonResponse
    {
        $this->assertSiswaOwnsNis($request, $nis);

        $siswa = Siswa::where('nis', $nis)->first();
        if (!$siswa) {
            return response()->json(['success' => false, 'message' => 'Siswa tidak ditemukan'], 404);
        }

        $rows = Konseling::where('siswa_id', $siswa->id)
            ->orderByDesc('created_at')
            ->get();

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
                   ->orWhere('guru_bk', $nama);
            });
        }

        return response()->json(['success' => true, 'data' => $q->get()]);
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

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function getDetail(Request $request, int $id): JsonResponse
    {
        $row = Konseling::with('siswa:id,nis,nama,kelas,foto_profile')->find($id);
        if (!$row) {
            return response()->json(['success' => false, 'message' => 'Tidak ditemukan'], 404);
        }

        $this->assertCanViewKonseling($request, $row);

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
        // bersama untuk web & API, lihat ScheduleService.
        $conflict = $this->schedule->hasConflict(
            $siswa->id,
            $guru->id,
            $guru->nama,
            $data['tanggal'],
            $data['jam']
        );

        if ($conflict) {
            return response()->json([
                'success' => false,
                'message' => 'Jadwal bentrok. Siswa atau Guru BK sudah memiliki konseling di tanggal/jam tersebut.',
            ], 409);
        }

        $row = Konseling::create([
            'siswa_id' => $siswa->id,
            'guru_id' => $guru->id,
            'guru_bk' => $guru->nama,
            'tanggal' => $data['tanggal'],
            'jam' => $data['jam'],
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

        return response()->json(['success' => true, 'data' => $row], 201);
    }

    public function walkin(Request $request): JsonResponse
    {
        if (!$this->isRole($request, 'guru', 'admin')) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak'], 403);
        }

        // ... implement similar to store but for walk-in, status Proses
        $v = Validator::make($request->all(), [
            'siswa_id' => 'required_without:nis|integer',
            'nis' => 'required_without:siswa_id|string',
            'kategori' => ['required', 'string', Rule::in(KategoriKonseling::ALL)],
            'deskripsi' => 'required|string|min:10',
            'jenis' => 'nullable|in:Luring,Daring',
            'catatan_walkin' => 'nullable|string',
        ]);
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
        $row = Konseling::create([
            'siswa_id' => $siswa->id,
            'guru_id' => $user->id,
            'guru_bk' => $user->nama ?? $user->username,
            'tanggal' => now()->toDateString(),
            'jam' => now()->format('H:i'),
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

        return response()->json(['success' => true, 'data' => $row], 201);
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
        } else {
            // Cek konflik jika ubah tanggal/jam — via ScheduleService bersama
            $tgl = $data['tanggal'] ?? $row->tanggal;
            $jam = $data['jam'] ?? $row->jam;
            $conflict = $this->schedule->hasConflictFor($row, $tgl, $jam);
            if ($conflict) {
                return response()->json(['success' => false, 'message' => 'Jadwal bentrok'], 409);
            }

            $row->tanggal = $tgl;
            $row->jam = $jam;
            $row->status = 'Proses';
            $row->status_konfirmasi = 'Dikonfirmasi';
            $row->tanggal_konfirmasi = now()->toDateString();
            $row->jam_konfirmasi = now()->format('H:i');
        }
        $row->save();

        if ($row->siswa) {
            Notifikasi::create([
                'penerima_id' => $row->siswa->nis,
                'penerima_role' => 'siswa',
                'judul' => $konfirmasi === 'Ditolak' ? 'Pengajuan Ditolak' : 'Jadwal Konseling Dikonfirmasi',
                'pesan' => $konfirmasi === 'Ditolak'
                    ? ('Pengajuan ditolak. Alasan: ' . ($row->alasan_batal ?? '-'))
                    : 'Jadwal konseling Anda telah dikonfirmasi.',
                'tipe' => 'konseling',
                'data' => json_encode(['konseling_id' => $row->id]),
                'created_at' => now(),
            ]);
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
            'laporan' => 'nullable|string',
            'laporan_kesimpulan' => 'nullable|string',
            'laporan_rekomendasi' => 'nullable|string',
            'laporan_status_penanganan' => 'nullable|string|max:50',
            'laporan_catatan_tambahan' => 'nullable|string',
        ]);
        if ($v->fails()) {
            return response()->json(['success' => false, 'message' => $v->errors()->first()], 400);
        }

        $user = $request->user();
        $row->fill($v->validated());
        $row->laporan_tanggal = now()->toDateString();
        $row->laporan_waktu = now()->format('H:i:s');
        $row->laporan_dibuat_oleh = $user->nama ?? $user->username ?? 'Guru BK';
        $row->laporan_created_at = now();
        $row->status = 'Selesai';
        $row->save();

        return response()->json(['success' => true, 'message' => 'Laporan disimpan', 'data' => $row]);
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

        $row->status = $status;
        if ($alasan) {
            $row->alasan_batal = $alasan;
        }
        $row->save();

        // Notifikasi
        if ($row->siswa && in_array($status, ['Dibatalkan', 'Ditolak'], true)) {
            Notifikasi::create([
                'penerima_id' => $row->siswa->nis,
                'penerima_role' => 'siswa',
                'judul' => 'Konseling Dibatalkan',
                'pesan' => 'Konseling dibatalkan. Alasan: ' . ($alasan ?? '-'),
                'tipe' => 'konseling',
                'data' => json_encode(['konseling_id' => $row->id]),
                'created_at' => now(),
            ]);
        }

        return response()->json(['success' => true, 'message' => 'Status diperbarui', 'data' => $row]);
    }
}
