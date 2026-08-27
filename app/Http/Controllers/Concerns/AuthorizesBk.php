<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

trait AuthorizesBk
{
    /**
     * Daftar role/ability yang dikenal sistem. Setiap token diterbitkan
     * dengan TEPAT SATU ability yang namanya sama dengan role (lihat
     * AuthController::login). Urutan di sini menentukan prioritas saat
     * mendeteksi role dari token.
     */
    private const KNOWN_ROLES = ['admin', 'kepsek', 'guru', 'siswa'];

    /**
     * PENTING: jangan baca $token->abilities sebagai array mentah.
     * Pada token asli (PersonalAccessToken) itu memang berfungsi, tapi
     * Sanctum::actingAs() (dipakai di seluruh test suite) membuat MOCK
     * token yang hanya men-stub method can($ability) — properti
     * ->abilities pada mock tersebut tidak pernah terisi, sehingga akan
     * selalu bernilai null dan setiap pengecekan role akan gagal secara
     * diam-diam (selalu dianggap tidak berrole apa pun). Gunakan
     * tokenCan()/can() yang bekerja konsisten baik untuk token asli
     * maupun token hasil Sanctum::actingAs().
     */
    protected function currentRole(Request $request): ?string
    {
        $user = $request->user();
        if (!$user) {
            return null;
        }

        foreach (self::KNOWN_ROLES as $role) {
            if ($user->tokenCan($role)) {
                return $role;
            }
        }

        return null;
    }

    protected function isRole(Request $request, string ...$roles): bool
    {
        $role = $this->currentRole($request);
        return $role && in_array($role, $roles, true);
    }

    protected function isSiswa(Request $request): bool
    {
        return $this->isRole($request, 'siswa');
    }

    protected function isGuru(Request $request): bool
    {
        return $this->isRole($request, 'guru');
    }

    protected function isKepsek(Request $request): bool
    {
        return $this->isRole($request, 'kepsek');
    }

    protected function isAdmin(Request $request): bool
    {
        return $this->isRole($request, 'admin');
    }

    protected function isStaff(Request $request): bool
    {
        return $this->isRole($request, 'guru', 'kepsek', 'admin');
    }

    /** Siswa hanya boleh akses datanya sendiri; staff boleh lebih luas. */
    protected function assertSiswaOwns(Request $request, $siswaOrId): void
    {
        if ($this->isStaff($request)) {
            return;
        }
        $user = $request->user();
        $id = is_object($siswaOrId) ? $siswaOrId->id : (int) $siswaOrId;
        if (!$user || (int) $user->id !== $id) {
            abort(response()->json(['success' => false, 'message' => 'Akses ditolak'], 403));
        }
    }

    protected function assertSiswaOwnsNis(Request $request, string $nis): void
    {
        if ($this->isStaff($request)) {
            return;
        }
        $user = $request->user();
        if (!$user || (string) ($user->nis ?? '') !== (string) $nis) {
            abort(response()->json(['success' => false, 'message' => 'Akses ditolak'], 403));
        }
    }

    /**
     * Hak MELIHAT saja. Admin & Kepsek boleh (monitoring), Guru BK pemilik boleh,
     * Siswa pemilik boleh. Tidak memberi hak mengubah data.
     *
     * PENTING: fungsi ini HANYA untuk melihat (baca data konseling, riwayat
     * chat untuk keperluan monitoring, dsb). JANGAN dipakai untuk
     * mengizinkan aksi menulis/berpartisipasi seperti mengirim pesan chat —
     * lihat assertCanChatKonseling() untuk itu. Sebelumnya
     * ChatController@send salah memakai fungsi ini, sehingga Admin/Kepsek
     * yang seharusnya cuma boleh MELIHAT ikut bisa MENGIRIM pesan chat
     * konseling (revisi 24 Agustus 2026, poin 2).
     *
     * CATATAN (revisi 25 Agustus 2026, poin 3): fungsi ini hanya gerbang
     * OTORISASI (boleh akses endpoint atau tidak) — bukan penentu FIELD
     * apa saja yang dikembalikan. Untuk data konseling itu sendiri, Admin
     * & Kepsek yang lolos di sini tetap disaring lebih lanjut di
     * controller (lihat Konseling::untukMonitoringKepsek() dan
     * pemakaiannya di getDetail()/listAll()/listBySiswa()) supaya isi
     * konsultasi (deskripsi/kesimpulan/rekomendasi/catatan) hanya
     * diterima siswa & Guru BK yang bersangkutan, bukan Admin/Kepsek.
     */
    protected function assertCanViewKonseling(Request $request, $konseling): void
    {
        if ($this->isRole($request, 'admin', 'kepsek')) {
            return;
        }
        if ($this->isGuru($request) && $this->guruOwnsKonseling($request, $konseling)) {
            return;
        }
        if ($this->isSiswa($request)) {
            $user = $request->user();
            if ((int) $konseling->siswa_id === (int) $user->id) {
                return;
            }
        }
        abort(response()->json(['success' => false, 'message' => 'Akses ditolak'], 403));
    }

