<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RiwayatKelas;
use App\Models\Siswa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RiwayatKelasController extends Controller
{
    /**
     * PERBAIKAN (revisi 27 Agustus 2026, poin 3): riwayat_kelas tidak
     * lagi punya kolom 'nis' sendiri (lihat migration
     * add_siswa_id_to_riwayat_kelas dan Model\RiwayatKelas) — data
     * dihubungkan lewat siswa_id. Kontrak endpoint publik ini SENGAJA
     * dipertahankan memakai {nis} di URL (tidak mengubah API untuk
     * klien yang sudah ada); method ini yang menerjemahkan NIS dari
     * request jadi siswa_id di lapisan internal.
     */
    public function list(string $nis): JsonResponse
    {
        $siswa = Siswa::where('nis', $nis)->first(['id', 'nis']);
        if (!$siswa) {
            return response()->json(['success' => true, 'data' => []]);
        }

        // Eager-load relasi siswa (dan set manual ke siswa yang sudah
        // ada di tangan) supaya accessor 'nis' pada tiap baris tidak
        // memicu query tambahan per baris (N+1).
        $rows = RiwayatKelas::where('siswa_id', $siswa->id)
            ->orderByDesc('tahun_ajaran')
            ->get()
            ->each(fn ($row) => $row->setRelation('siswa', $siswa));

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function getAktif(string $nis): JsonResponse
    {
        $siswa = Siswa::where('nis', $nis)->first(['id', 'nis']);
        if (!$siswa) {
            return response()->json(['success' => true, 'data' => null]);
        }

        $row = RiwayatKelas::where('siswa_id', $siswa->id)->where('status', 'aktif')->first();
        $row?->setRelation('siswa', $siswa);

        return response()->json(['success' => true, 'data' => $row]);
    }

    public function create(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            // PERBAIKAN (revisi 26 Agustus 2026, poin 8): dulu
            // 'string|max:20' — diseragamkan dengan aturan NIS di seluruh
            // sistem (tepat 4 digit angka).
            'nis' => 'required|digits:4',
            'tahun_ajaran' => 'required|string|max:9',
            'kelas' => 'required|string|max:20',
            'status' => 'nullable|in:aktif,arsip',
        ]);
        if ($v->fails()) {
            return response()->json(['success' => false, 'message' => $v->errors()->first()], 400);
        }

        $data = $v->validated();
        $siswa = Siswa::where('nis', $data['nis'])->first(['id', 'nis']);
        if (!$siswa) {
            return response()->json(['success' => false, 'message' => 'Siswa dengan NIS tersebut tidak ditemukan'], 404);
        }
        unset($data['nis']);
        $data['siswa_id'] = $siswa->id;

        $row = RiwayatKelas::create($data);
        $row->setRelation('siswa', $siswa);

        return response()->json(['success' => true, 'data' => $row], 201);
    }

    public function remove(int $id): JsonResponse
    {
        $row = RiwayatKelas::find($id);
        if (!$row) {
            return response()->json(['success' => false, 'message' => 'Tidak ditemukan'], 404);
        }
        $row->delete();
        return response()->json(['success' => true, 'message' => 'Dihapus']);
    }
}
