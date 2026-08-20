<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

trait AuthorizesBk
{
    protected function tokenAbilities(Request $request): array
    {
        $token = $request->user()?->currentAccessToken();
        return $token?->abilities ?? [];
    }

    protected function currentRole(Request $request): ?string
    {
        $abilities = $this->tokenAbilities($request);
        return $abilities[0] ?? null;
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

    protected function assertGuruOwnsKonseling(Request $request, $konseling): void
    {
        if ($this->isRole($request, 'admin', 'kepsek')) {
            return;
        }
        if ($this->isGuru($request)) {
            $user = $request->user();
            $nama = $user->nama ?? '';
            if ((int) ($konseling->guru_id ?? 0) === (int) $user->id || strcasecmp((string) $konseling->guru_bk, $nama) === 0) {
                return;
            }
        }
        if ($this->isSiswa($request)) {
            $user = $request->user();
            if ((int) $konseling->siswa_id === (int) $user->id) {
                return;
            }
        }
        abort(response()->json(['success' => false, 'message' => 'Akses ditolak'], 403));
    }
}