    /**
     * Hak IKUT SERTA di chat konseling (kirim pesan). BEDA dengan
     * assertCanViewKonseling(): Admin & Kepsek boleh melihat data
     * konseling untuk monitoring, tapi mereka BUKAN peserta sesi
     * konseling seorang siswa dengan Guru BK-nya, jadi tidak boleh
     * mengirim pesan di dalamnya. Hanya siswa pemilik dan Guru BK
     * pemilik yang boleh berpartisipasi.
     */
    protected function assertCanChatKonseling(Request $request, $konseling): void
    {
        if ($this->isGuru($request) && $this->guruOwnsKonseling($request, $konseling)) {
            return;
        }
        if ($this->isSiswa($request)) {
            $user = $request->user();
            if ((int) $konseling->siswa_id === (int) $user->id) {
                return;
            }
        }
        abort(response()->json(['success' => false, 'message' => 'Akses ditolak'], 403));
    }

    /**
     * Hak MEMBACA isi/riwayat chat konseling. BEDA dengan
     * assertCanViewKonseling(): assertCanViewKonseling() sengaja
     * meloloskan Admin & Kepsek untuk keperluan monitoring data
     * administratif konseling (jadwal, status, dsb) — tapi ISI CHAT
     * adalah bagian dari isi konsultasi yang menurut halaman
     * siswa/konseling-pilih.blade.php hanya boleh dilihat siswa dan
     * Guru BK yang dipilih.
     *
     * PERBAIKAN (revisi 25 Agustus 2026, poin 2): sebelumnya
     * ChatController@history memakai assertCanViewKonseling(), sehingga
     * walau Admin/Kepsek sudah tidak bisa MENGIRIM pesan (lihat
     * assertCanChatKonseling(), revisi 24 Agustus 2026 poin 2), mereka
     * tetap bisa MEMBACA seluruh isi chat lewat endpoint history.
     * Aturannya sama persis dengan assertCanChatKonseling() (hanya
     * siswa pemilik & Guru BK pemilik) — dibuat sebagai fungsi terpisah
     * supaya namanya tetap jelas menggambarkan maksud pemanggilan di
     * setiap controller (baca vs kirim), bukan karena aturannya beda.
     */
    protected function assertCanReadChatKonseling(Request $request, $konseling): void
    {
        $this->assertCanChatKonseling($request, $konseling);
    }

