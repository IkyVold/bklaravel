<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Konseling;
use App\Models\Notifikasi;
use App\Models\Siswa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class KonselingController extends Controller
{
    public function listBySiswa(Request $request, string $nis): JsonResponse
    {
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
        $user = $request->user();
        $nama = $user->nama ?? $user->username ?? null;

        $q = Konseling::with('siswa:id,nis,nama,kelas')->orderByDesc('created_at');

        if ($nama && !in_array('admin', $user->currentAccessToken()?->abilities ?? [], true)) {
            $q->where('guru_bk', $nama);
        }

        return response()->json(['success' => true, 'data' => $q->get()]);
    }

    public function listAll(Request $request): JsonResponse
    {
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
        return response()->json(['success' => true, 'data' => $row]);
    }

    public function store(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'siswa_id' => 'required_without:nis|integer',
            'nis' => 'required_without:siswa_id|string',
            'guru_bk' => 'nullable|string|max:100',
            'tanggal' => 'nullable|date',
            'jam' => 'nullable',
            'jenis' => 'nullable|string|max:20',
            'kategori' => 'nullable|string|max:50',
            'deskripsi' => 'nullable|string',
            'kelas_siswa' => 'nullable|string|max:20',
        ]);

        if ($v->fails()) {
            return response()->json(['success' => false, 'message' => $v->errors()->first()], 400);
        }

        $data = $v->validated();

        if (empty($data['siswa_id']) && !empty($data['nis'])) {
            $siswa = Siswa::where('nis', $data['nis'])->first();
            if (!$siswa) {
                return response()->json(['success' => false, 'message' => 'Siswa tidak ditemukan'], 404);
            }
            $data['siswa_id'] = $siswa->id;
            $data['kelas_siswa'] = $data['kelas_siswa'] ?? $siswa->kelas;
        }
        unset($data['nis']);
        $data['created_at'] = now();
        $data['status'] = $data['status'] ?? 'Proses';
        $data['status_konfirmasi'] = $data['status_konfirmasi'] ?? 'Belum Dikonfirmasi';

        $row = Konseling::create($data);

        return response()->json(['success' => true, 'message' => 'Pengajuan konseling berhasil', 'data' => $row], 201);
    }

    public function walkin(Request $request): JsonResponse
    {
        $request->merge(['input_manual' => true]);
        return $this->store($request);
    }

    public function konfirmasi(Request $request, int $id): JsonResponse
    {
        $row = Konseling::find($id);
        if (!$row) {
            return response()->json(['success' => false, 'message' => 'Tidak ditemukan'], 404);
        }

        $v = Validator::make($request->all(), [
            'tanggal_konfirmasi' => 'nullable|date',
            'jam_konfirmasi' => 'nullable',
            'status_konfirmasi' => 'nullable|string|max:30',
        ]);

        if ($v->fails()) {
            return response()->json(['success' => false, 'message' => $v->errors()->first()], 400);
        }

        $row->fill($v->validated());
        $row->status_konfirmasi = $row->status_konfirmasi ?: 'Dikonfirmasi';
        $row->tanggal_konfirmasi = $row->tanggal_konfirmasi ?: now()->toDateString();
        $row->save();

        // Notifikasi ke siswa
        if ($row->siswa) {
            Notifikasi::create([
                'penerima_id' => $row->siswa->nis,
                'penerima_role' => 'siswa',
                'judul' => 'Jadwal Konseling Dikonfirmasi',
                'pesan' => 'Jadwal konseling Anda telah dikonfirmasi.',
                'tipe' => 'konseling',
                'data' => ['konseling_id' => $row->id],
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

        $v = Validator::make($request->all(), [
            'laporan' => 'nullable|string',
            'laporan_kesimpulan' => 'nullable|string',
            'laporan_rekomendasi' => 'nullable|string',
            'laporan_status_penanganan' => 'nullable|string|max:50',
            'laporan_catatan_tambahan' => 'nullable|string',
            'status' => 'nullable|string|max:20',
        ]);

        if ($v->fails()) {
            return response()->json(['success' => false, 'message' => $v->errors()->first()], 400);
        }

        $user = $request->user();
        $row->fill($v->validated());
        $row->laporan_tanggal = now()->toDateString();
        $row->laporan_waktu = now()->toTimeString();
        $row->laporan_dibuat_oleh = $user->nama ?? $user->username ?? 'Guru BK';
        $row->laporan_created_at = now();
        if (empty($row->status) || $row->status === 'Proses') {
            $row->status = 'Selesai';
        }
        $row->save();

        return response()->json(['success' => true, 'message' => 'Laporan disimpan', 'data' => $row]);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $row = Konseling::find($id);
        if (!$row) {
            return response()->json(['success' => false, 'message' => 'Tidak ditemukan'], 404);
        }

        $status = $request->input('status');
        if (!$status) {
            return response()->json(['success' => false, 'message' => 'Status wajib diisi'], 400);
        }

        $row->status = $status;
        $row->save();

        return response()->json(['success' => true, 'message' => 'Status diperbarui', 'data' => $row]);
    }
}
