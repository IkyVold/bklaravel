<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesBk;
use App\Models\InformasiBk;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class InformasiController extends Controller
{
    use AuthorizesBk;

    public function list(): JsonResponse
    {
        $rows = InformasiBk::orderByDesc('created_at')->get();
        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function create(Request $request): JsonResponse
    {
        if (!$this->isRole($request, 'guru', 'admin')) {
            return response()->json(['success' => false, 'message' => 'Hanya Guru BK / Admin yang dapat menambah informasi'], 403);
        }

        $v = Validator::make($request->all(), [
            'judul' => 'required|string|max:150',
            'kategori' => 'required|string|max:50',
            'isi' => 'required|string',
            'guru_bk' => 'required|string|max:100',
        ]);
        if ($v->fails()) {
            return response()->json(['success' => false, 'message' => $v->errors()->first()], 400);
        }
        $row = InformasiBk::create($v->validated());
        return response()->json(['success' => true, 'data' => $row], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        if (!$this->isRole($request, 'guru', 'admin')) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak'], 403);
        }

        $row = InformasiBk::find($id);
        if (!$row) {
            return response()->json(['success' => false, 'message' => 'Tidak ditemukan'], 404);
        }
        $row->fill($request->only(['judul', 'kategori', 'isi', 'guru_bk']))->save();
        return response()->json(['success' => true, 'data' => $row]);
    }

    public function remove(Request $request, int $id): JsonResponse
    {
        if (!$this->isRole($request, 'guru', 'admin')) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak'], 403);
        }

        $row = InformasiBk::find($id);
        if (!$row) {
            return response()->json(['success' => false, 'message' => 'Tidak ditemukan'], 404);
        }
        $row->delete();
        return response()->json(['success' => true, 'message' => 'Dihapus']);
    }
}
