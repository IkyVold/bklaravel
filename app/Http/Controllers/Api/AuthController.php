<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\GuruBk;
use App\Models\Kepsek;
use App\Models\Siswa;
use App\Services\AuthenticationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function __construct(private AuthenticationService $auth)
    {
    }

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
            'siswa' => $this->loginSiswa($request, $request->input('nis'), $password),
            'guru' => $this->loginStaff($request, GuruBk::class, $request->input('username'), $password, 'guru'),
            'kepsek' => $this->loginStaff($request, Kepsek::class, $request->input('username'), $password, 'kepsek'),
            'admin' => $this->loginStaff($request, Admin::class, $request->input('username'), $password, 'admin'),
            default => response()->json(['success' => false, 'message' => 'Role tidak valid'], 400),
        };
    }

    private function loginSiswa(Request $request, ?string $nis, string $password): JsonResponse
    {
        if (!$nis) {
            return response()->json(['success' => false, 'message' => 'NIS dan password harus diisi'], 400);
        }

        // Burst throttle — sama dengan jalur web, berlaku sebelum apa pun
        // di-query supaya percobaan yang sangat cepat/berulang tetap
        // dibatasi walau NIS-nya berbeda-beda.
        $throttleKey = $this->auth->throttleKey('siswa', $nis, $request);
        if ($this->auth->tooManyAttempts($throttleKey)) {
            $seconds = $this->auth->availableIn($throttleKey);
            return response()->json([
                'success' => false,
                'message' => "Terlalu banyak percobaan login. Coba lagi dalam {$seconds} detik.",
            ], 429);
        }

        $siswa = Siswa::where('nis', $nis)->first();
        if (!$siswa) {
            $this->auth->hitThrottle($throttleKey);
            return response()->json(['success' => false, 'message' => 'NIS atau password salah'], 401);
        }

        if ($this->auth->isSiswaLocked($siswa)) {
            $jam = $siswa->locked_until->timezone('Asia/Jakarta')->format('d M Y H:i');
            return response()->json([
                'success' => false,
                'message' => "Akun terkunci sementara karena terlalu banyak percobaan login gagal. Coba lagi setelah {$jam} WIB.",
            ], 423);
        }

        if (!$siswa->verifyPassword($password)) {
            $this->auth->hitThrottle($throttleKey);
            $this->auth->registerSiswaFailure($siswa);

            if ($this->auth->isSiswaLocked($siswa)) {
                $jam = $siswa->locked_until->timezone('Asia/Jakarta')->format('d M Y H:i');
                return response()->json([
                    'success' => false,
                    'message' => "Akun dikunci sementara karena beberapa kali login gagal. Coba lagi setelah {$jam} WIB.",
                ], 423);
            }

            return response()->json(['success' => false, 'message' => 'NIS atau password salah'], 401);
        }

        $this->auth->clearThrottle($throttleKey);
        $this->auth->resetSiswaAttempts($siswa);

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

    private function loginStaff(Request $request, string $model, ?string $username, string $password, string $role): JsonResponse
    {
        if (!$username) {
            return response()->json(['success' => false, 'message' => 'Username dan password harus diisi'], 400);
        }

        // Guru/Kepsek/Admin belum punya kolom lockout persisten, tetapi
        // tetap dilindungi burst throttle yang sama dengan siswa & web,
        // supaya jalur ini juga tidak bebas dipakai brute force.
        $throttleKey = $this->auth->throttleKey($role, $username, $request);
        if ($this->auth->tooManyAttempts($throttleKey)) {
            $seconds = $this->auth->availableIn($throttleKey);
            return response()->json([
                'success' => false,
                'message' => "Terlalu banyak percobaan login. Coba lagi dalam {$seconds} detik.",
            ], 429);
        }

        $user = $model::where('username', $username)->first();
        if (!$user || !$user->is_active || !$user->verifyPassword($password)) {
            $this->auth->hitThrottle($throttleKey);
            return response()->json(['success' => false, 'message' => 'Username atau password salah'], 401);
        }

        $this->auth->clearThrottle($throttleKey);

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

        // Jangan baca ->abilities sebagai array mentah: pada token mock dari
        // Sanctum::actingAs() (dipakai di test) properti itu tidak pernah
        // terisi. tokenCan() bekerja konsisten untuk token asli maupun mock.
        $role = 'unknown';
        foreach (['admin', 'kepsek', 'guru', 'siswa'] as $candidate) {
            if ($user->tokenCan($candidate)) {
                $role = $candidate;
                break;
            }
        }

        return response()->json([
            'success' => true,
            'role' => $role,
            'user' => $user,
        ]);
    }
}
