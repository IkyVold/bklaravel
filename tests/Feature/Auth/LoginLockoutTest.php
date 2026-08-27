<?php

namespace Tests\Feature\Auth;

use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Menutup poin revisi: dulu locked_until hanya dicek di Api\AuthController,
 * sehingga akun yang "terkunci" lewat API masih bisa dicoba lewat web.
 * Sekarang AuthenticationService dipakai bersama oleh kedua jalur.
 */
class LoginLockoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_akun_terkunci_lewat_percobaan_gagal_di_api_juga_ditolak_di_web(): void
    {
        $siswa = Siswa::factory()->create(['password' => 'password-benar']);

        // Gagal login berkali-kali lewat API sampai terkunci (ambang
        // terendah PROGRESSIVE_LOCK_TIERS: 5x gagal -> kunci 1 menit).
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', [
                'role' => 'siswa',
                'nis' => $siswa->nis,
                'password' => 'password-salah',
            ]);
        }

        $siswa->refresh();
        $this->assertNotNull($siswa->locked_until, 'Akun seharusnya sudah terkunci setelah 5x gagal.');

        // Walau sekarang mencoba password yang BENAR lewat WEB, akun yang
        // terkunci lewat API harus tetap ditolak di web.
        $response = $this->post(route('login.submit'), [
            'role' => 'siswa',
            'nis' => $siswa->nis,
            'password' => 'password-benar',
        ]);

        $response->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_login_berhasil_mereset_hitungan_gagal(): void
    {
        $siswa = Siswa::factory()->create(['password' => 'password-benar']);

        $this->postJson('/api/login', [
            'role' => 'siswa',
            'nis' => $siswa->nis,
            'password' => 'salah',
        ]);
        $this->assertSame(1, $siswa->fresh()->failed_login_attempts);

        $this->postJson('/api/login', [
            'role' => 'siswa',
            'nis' => $siswa->nis,
            'password' => 'password-benar',
        ])->assertOk();

        $this->assertSame(0, $siswa->fresh()->failed_login_attempts);
        $this->assertNull($siswa->fresh()->locked_until);
    }
}
