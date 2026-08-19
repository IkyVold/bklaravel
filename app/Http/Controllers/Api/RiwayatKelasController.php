<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RiwayatKelas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RiwayatKelasController extends Controller
{
    public function list(string $nis): JsonResponse
    {
        $rows = RiwayatKelas::where('nis', $nis)->orderByDesc('tahun_ajaran')->get();
        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function getAktif(string $nis): JsonResponse
    {
        $row = RiwayatKelas::where('nis', $nis)->where('status', 'aktif')->first();
        return response()->json(['success' => true, 'data' => $row]);
    }

    public function create(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'nis' => 'required|string|max:20',
            'tahun_ajaran' => 'required|string|max:9',
            'kelas' => 'required|string|max:20',
            'status' => 'nullable|in:aktif,arsip',
        ]);
        if ($v->fails()) {
            return response()->json(['success' => false, 'message' => $v->errors()->first()], 400);
        }
        $row = RiwayatKelas::create($v->validated());
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
