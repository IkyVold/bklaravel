<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesBk;
use App\Models\GuruBk;
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

        $rules = [
            'judul' => 'required|string|max:150',
            'kategori' => 'required|string|max:50',
            'isi' => 'required|string',
        ];

        if ($this->isAdmin($request)) {
            $rules['guru_id'] = 'required|integer';
        }

        $v = Validator::make($request->all(), $rules);
        if ($v->fails()) {
            return response()->json(['success' => false, 'message' => $v->errors()->first()], 400);
        }
        $data = $v->validated();

        if ($this->isGuru($request)) {
            $user = $request->user();
            $data['guru_bk'] = $user->nama ?? 'Guru BK';
            $data['guru_id'] = $user->id;
        } else {
            $guru = GuruBk::find($data['guru_id']);
            if (!$guru) {
                return response()->json(['success' => false, 'message' => 'Guru BK tidak ditemukan'], 404);
            }
            if (!($guru->is_active ?? true)) {
                return response()->json(['success' => false, 'message' => 'Guru BK tidak aktif'], 400);
            }
            $data['guru_bk'] = $guru->nama;
            $data['guru_id'] = $guru->id;
        }

        $row = InformasiBk::create($data);
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

        $this->assertGuruCanManageInformasi($request, $row);

        $row->fill($request->only(['judul', 'kategori', 'isi']))->save();
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

        $this->assertGuruCanManageInformasi($request, $row);

        $row->delete();
        return response()->json(['success' => true, 'message' => 'Dihapus']);
    }
}
