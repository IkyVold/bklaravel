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
        // PERBAIKAN (revisi 25 Agustus 2026, poin 10): dulu di sini hanya
        // dipakai assertSiswaOwnsNis(), yang meloloskan SELURUH staff
        // (Guru BK, Kepsek, Admin) melewati pengecekan kepemilikan NIS —
        // lalu $rules di bawah membuka jenis_kelamin/tanggal_lahir/
        // alamat/no_telepon (dan kelas untuk staff) bagi siapa pun yang
        // lolos di situ. Akibatnya Guru BK maupun Kepala Sekolah bisa
        // mengubah profil siswa MANA PUN lewat API, padahal:
        //  - Guru BK menurut hasil review hanya perlu hak baca (plus data
        //    administratif tertentu BILA memang diberi kewenangan — belum
        //    ada mekanisme kewenangan semacam itu, jadi defaultnya baca
        //    saja untuk sekarang).
        //  - Kepala Sekolah eksplisit read-only; tidak ada alasan proses
        //    bisnis untuk kepsek mengubah kelas/data siswa.
        //  - Admin adalah pengelola master akademik, jadi tetap boleh
        //    mengubah field administratif siswa mana pun.
        // Sekarang: siswa hanya boleh mengubah profilnya sendiri (dicek
        // via assertSiswaOwnsNis(), yang bagi non-staff berarti wajib
        // pemilik NIS); Admin boleh mengubah profil siswa mana pun; Guru
        // BK & Kepsek ditolak total di endpoint tulis ini — mereka tetap
        // bisa membaca lewat get().
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

        // PERBAIKAN (revisi 24 Agustus 2026, poin 1): 'password' SENGAJA
        // tidak dibuka untuk seluruh staff. Guru BK/Kepsek sudah ditolak
        // total di atas (poin 10), jadi baris ini sekarang hanya relevan
        // untuk siswa sendiri atau Admin. Reset password siswa hanya
        // boleh dilakukan oleh siswa itu sendiri (ganti password sendiri)
        // atau Admin (mengelola akun siswa) — sama seperti pembatasan
        // create siswa yang sudah 'ability:admin' di routes/api.php.
        if ($this->isSiswa($request) || $this->isAdmin($request)) {
            $rules['password'] = 'nullable|string|min:6';
        }

        // PERBAIKAN (revisi 25 Agustus 2026, poin 13): dulu siswa bisa
        // mengganti password sendiri tanpa diminta password lama sama
        // sekali. Kalau session/token siswa berhasil diambil orang lain,
        // attacker bisa langsung ganti password dan mengunci pemilik asli
        // dari akunnya sendiri. Sekarang khusus jalur SISWA (bukan Admin
        // yang mereset), 'current_password' wajib diisi setiap kali field
        // 'password' dikirim, dan divalidasi cocok dengan password yang
        // tersimpan SEBELUM password baru disimpan. Admin sengaja
        // dikecualikan — proses reset oleh Admin memang tidak mengetahui
        // password lama siswa (lihat poin 13 pada revisi).
        if ($this->isSiswa($request)) {
            $rules['current_password'] = 'required_with:password|string';
        }

        $v = Validator::make($request->all(), $rules);
        if ($v->fails()) {
            return response()->json(['success' => false, 'message' => $v->errors()->first()], 400);
        }

        $data = $v->validated();

        // PERBAIKAN (revisi 27 Agustus 2026, poin 2 — lanjutan, hasil
        // review dosen penguji): EnsurePasswordChanged mengecualikan
        // ProfileController@update dari gate 423 supaya siswa yang
        // must_change_password=true tetap punya jalan untuk mematuhi
        // kewajiban itu (ganti password sendiri). Tapi sebelumnya
        // endpoint ini sendiri tidak membedakan "permintaan ganti
        // password" dari permintaan lain — selama must_change_password
        // masih true, TOKEN APA PUN yang masih bisa memanggil endpoint
        // ini (termasuk token attacker yang sudah membajak akun sebelum
        // Admin mereset password) tetap bebas mengubah jenis_kelamin,
        // tanggal_lahir, alamat, atau no_telepon TANPA pernah benar-
        // benar mengganti password, membuat kewajiban ganti password
        // jadi gate yang bisa dilewati begitu saja.
        //
        // Sekarang, selama siswa masih wajib ganti password (dibaca dari
        // kolom di database, BUKAN snapshot), endpoint ini:
        //   1. Menolak (423, pesan & shape sama dengan EnsurePasswordChanged
        //      supaya konsisten bagi klien) kalau field 'password' tidak
        //      ikut dikirim sama sekali — field lain saja tidak cukup.
        //   2. Kalau 'password' dikirim, field-field LAIN yang ikut
        //      terselip di request yang sama diabaikan sepenuhnya (tidak
        //      disimpan diam-diam) — satu-satunya perubahan yang diproses
        //      adalah penggantian password itu sendiri. Ini mencegah
        //      "menumpangkan" perubahan data lain di balik kewajiban
        //      ganti password.
        // Admin TIDAK terpengaruh pembatasan ini — Admin mereset password
        // siswa lain, bukan mengubah profilnya sendiri, jadi
        // must_change_password di sini selalu merujuk ke akun siswa yang
        // sedang login sendiri.
        if ($this->isSiswa($request) && $siswa->must_change_password) {
            if (empty($data['password'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda wajib mengganti password default terlebih dahulu. Gunakan PUT /api/profile/{nis} dengan field password untuk menggantinya.',
                    'must_change_password' => true,
                ], 423);
            }
            $data = array_intersect_key($data, array_flip(['password', 'current_password']));
        }

        if ($this->isSiswa($request) && !empty($data['password'])) {
            if (!$siswa->verifyPassword($data['current_password'])) {
                return response()->json(['success' => false, 'message' => 'Password saat ini tidak sesuai'], 400);
            }
        }
        unset($data['current_password']);

        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            // PERBAIKAN (revisi 25 Agustus 2026, poin 11): kalau siswa
            // sendiri yang mengganti password (bukan Admin), itu sudah
            // password pilihannya sendiri — bebaskan dari kewajiban ganti
            // password. Kalau Admin yang mereset, password baru itu tetap
            // ditentukan orang lain, jadi tetap wajib diganti lagi saat
            // siswa login berikutnya.
            $data['must_change_password'] = !$this->isSiswa($request);
        }

        // PERBAIKAN (revisi 27 Agustus 2026, poin 2): dicatat SEBELUM
        // fill()->save() supaya tidak terpengaruh oleh unset('password')
        // di atas untuk kasus password kosong.
        $passwordChanged = array_key_exists('password', $data);

        $siswa->fill($data)->save();

        // PERBAIKAN (revisi 27 Agustus 2026, poin 2): sebelumnya baris di
        // atas ini adalah SATU-SATUNYA hal yang terjadi saat Admin
        // mereset password siswa — token Sanctum yang sudah diterbitkan
        // untuk siswa ini (termasuk milik attacker yang mungkin sudah
        // membajak akun sebelum reset ini terjadi) tetap berlaku penuh
        // sampai kedaluwarsa sendiri. Sekarang, persis pola yang sudah
        // dipakai Api\AkunController@updateGuru/@updateKepsek untuk
        // staff: begitu password BENAR-BENAR berubah lewat endpoint ini
        // DAN yang mereset adalah Admin (bukan siswa mengganti password
        // sendiri — siswa tidak perlu "mencabut token miliknya sendiri"
        // yang sedang ia pakai untuk request ini), seluruh token lama
        // siswa dicabut. Token baru akan diterbitkan lagi saat siswa
        // login ulang.
        if ($passwordChanged && $this->isAdmin($request)) {
            $siswa->tokens()->delete();
        }

        // PERBAIKAN BUG (ditemukan saat menjalankan test poin 10): baris
        // ini sebelumnya memanggil $siswa->fresh([...]) dengan daftar NAMA
        // KOLOM ('id', 'nis', dst). Model::fresh() TIDAK menerima daftar
        // kolom — argumennya adalah daftar RELASI yang mau di-eager-load
        // (sama seperti with()). Karena 'id' bukan nama relasi pada model
        // Siswa, Eloquent mencoba memanggil method id() sebagai relasi dan
        // melempar RelationNotFoundException (500) setiap kali baris ini
        // dieksekusi — sebelumnya tidak pernah ketahuan karena belum ada
        // test yang benar-benar sampai ke titik ini dengan assertOk().
        // Perbaikannya: reload penuh via refresh(), lalu pilih kolom yang
        // aman ditampilkan dengan only() di level array PHP, bukan lewat
        // argumen fresh()/with().
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
        // PERBAIKAN (revisi 25 Agustus 2026, poin 10): sama seperti
        // update() di atas — dulu Guru BK maupun Kepala Sekolah bisa
        // mengganti foto siswa mana pun karena assertSiswaOwnsNis()
        // meloloskan seluruh staff. Review secara eksplisit menyebut
        // tidak ada alasan proses bisnis bagi Kepsek untuk mengubah foto
        // siswa; Guru BK juga belum punya kewenangan khusus untuk itu.
        // Hanya siswa pemilik dan Admin yang boleh mengganti foto.
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
        // PERBAIKAN (revisi 25 Agustus 2026, poin 10): lihat penjelasan di
        // updateFoto()/update() — hapus foto disamakan dengan ubah foto,
        // Guru BK & Kepsek ditolak, hanya siswa pemilik & Admin yang boleh.
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
