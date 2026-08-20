<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesBk;
use App\Models\Siswa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    use AuthorizesBk;

    public function get(Request $request, string $nis): JsonResponse
    {
        $this->assertSiswaOwnsNis($request, $nis);

        $siswa = Siswa::where('nis', $nis)->first([
            'id', 'nis', 'nama', 'kelas', 'jenis_kelamin', 'tanggal_lahir',
            'alamat', 'no_telepon', 'foto_profile',
        ]);
        if (!$siswa) {
            return response()->json(['success' => false, 'message' => 'Tidak ditemukan'], 404);
        }
        return response()->json(['success' => true, 'data' => $siswa]);
    }

    public function update(Request $request, string $nis): JsonResponse
    {
        $this->assertSiswaOwnsNis($request, $nis);

        $siswa = Siswa::where('nis', $nis)->first();
        if (!$siswa) {
            return response()->json(['success' => false, 'message' => 'Tidak ditemukan'], 404);
        }

        // Siswa hanya boleh ubah field tertentu; staff boleh lebih
        $rules = [
            'nama' => 'sometimes|string|max:100',
            'jenis_kelamin' => 'nullable|in:Laki-laki,Perempuan',
            'tanggal_lahir' => 'nullable|date',
            'alamat' => 'nullable|string|max:500',
            'no_telepon' => 'nullable|string|max:15',
            'password' => 'nullable|string|min:6',
        ];
        if ($this->isStaff($request)) {
            $rules['kelas'] = 'sometimes|string|max:20';
        }

        $v = Validator::make($request->all(), $rules);
        if ($v->fails()) {
            return response()->json(['success' => false, 'message' => $v->errors()->first()], 400);
        }

        $data = $v->validated();
        if (empty($data['password'])) {
            unset($data['password']);
        }
        $siswa->fill($data)->save();

        return response()->json([
            'success' => true,
            'message' => 'Profil diperbarui',
            'data' => $siswa->fresh(['id', 'nis', 'nama', 'kelas', 'jenis_kelamin', 'tanggal_lahir', 'alamat', 'no_telepon', 'foto_profile']),
        ]);
    }

    public function updateFoto(Request $request, string $nis): JsonResponse
    {
        $this->assertSiswaOwnsNis($request, $nis);

        $siswa = Siswa::where('nis', $nis)->first();
        if (!$siswa) {
            return response()->json(['success' => false, 'message' => 'Tidak ditemukan'], 404);
        }

        $request->validate([
            'foto' => 'required|image|mimes:jpeg,jpg,png,webp|max:2048',
        ]);

        $file = $request->file('foto');

        // Validasi magic bytes sederhana (image)
        $mime = $file->getMimeType();
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return response()->json(['success' => false, 'message' => 'File harus berupa gambar valid'], 400);
        }

        if ($siswa->foto_profile) {
            Storage::disk('public')->delete('siswa/' . basename($siswa->foto_profile));
        }

        // Filename UUID, abaikan ekstensi asli
        $ext = match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
        $filename = Str::uuid()->toString() . '.' . $ext;
        $path = $file->storeAs('siswa', $filename, 'public');

        $siswa->foto_profile = $path;
        $siswa->save();

        return response()->json([
            'success' => true,
            'message' => 'Foto diperbarui',
            'data' => ['foto_profile' => $path],
        ]);
    }

    public function deleteFoto(Request $request, string $nis): JsonResponse
    {
        $this->assertSiswaOwnsNis($request, $nis);

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