    /**
     * Hak MENGUBAH (konfirmasi, laporan, ubah status). HANYA Guru BK
     * pemilik konsultasi. Kepsek TIDAK boleh (hanya monitoring di web).
     * Siswa TIDAK PERNAH lolos di sini, walaupun ia pemilik konsultasi.
     *
     * PERBAIKAN (revisi 26 Agustus 2026, poin 1): sebelumnya Admin selalu
     * diloloskan di sini (`isRole($request, 'admin')`), padahal saat
     * MEMBACA konsultasi (assertCanViewKonseling + penyaringan field di
     * controller) isi konsultasi sudah sengaja disensor dari Admin demi
     * kerahasiaan. Timpang: Admin tidak boleh membaca isi konsultasi,
     * tapi boleh mengonfirmasi/membuat laporan/mengubah statusnya. Kalau
     * peran Admin memang hanya pengelola user, akun, dan konfigurasi
     * sistem, ia tidak semestinya punya wewenang mengelola konten
     * konseling. Sekarang HANYA Guru BK pemilik yang lolos; kalau ke
     * depan Admin benar-benar perlu melakukan tindakan administratif atas
     * konsultasi (mis. override darurat), sediakan endpoint administratif
     * terpisah yang eksplisit dan tercatat, bukan menumpang di jalur ini.
     */
    protected function assertGuruCanManageKonseling(Request $request, $konseling): void
    {
        if ($this->isGuru($request) && $this->guruOwnsKonseling($request, $konseling)) {
            return;
        }
        abort(response()->json(['success' => false, 'message' => 'Akses ditolak'], 403));
    }

    /**
     * PERBAIKAN (revisi 24 Agustus 2026, poin 8): dulu ownership diperiksa
     * dengan guru_id COCOK ATAU nama COCOK — walau konseling sudah punya
     * guru_id yang menunjuk Guru BK tertentu, sistem tetap mencoba
     * mencocokkan nama sebagai fallback. Nama BUKAN identifier unik: jika
     * ada dua Guru BK dengan nama sama persis dan konseling ini sebenarnya
     * milik Guru A (guru_id = id Guru A), Guru B bisa ikut lolos ownership
     * check hanya karena namanya kebetulan sama.
     *
     * Sekarang: begitu konseling punya guru_id, itu SATU-SATUNYA sumber
     * kebenaran — fallback nama HANYA dipakai untuk data lama yang memang
     * belum punya guru_id sama sekali (guru_id null), bukan dipakai
     * bersamaan/menggantikan guru_id yang sudah ada tapi tidak cocok.
     */
    private function guruOwnsKonseling(Request $request, $konseling): bool
    {
        $user = $request->user();

        if (!is_null($konseling->guru_id)) {
            return (int) $konseling->guru_id === (int) $user->id;
        }

        // Data lama sebelum kolom guru_id ada — satu-satunya kasus fallback
        // nama sah dipakai.
        $nama = $user->nama ?? '';
        return $nama !== '' && strcasecmp((string) $konseling->guru_bk, $nama) === 0;
    }

    /**
     * PERBAIKAN (revisi 26 Agustus 2026, poin 4): Guru BK sebelumnya bisa
     * mengubah/menghapus informasi_bk milik Guru BK lain karena
     * update()/remove() hanya memeriksa role, bukan kepemilikan. Pola
     * ownership di sini SENGAJA disamakan persis dengan
     * guruOwnsKonseling(): guru_id, kalau ada, SATU-SATUNYA sumber
     * kebenaran; fallback nama HANYA untuk baris lama yang guru_id-nya
     * masih null (belum ter-backfill migration).
     *
     * Admin BOLEH mengelola semua informasi (audit trail tetap tercatat
     * lewat kolom updated_at/who pada log aplikasi, bukan dibatasi di
     * sini) — dipanggil terpisah lewat assertGuruCanManageInformasi().
     */
    protected function informasiOwnedByGuru(Request $request, $informasi): bool
    {
        $user = $request->user();

        if (!is_null($informasi->guru_id)) {
            return (int) $informasi->guru_id === (int) $user->id;
        }

        $nama = $user->nama ?? '';
        return $nama !== '' && strcasecmp((string) $informasi->guru_bk, $nama) === 0;
    }

    /**
     * Hak MENGUBAH/MENGHAPUS informasi_bk. Admin boleh mengelola semua
     * (dengan audit trail). Guru BK hanya boleh mengelola informasi
     * miliknya sendiri — lihat informasiOwnedByGuru().
     */
    protected function assertGuruCanManageInformasi(Request $request, $informasi): void
    {
        if ($this->isRole($request, 'admin')) {
            return;
        }
        if ($this->isGuru($request) && $this->informasiOwnedByGuru($request, $informasi)) {
            return;
        }
        abort(response()->json(['success' => false, 'message' => 'Akses ditolak: informasi ini milik Guru BK lain'], 403));
    }
}
