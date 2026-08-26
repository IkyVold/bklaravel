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
        if ($this->isGuru($request) || $this->isKepsek($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Guru BK dan Kepala Sekolah hanya dapat melihat profil siswa, bukan mengubahnya.',
            ], 403);
        }

        $this->assertSiswaOwnsNis($request, $nis);

        $siswa = Siswa::where('nis', $nis)->first();
        if (!$siswa) {
            return response()->json(['success' => false, 'message' => 'Tidak ditemukan'], 404);
        }

        // Siswa hanya boleh ubah field tertentu; Admin (satu-satunya staff
        // yang masih lolos di atas) boleh lebih.
        // 'nama' SENGAJA tidak termasuk di sini pada endpoint mana pun —
        // nama, NIS, dan kelas adalah data administratif sekolah dan hanya
        // boleh diubah lewat manajemen data siswa (Api/SiswaController),
        // bukan lewat profil sendiri. Karena 'nama' tidak ada di daftar
        // rules, Validator::validated() tidak akan pernah mengembalikannya
        // walau client mengirimkannya di body request.
        $rules = [
            'jenis_kelamin' => 'nullable|in:Laki-laki,Perempuan',
            'tanggal_lahir' => 'nullable|date',
            'alamat' => 'nullable|string|max:500',
            'no_telepon' => 'nullable|string|max:15',
        ];
        if ($this->isAdmin($request)) {
            $rules['kelas'] = 'sometimes|string|max:20';
        }

        if ($this->isSiswa($request) || $this->isAdmin($request)) {
            $rules['password'] = 'nullable|string|min:6';
        }

        if ($this->isSiswa($request)) {
            $rules['current_password'] = 'required_with:password|string';
        }

        $v = Validator::make($request->all(), $rules);
        if ($v->fails()) {
            return response()->json(['success' => false, 'message' => $v->errors()->first()], 400);
        }

        $data = $v->validated();

        if ($this->isSiswa($request) && !empty($data['password'])) {
            if (!$siswa->verifyPassword($data['current_password'])) {
                return response()->json(['success' => false, 'message' => 'Password saat ini tidak sesuai'], 400);
            }
        }
        unset($data['current_password']);

        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['must_change_password'] = !$this->isSiswa($request);
        }
        $siswa->fill($data)->save();

        $siswa->refresh();

        // collect()->only() dipakai (bukan Model::only(), yang tidak ada
        // di Eloquent) untuk memilih subset kolom secara aman dari array
        // hasil toArray().
        return response()->json([
            'success' => true,
            'message' => 'Profil diperbarui',
            'data' => collect($siswa->toArray())
                ->only(['id', 'nis', 'nama', 'kelas', 'jenis_kelamin', 'tanggal_lahir', 'alamat', 'no_telepon', 'foto_profile'])
                ->all(),
        ]);
    }

    public function updateFoto(Request $request, string $nis): JsonResponse
    {
        if ($this->isGuru($request) || $this->isKepsek($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Guru BK dan Kepala Sekolah hanya dapat melihat profil siswa, bukan mengubahnya.',
            ], 403);
        }

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
        if ($this->isGuru($request) || $this->isKepsek($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Guru BK dan Kepala Sekolah hanya dapat melihat profil siswa, bukan mengubahnya.',
            ], 403);
        }

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
