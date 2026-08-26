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

    protected function assertCanReadChatKonseling(Request $request, $konseling): void
    {
        $this->assertCanChatKonseling($request, $konseling);
    }

    /**
     * Hak MENGUBAH (konfirmasi, laporan, ubah status). Hanya Guru BK pemilik
     * konsultasi, atau Admin. Kepsek TIDAK boleh (hanya monitoring di web).
     * Siswa TIDAK PERNAH lolos di sini, walaupun ia pemilik konsultasi.
     */
    protected function assertGuruCanManageKonseling(Request $request, $konseling): void
    {
        if ($this->isRole($request, 'admin')) {
            return;
        }
        if ($this->isGuru($request) && $this->guruOwnsKonseling($request, $konseling)) {
            return;
        }
        abort(response()->json(['success' => false, 'message' => 'Akses ditolak'], 403));
    }

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
