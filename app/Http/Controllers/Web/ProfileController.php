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
        $mustChangePassword = (bool) $siswa->must_change_password;
        return view('siswa.profile', compact('siswa', 'mustChangePassword'));
    }

    public function update(Request $request)
    {
        $siswa = Siswa::findOrFail(Session::get('auth_id'));

        // Update satu field (match React modal edit)
        if ($request->filled('edit_field')) {
            $field = $request->input('edit_field');
            $allowed = ['jenis_kelamin', 'tanggal_lahir', 'alamat', 'no_telepon', 'password'];
            if (!in_array($field, $allowed, true)) {
                return back()->with('error', 'Field tidak diizinkan.');
            }

            $rules = match ($field) {
                'jenis_kelamin' => ['edit_value' => 'nullable|string|in:Laki-laki,Perempuan'],
                'tanggal_lahir' => ['edit_value' => 'nullable|date'],
                'alamat' => ['edit_value' => 'nullable|string|max:500'],
                'no_telepon' => ['edit_value' => 'nullable|string|max:30'],
                'password' => ['edit_value' => 'required|string|min:6|confirmed', 'current_password' => 'required|string'],
                default => ['edit_value' => 'nullable|string'],
            };
            $request->validate($rules);

            if ($field === 'password') {
                if (!$siswa->verifyPassword((string) $request->input('current_password'))) {
                    return back()->with('error', 'Password saat ini tidak sesuai.');
                }

                // Siswa mengganti password sendiri — bebaskan dari
                // kewajiban ganti password (lihat RoleAuth middleware).
                $siswa->update(['password' => $request->input('edit_value'), 'must_change_password' => false]);
                Session::put('auth_user', array_merge(Session::get('auth_user', []), [
                    'must_change_password' => false,
                ]));
                return back()->with('success', 'Password berhasil diganti!');
            }

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

        $data = $request->validate([
            'jenis_kelamin' => 'nullable|string|max:20',
            'tanggal_lahir' => 'nullable|date',
            'alamat' => 'nullable|string',
            'no_telepon' => 'nullable|string|max:30',
            'password' => 'nullable|string|min:6|confirmed',
            'current_password' => 'required_with:password|string',
            'foto' => 'nullable|image|max:2048',
        ]);

        if (!empty($data['password']) && !$siswa->verifyPassword((string) $data['current_password'])) {
            return back()->with('error', 'Password saat ini tidak sesuai.');
        }
        unset($data['current_password']);

        if ($request->hasFile('foto')) {
            if ($siswa->foto_profile) {
                Storage::disk('public')->delete($siswa->foto_profile);
            }
            $data['foto_profile'] = $request->file('foto')->store('siswa', 'public');
        }
        unset($data['foto']);
        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['must_change_password'] = false;
        }

        $siswa->update($data);

        if (array_key_exists('must_change_password', $data)) {
            Session::put('auth_user', array_merge(Session::get('auth_user', []), [
                'must_change_password' => false,
            ]));
        }

        Session::put('auth_user', array_merge(Session::get('auth_user', []), [
            'nama' => $siswa->nama,
            'foto' => $siswa->foto_profile,
        ]));

        return back()->with('success', 'Profil diperbarui.');
    }
}
