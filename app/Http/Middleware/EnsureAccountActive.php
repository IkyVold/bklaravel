<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * PERBAIKAN (revisi 26 Agustus 2026, poin 3): menonaktifkan akun Guru BK /
 * Kepsek / Admin (AkunController@deleteGuru, @deleteKepsek, dst.) hanya
 * mengubah kolom is_active menjadi false. Token Sanctum yang sudah
 * diterbitkan untuk akun tersebut TIDAK ikut dihapus, dan sebelum
 * middleware ini ditambahkan, auth:sanctum hanya memeriksa apakah token
 * itu valid (belum dihapus) — bukan apakah pemilik token masih aktif.
 * Akibatnya akun yang baru saja dinonaktifkan tetap bisa dipakai penuh
 * lewat API selama token lamanya belum dihapus/kedaluwarsa sendiri.
 *
 * Middleware ini dipasang setelah 'auth:sanctum' pada rute terproteksi dan
 * memvalidasi ULANG status is_active pemilik token pada SETIAP request —
 * bukan hanya saat token diterbitkan. Kalau ternyata sudah tidak aktif,
 * token yang dipakai (dan seluruh token lain milik akun tsb, untuk jaga2)
 * langsung dihapus di sini juga, supaya percobaan berikutnya benar2 gagal
 * di tahap auth:sanctum, bukan cuma ditolak middleware ini berulang kali.
 *
 * Siswa TIDAK punya kolom is_active sama sekali (lihat migration), jadi
 * pemeriksaan ini otomatis dilewati untuk model yang tidak memilikinya.
 */
class EnsureAccountActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && array_key_exists('is_active', $user->getAttributes()) && !$user->is_active) {
            // Bersihkan seluruh token milik akun ini supaya percobaan
            // berikutnya ditolak lebih awal oleh auth:sanctum sendiri.
            if (method_exists($user, 'tokens')) {
                $user->tokens()->delete();
            }

            return response()->json([
                'success' => false,
                'message' => 'Akun ini sudah dinonaktifkan.',
            ], 403);
        }

        return $next($request);
    }
}
