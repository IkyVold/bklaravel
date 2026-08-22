<?php

namespace App\Services;

use App\Models\Siswa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Satu-satunya sumber aturan login, lockout, dan rate limiting — dipakai
 * bersama oleh Web\AuthController dan Api\AuthController. Jangan duplikasi
 * logika ini di masing-masing controller; panggil service ini supaya
 * perilaku keamanan login SELALU sama di kedua jalur (akun yang dibatasi
 * lewat API juga otomatis dibatasi lewat web, begitu pula sebaliknya).
 */
class AuthenticationService
{
    /**
     * Burst throttle: percobaan login (identitas + IP) yang diperbolehkan
     * dalam satu jendela waktu singkat, berlaku untuk SEMUA role (siswa,
     * guru, kepsek, admin) di web maupun API. Ini mencegah brute force
     * murni yang sebelumnya bisa lewat tanpa batas di jalur web.
     */
    private const THROTTLE_MAX_ATTEMPTS = 5;
    private const THROTTLE_DECAY_SECONDS = 60; // 1 menit

    /**
     * Lockout progresif KHUSUS akun siswa (kolom failed_login_attempts /
     * locked_until hanya ada di tabel siswa). Ambang dihitung dari jumlah
     * gagal berturut-turut; makin banyak, makin lama kuncinya — tetapi
     * TIDAK langsung 24 jam seperti sebelumnya, supaya orang lain tidak
     * bisa sengaja mengunci akun siswa lain dalam waktu lama hanya dengan
     * berulang kali memasukkan password salah.
     *
     * Format: [ambang_minimum_gagal_berturut2, lama_kunci_menit]
     * Diurutkan dari yang terbesar supaya pencarian tier berhenti di match
     * pertama.
     */
    private const PROGRESSIVE_LOCK_TIERS = [
        [14, 60], // >= 14 kali gagal -> kunci 60 menit
        [11, 15], // >= 11 kali gagal -> kunci 15 menit
        [8, 5],   // >= 8 kali gagal  -> kunci 5 menit
        [5, 1],   // >= 5 kali gagal  -> kunci 1 menit
    ];

    /* ---------------------------------------------------------------
     | Burst throttle (Laravel RateLimiter) — semua role, web & API
     |---------------------------------------------------------------*/

    public function throttleKey(string $role, string $identifier, Request $request): string
    {
        return Str::lower("login:{$role}:{$identifier}:{$request->ip()}");
    }

    public function tooManyAttempts(string $key): bool
    {
        return RateLimiter::tooManyAttempts($key, self::THROTTLE_MAX_ATTEMPTS);
    }

    public function availableIn(string $key): int
    {
        return RateLimiter::availableIn($key);
    }

    public function hitThrottle(string $key): void
    {
        RateLimiter::hit($key, self::THROTTLE_DECAY_SECONDS);
    }

    public function clearThrottle(string $key): void
    {
        RateLimiter::clear($key);
    }

    /* ---------------------------------------------------------------
     | Lockout progresif akun siswa (persist di kolom siswa)
     |---------------------------------------------------------------*/

    public function isSiswaLocked(Siswa $siswa): bool
    {
        return $siswa->locked_until !== null && $siswa->locked_until->isFuture();
    }

    /**
     * Catat satu kegagalan login siswa dan, jika ambang tercapai, kunci
     * akun secara progresif (lihat PROGRESSIVE_LOCK_TIERS).
     */
    public function registerSiswaFailure(Siswa $siswa): void
    {
        $siswa->increment('failed_login_attempts');
        $siswa->refresh();

        $lockMinutes = $this->lockMinutesFor((int) $siswa->failed_login_attempts);
        if ($lockMinutes > 0) {
            $siswa->locked_until = now()->addMinutes($lockMinutes);
            $siswa->save();
        }
    }

    public function resetSiswaAttempts(Siswa $siswa): void
    {
        if ((int) $siswa->failed_login_attempts !== 0 || $siswa->locked_until !== null) {
            $siswa->failed_login_attempts = 0;
            $siswa->locked_until = null;
            $siswa->save();
        }
    }

    private function lockMinutesFor(int $failedAttempts): int
    {
        foreach (self::PROGRESSIVE_LOCK_TIERS as [$threshold, $minutes]) {
            if ($failedAttempts >= $threshold) {
                return $minutes;
            }
        }
        return 0;
    }
}
