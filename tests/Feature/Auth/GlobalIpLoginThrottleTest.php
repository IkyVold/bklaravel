<?php

namespace Tests\Feature\Auth;

use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalIpLoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_mengganti_nis_setiap_percobaan_tetap_dibatasi_oleh_limiter_ip(): void
    {
        // Buat 25 siswa dengan NIS berbeda-beda, semua dicoba dari IP yang
        // sama dengan password salah. Limiter per-akun (role+nis+ip) tidak
        // akan pernah kena karena tiap NIS hanya dicoba satu kali — tapi
        // limiter global per-IP (ambang 20) harus tetap menghentikan
        // percobaan setelah NIS ke-20.
        $siswas = Siswa::factory()->count(25)->create();

        $blocked = false;
        $blockedAtAttempt = null;

        foreach ($siswas as $i => $siswa) {
            $response = $this->postJson('/api/login', [
                'role' => 'siswa',
                'nis' => $siswa->nis,
                'password' => 'password-salah-pasti',
            ]);

            if ($response->status() === 429) {
                $blocked = true;
                $blockedAtAttempt = $i + 1;
                break;
            }
        }

        $this->assertTrue($blocked, 'Percobaan login dengan NIS yang selalu berbeda dari IP yang sama seharusnya tetap dibatasi.');
        $this->assertLessThanOrEqual(
            21,
            $blockedAtAttempt,
            'Limiter IP (ambang 20) seharusnya sudah menghentikan percobaan paling lambat di percobaan ke-21.'
        );
    }

    public function test_percobaan_login_dalam_batas_wajar_tidak_terkena_limiter_ip(): void
    {
        // Beberapa siswa berbeda dari IP yang sama (mis. satu lab komputer
        // sekolah) dengan jumlah percobaan yang jauh di bawah ambang global
        // tidak boleh ikut terblokir.
        $siswas = Siswa::factory()->count(5)->create(['password' => 'password-benar']);

        foreach ($siswas as $siswa) {
            $this->postJson('/api/login', [
                'role' => 'siswa',
                'nis' => $siswa->nis,
                'password' => 'password-benar',
            ])->assertOk();
        }
    }
}
