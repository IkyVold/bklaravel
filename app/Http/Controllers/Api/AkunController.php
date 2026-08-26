<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\GuruBk;
use App\Models\Kepsek;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AkunController extends Controller
{
    public function listGuru(): JsonResponse
    {
        $rows = GuruBk::orderBy('nama')->get(['id', 'username', 'nama', 'spesialisasi', 'npsn', 'alamat', 'avatar', 'foto_profile', 'is_active', 'created_at']);
        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function createGuru(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'username' => 'required|string|max:50|unique:guru_bk,username',
            'password' => 'required|string|min:8',
            'nama' => 'required|string|max:100',
            'spesialisasi' => 'nullable|string|max:100',
            'npsn' => 'nullable|string|max:30',
            'alamat' => 'nullable|string|max:150',
            'avatar' => 'nullable|string|max:10',
        ]);
        if ($v->fails()) {
            return response()->json(['success' => false, 'message' => $v->errors()->first()], 400);
        }
        $row = GuruBk::create($v->validated());
        return response()->json(['success' => true, 'data' => $row->makeHidden('password')], 201);
    }

    public function updateGuru(Request $request, int $id): JsonResponse
    {
        $row = GuruBk::find($id);
        if (!$row) {
            return response()->json(['success' => false, 'message' => 'Tidak ditemukan'], 404);
        }

        if ($request->filled('password')) {
            $v = Validator::make($request->all(), ['password' => 'string|min:8']);
            if ($v->fails()) {
                return response()->json(['success' => false, 'message' => $v->errors()->first()], 400);
            }
        }

        $data = $request->only(['username', 'password', 'nama', 'spesialisasi', 'npsn', 'alamat', 'avatar', 'is_active']);
        if (empty($data['password'])) {
            unset($data['password']);
        }
        $passwordChanged = array_key_exists('password', $data);
        $row->fill($data)->save();

        if ($passwordChanged || !$row->is_active) {
            $row->tokens()->delete();
        }

        return response()->json(['success' => true, 'data' => $row->fresh()->makeHidden('password')]);
    }

    public function deleteGuru(int $id): JsonResponse
    {
        $row = GuruBk::find($id);
        if (!$row) {
            return response()->json(['success' => false, 'message' => 'Tidak ditemukan'], 404);
        }
        $row->is_active = false;
        $row->save();
        $row->tokens()->delete();
        return response()->json(['success' => true, 'message' => 'Akun dinonaktifkan']);
    }

    public function listKepsek(): JsonResponse
    {
        $rows = Kepsek::orderBy('nama')->get(['id', 'username', 'nama', 'npsn', 'is_active', 'created_at']);
        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function createKepsek(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'username' => 'required|string|max:50|unique:kepsek,username',
            'password' => 'required|string|min:8',
            'nama' => 'required|string|max:100',
            'npsn' => 'nullable|string|max:30',
        ]);
        if ($v->fails()) {
            return response()->json(['success' => false, 'message' => $v->errors()->first()], 400);
        }
        $row = Kepsek::create($v->validated());
        return response()->json(['success' => true, 'data' => $row->makeHidden('password')], 201);
    }

    public function updateKepsek(Request $request, int $id): JsonResponse
    {
        $row = Kepsek::find($id);
        if (!$row) {
            return response()->json(['success' => false, 'message' => 'Tidak ditemukan'], 404);
        }

        if ($request->filled('password')) {
            $v = Validator::make($request->all(), ['password' => 'string|min:8']);
            if ($v->fails()) {
                return response()->json(['success' => false, 'message' => $v->errors()->first()], 400);
            }
        }

        $data = $request->only(['username', 'password', 'nama', 'npsn', 'is_active']);
        if (empty($data['password'])) {
            unset($data['password']);
        }
        $passwordChanged = array_key_exists('password', $data);
        $row->fill($data)->save();

        if ($passwordChanged || !$row->is_active) {
            $row->tokens()->delete();
        }

        return response()->json(['success' => true, 'data' => $row->fresh()->makeHidden('password')]);
    }

    public function deleteKepsek(int $id): JsonResponse
    {
        $row = Kepsek::find($id);
        if (!$row) {
            return response()->json(['success' => false, 'message' => 'Tidak ditemukan'], 404);
        }
        $row->is_active = false;
        $row->save();
        $row->tokens()->delete();
        return response()->json(['success' => true, 'message' => 'Akun dinonaktifkan']);
    }
}
