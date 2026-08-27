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

        // PERBAIKAN (revisi 27 Agustus 2026, poin 2 — lanjutan, hasil
        // review dosen penguji): rute 'siswa.profil.update' sengaja
        // dikecualikan dari redirect wajib-ganti-password di RoleAuth
        // (lihat $exemptRoutes di sana) supaya siswa punya jalan untuk
        // benar-benar mematuhi kewajiban itu. Tapi method update() ini
        // sendiri sebelumnya tidak membedakan "permintaan ganti password"
        // dari permintaan lain — selama must_change_password masih true,
        // session APA PUN yang masih bisa mencapai endpoint ini
        // (termasuk session yang sudah dibajak sebelum Admin mereset
        // password) tetap bebas mengubah jenis_kelamin/tanggal_lahir/
        // alamat/no_telepon/foto lewat jalur mana pun di bawah (edit_field
        // field lain, upload foto, atau fallback form penuh), tanpa pernah
        // benar-benar mengganti password — sama seperti celah yang sudah
        // diperbaiki di Api\ProfileController@update.
        //
        // Sekarang, selama siswa masih wajib ganti password (dibaca dari
        // kolom di database, BUKAN snapshot session), request HANYA boleh
        // lanjut kalau ini benar-benar sebuah "percobaan ganti password":
        // baik lewat modal edit_field === 'password', maupun lewat
        // fallback form penuh yang mengisi field 'password'. Semua jalur
        // lain (edit_field field lain, upload foto saja, fallback form
        // tanpa password) ditolak di sini SEBELUM sempat menyentuh
        // Storage atau Siswa::update() sama sekali.
        $isPercobaanGantiPassword = $request->input('edit_field') === 'password'
            || (!$request->filled('edit_field') && $request->filled('password'));

        if ($siswa->must_change_password && !$isPercobaanGantiPassword) {
            return back()->with('error', 'Anda wajib mengganti password default terlebih dahulu sebelum mengubah data lain.');
        }

        // Update satu field (match React modal edit)
        if ($request->filled('edit_field')) {
            $field = $request->input('edit_field');
            // PERBAIKAN (revisi 25 Agustus 2026, poin 11): 'password'
            // ditambahkan ke daftar field yang boleh diubah lewat modal
            // ini. Sebelumnya halaman profil siswa TIDAK punya cara sama
            // sekali untuk mengganti password lewat web (hanya tersedia
            // lewat API) — padahal mekanisme wajib-ganti-password-default
            // (poin 11) mengharuskan siswa punya jalan nyata untuk
            // mematuhinya di web juga, bukan hanya diblokir tanpa jalan
            // keluar.
            $allowed = ['jenis_kelamin', 'tanggal_lahir', 'alamat', 'no_telepon', 'password'];
            if (!in_array($field, $allowed, true)) {
                return back()->with('error', 'Field tidak diizinkan.');
            }

            $rules = match ($field) {
                'jenis_kelamin' => ['edit_value' => 'nullable|string|in:Laki-laki,Perempuan'],
                'tanggal_lahir' => ['edit_value' => 'nullable|date'],
                'alamat' => ['edit_value' => 'nullable|string|max:500'],
                'no_telepon' => ['edit_value' => 'nullable|string|max:30'],
                // PERBAIKAN (revisi 25 Agustus 2026, poin 13): 'current_password'
                // wajib diisi saat mengganti password lewat modal ini —
                // lihat penjelasan lengkap di bawah pada blok $field === 'password'.
                'password' => ['edit_value' => 'required|string|min:6|confirmed', 'current_password' => 'required|string'],
                default => ['edit_value' => 'nullable|string'],
            };
            $request->validate($rules);

            if ($field === 'password') {
                // PERBAIKAN (revisi 25 Agustus 2026, poin 13): dulu siswa
                // bisa mengganti password sendiri tanpa diminta password
                // lama sama sekali. Kalau session siswa berhasil diambil
                // orang lain, attacker bisa langsung ganti password dan
                // mengunci pemilik asli dari akunnya sendiri. Sekarang
                // password lama wajib dicocokkan dulu SEBELUM password
                // baru disimpan.
                if (!$siswa->verifyPassword((string) $request->input('current_password'))) {
                    return back()->with('error', 'Password saat ini tidak sesuai.');
                }

                // Siswa mengganti password sendiri — bebaskan dari
                // kewajiban ganti password (lihat RoleAuth middleware).
                $siswa->update(['password' => $request->input('edit_value'), 'must_change_password' => false]);
                Session::put('auth_user', array_merge(Session::get('auth_user', []), [
                    'must_change_password' => false,
                ]));
                // PERBAIKAN (revisi 27 Agustus 2026, poin 2): password_version
                // baru saja naik lewat update() di atas (lihat
                // Siswa::setPasswordAttribute()). Tanpa baris ini, RoleAuth
                // akan melihat baseline session ini sudah basi pada
                // request BERIKUTNYA dan langsung memaksa siswa yang baru
                // saja mengganti password-nya sendiri untuk logout —
                // padahal ini bukan reset oleh Admin, session ini tetap
                // sah dipakai pemiliknya sendiri.
                Session::put('auth_password_version', (int) $siswa->password_version);
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

        // Full form fallback (password opsional).
        // 'nama' SENGAJA tidak divalidasi/diterima di sini — mode edit_field
        // di atas sudah membatasi field yang boleh diubah siswa, dan
        // fallback ini dulu membuka celah karena masih mengizinkan 'nama'.
        // Nama, NIS, dan kelas adalah data administratif sekolah; ubahnya
        // hanya lewat manajemen data siswa (Web/SiswaController), bukan
        // lewat profil sendiri.
        // PERBAIKAN (revisi 25 Agustus 2026, poin 13): 'current_password'
        // ditambahkan di sini juga (jalur fallback ini tidak dipakai UI
        // saat ini, tapi tetap route yang bisa dipanggil langsung — kalau
        // tidak disamakan, ini jadi celah untuk melewati kewajiban
        // password lama yang baru dipasang di jalur edit_field di atas).
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

        // PERBAIKAN (revisi 27 Agustus 2026, poin 2 — lanjutan): kalau
        // fallback form ini dipakai selagi masih must_change_password
        // (lolos guard umum di atas karena field 'password' ikut diisi),
        // upload foto dilewati sama sekali — tidak disimpan ke disk sama
        // sekali, bukan cuma dibuang dari $data — dan field lain yang
        // ikut terselip di request yang sama (mis. jenis_kelamin, alamat)
        // juga diabaikan. Satu-satunya perubahan yang benar-benar
        // diproses tetap penggantian password itu sendiri, sama seperti
        // pembatasan yang sudah dipasang di Api\ProfileController@update.
        if ($siswa->must_change_password) {
            $data = array_intersect_key($data, ['password' => true]);
        } elseif ($request->hasFile('foto')) {
            if ($siswa->foto_profile) {
                Storage::disk('public')->delete($siswa->foto_profile);
            }
            $data['foto_profile'] = $request->file('foto')->store('siswa', 'public');
        }
        unset($data['foto']);

        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            // PERBAIKAN (revisi 25 Agustus 2026, poin 11): jalur ini hanya
            // pernah dipakai siswa sendiri (route profil ini ada di bawah
            // 'role:siswa'), jadi mengisi password di sini selalu berarti
            // siswa mengganti password-nya sendiri — bebaskan dari
            // kewajiban ganti password.
            $data['must_change_password'] = false;
        }

        $siswa->update($data);

        if (array_key_exists('must_change_password', $data)) {
            Session::put('auth_user', array_merge(Session::get('auth_user', []), [
                'must_change_password' => false,
            ]));
        }

        // PERBAIKAN (revisi 27 Agustus 2026, poin 2): sama seperti pada
        // jalur edit_field 'password' di atas — kalau field password
        // ikut terisi di sini, password_version baru saja naik lewat
        // update() di atas, jadi baseline session harus disinkronkan
        // supaya RoleAuth tidak memaksa siswa logout dari sesi yang baru
        // saja ia pakai untuk mengganti password-nya sendiri.
        if (array_key_exists('password', $data)) {
            Session::put('auth_password_version', (int) $siswa->password_version);
        }

        Session::put('auth_user', array_merge(Session::get('auth_user', []), [
            'nama' => $siswa->nama,
            'foto' => $siswa->foto_profile,
        ]));

        return back()->with('success', 'Profil diperbarui.');
    }
}
