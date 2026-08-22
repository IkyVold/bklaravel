<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Siswa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function show()
    {
        $siswa = Siswa::findOrFail(Session::get('auth_id'));
        return view('siswa.profile', compact('siswa'));
    }

    public function update(Request $request)
    {
        $siswa = Siswa::findOrFail(Session::get('auth_id'));

        // Update satu field (match React modal edit)
        if ($request->filled('edit_field')) {
            $field = $request->input('edit_field');
            $allowed = ['jenis_kelamin', 'tanggal_lahir', 'alamat', 'no_telepon'];
            if (!in_array($field, $allowed, true)) {
                return back()->with('error', 'Field tidak diizinkan.');
            }

            $rules = match ($field) {
                'jenis_kelamin' => ['edit_value' => 'nullable|string|in:Laki-laki,Perempuan'],
                'tanggal_lahir' => ['edit_value' => 'nullable|date'],
                'alamat' => ['edit_value' => 'nullable|string|max:500'],
                'no_telepon' => ['edit_value' => 'nullable|string|max:30'],
                default => ['edit_value' => 'nullable|string'],
            };
            $request->validate($rules);

            $siswa->update([$field => $request->input('edit_value')]);

            return back()->with('success', 'Profile berhasil diupdate!');
        }

        // Upload foto saja
        if ($request->hasFile('foto')) {
            $request->validate(['foto' => 'required|image|mimes:jpeg,jpg,png,webp|max:2048']);
            if ($siswa->foto_profile) {
                Storage::disk('public')->delete($siswa->foto_profile);
            }
            $path = $request->file('foto')->store('siswa', 'public');
            $siswa->update(['foto_profile' => $path]);
            Session::put('auth_user', array_merge(Session::get('auth_user', []), [
                'foto' => $path,
            ]));
            return back()->with('success', 'Foto profil berhasil diperbarui.');
        }

        // Full form fallback (password opsional).
        // 'nama' SENGAJA tidak divalidasi/diterima di sini — mode edit_field
        // di atas sudah membatasi field yang boleh diubah siswa, dan
        // fallback ini dulu membuka celah karena masih mengizinkan 'nama'.
        // Nama, NIS, dan kelas adalah data administratif sekolah; ubahnya
        // hanya lewat manajemen data siswa (Web/SiswaController), bukan
        // lewat profil sendiri.
        $data = $request->validate([
            'jenis_kelamin' => 'nullable|string|max:20',
            'tanggal_lahir' => 'nullable|date',
            'alamat' => 'nullable|string',
            'no_telepon' => 'nullable|string|max:30',
            'password' => 'nullable|string|min:4|confirmed',
            'foto' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('foto')) {
            if ($siswa->foto_profile) {
                Storage::disk('public')->delete($siswa->foto_profile);
            }
            $data['foto_profile'] = $request->file('foto')->store('siswa', 'public');
        }
        unset($data['foto']);
        if (empty($data['password'])) {
            unset($data['password']);
        }

        $siswa->update($data);

        Session::put('auth_user', array_merge(Session::get('auth_user', []), [
            'nama' => $siswa->nama,
            'foto' => $siswa->foto_profile,
        ]));

        return back()->with('success', 'Profil diperbarui.');
    }
}
