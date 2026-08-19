<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Siswa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    public function get(string $nis): JsonResponse
    {
        $siswa = Siswa::where('nis', $nis)->first(['id', 'nis', 'nama', 'kelas', 'jenis_kelamin', 'tanggal_lahir', 'alamat', 'no_telepon', 'foto_profile']);
        if (!$siswa) {
            return response()->json(['success' => false, 'message' => 'Tidak ditemukan'], 404);
        }
        return response()->json(['success' => true, 'data' => $siswa]);
    }

    public function update(Request $request, string $nis): JsonResponse
    {
        $siswa = Siswa::where('nis', $nis)->first();
        if (!$siswa) {
            return response()->json(['success' => false, 'message' => 'Tidak ditemukan'], 404);
        }

        $v = Validator::make($request->all(), [
            'nama' => 'sometimes|string|max:100',
            'kelas' => 'sometimes|string|max:20',
            'jenis_kelamin' => 'nullable|in:Laki-laki,Perempuan',
            'tanggal_lahir' => 'nullable|date',
            'alamat' => 'nullable|string',
            'no_telepon' => 'nullable|string|max:15',
            'password' => 'nullable|string|min:4',
        ]);

        if ($v->fails()) {
            return response()->json(['success' => false, 'message' => $v->errors()->first()], 400);
        }

        $data = $v->validated();
        if (empty($data['password'])) {
            unset($data['password']);
        }
        $siswa->fill($data)->save();

        return response()->json(['success' => true, 'message' => 'Profil diperbarui', 'data' => $siswa->fresh()]);
    }

    public function updateFoto(Request $request, string $nis): JsonResponse
    {
        $siswa = Siswa::where('nis', $nis)->first();
        if (!$siswa) {
            return response()->json(['success' => false, 'message' => 'Tidak ditemukan'], 404);
        }

        $request->validate(['foto' => 'required|image|max:2048']);

        if ($siswa->foto_profile) {
            Storage::disk('public')->delete('siswa/' . basename($siswa->foto_profile));
        }

        $path = $request->file('foto')->store('siswa', 'public');
        $siswa->foto_profile = $path;
        $siswa->save();

        return response()->json([
            'success' => true,
            'message' => 'Foto diperbarui',
            'foto_profile' => $path,
            'url' => Storage::disk('public')->url($path),
        ]);
    }

    public function deleteFoto(string $nis): JsonResponse
    {
        $siswa = Siswa::where('nis', $nis)->first();
        if (!$siswa) {
            return response()->json(['success' => false, 'message' => 'Tidak ditemukan'], 404);
        }

        if ($siswa->foto_profile) {
            Storage::disk('public')->delete('siswa/' . basename($siswa->foto_profile));
            $siswa->foto_profile = null;
            $siswa->save();
        }

        return response()->json(['success' => true, 'message' => 'Foto dihapus']);
    }
}
