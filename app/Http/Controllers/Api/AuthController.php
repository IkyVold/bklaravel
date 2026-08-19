<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\GuruBk;
use App\Models\Kepsek;
use App\Models\Siswa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    private const MAX_FAILED = 5;
    private const LOCK_HOURS = 24;

    public function login(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'role' => 'required|in:siswa,guru,kepsek,admin',
            'password' => 'required|string',
        ]);

        if ($v->fails()) {
            return response()->json(['success' => false, 'message' => $v->errors()->first()], 400);
        }

        $role = $request->input('role');
        $password = $request->input('password');

        return match ($role) {
            'siswa' => $this->loginSiswa($request->input('nis'), $password),
            'guru' => $this->loginStaff(GuruBk::class, $request->input('username'), $password, 'guru'),
            'kepsek' => $this->loginStaff(Kepsek::class, $request->input('username'), $password, 'kepsek'),
            'admin' => $this->loginStaff(Admin::class, $request->input('username'), $password, 'admin'),
            default => response()->json(['success' => false, 'message' => 'Role tidak valid'], 400),
        };
    }

    private function loginSiswa(?string $nis, string $password): JsonResponse
    {
        if (!$nis) {
            return response()->json(['success' => false, 'message' => 'NIS dan password harus diisi'], 400);
        }

        $siswa = Siswa::where('nis', $nis)->first();
        if (!$siswa) {
            return response()->json(['success' => false, 'message' => 'NIS atau password salah'], 401);
        }

        if ($siswa->locked_until && $siswa->locked_until->isFuture()) {
            $jam = $siswa->locked_until->timezone('Asia/Jakarta')->format('d M Y H:i');
            return response()->json([
                'success' => false,
                'message' => "Akun terkunci karena terlalu banyak percobaan login gagal. Coba lagi setelah {$jam} WIB.",
            ], 423);
        }

        if (!$siswa->verifyPassword($password)) {
            $siswa->increment('failed_login_attempts');
            if ($siswa->failed_login_attempts >= self::MAX_FAILED) {
                $siswa->update([
                    'locked_until' => now()->addHours(self::LOCK_HOURS),
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Akun dikunci selama 1 hari karena 5 kali login gagal. Hubungi Guru BK jika ini bukan Anda.',
                ], 423);
            }
            return response()->json(['success' => false, 'message' => 'NIS atau password salah'], 401);
        }

        $siswa->update(['failed_login_attempts' => 0, 'locked_until' => null]);

        $token = $siswa->createToken('siswa-token', ['siswa'])->plainTextToken;

        return response()->json([
            'success' => true,
            'token' => $token,
            'role' => 'siswa',
            'user' => [
                'id' => $siswa->id,
                'nis' => $siswa->nis,
                'nama' => $siswa->nama,
                'kelas' => $siswa->kelas,
                'foto_profile' => $siswa->foto_profile,
            ],
        ]);
    }

    private function loginStaff(string $model, ?string $username, string $password, string $role): JsonResponse
    {
        if (!$username) {
            return response()->json(['success' => false, 'message' => 'Username dan password harus diisi'], 400);
        }

        $user = $model::where('username', $username)->first();
        if (!$user || !$user->is_active || !$user->verifyPassword($password)) {
            return response()->json(['success' => false, 'message' => 'Username atau password salah'], 401);
        }

        $token = $user->createToken("{$role}-token", [$role])->plainTextToken;

        $payload = [
            'id' => $user->id,
            'username' => $user->username,
            'nama' => $user->nama,
        ];

        if ($role === 'guru') {
            $payload['spesialisasi'] = $user->spesialisasi;
            $payload['foto_profile'] = $user->foto_profile;
            $payload['avatar'] = $user->avatar;
        }

        return response()->json([
            'success' => true,
            'token' => $token,
            'role' => $role,
            'user' => $payload,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();
        return response()->json(['success' => true, 'message' => 'Logout berhasil']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $role = $user->currentAccessToken()?->abilities[0] ?? 'unknown';

        return response()->json([
            'success' => true,
            'role' => $role,
            'user' => $user,
        ]);
    }
}
