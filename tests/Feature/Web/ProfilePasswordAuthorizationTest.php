<?php

namespace Tests\Feature\Web;

use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Menutup poin revisi 25 Agustus 2026 #13: "Ganti password siswa tidak
 * meminta password lama" — jalur web (modal edit_field di halaman profil
 * siswa). Kalau session siswa berhasil diambil orang lain, tanpa
 * pengecekan ini attacker bisa langsung mengganti password dan mengunci
 * pemilik asli dari akunnya sendiri. Lihat juga versi API-nya di
 * tests/Feature/Api/ProfilePasswordAuthorizationTest.php.
 */
class ProfilePasswordAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function sessionUntukSiswa(Siswa $siswa): array
    {
        return [
            'auth_role' => 'siswa',
            'auth_id' => $siswa->id,
            'auth_user' => ['nis' => $siswa->nis, 'nama' => $siswa->nama, 'must_change_password' => false],
        ];
    }

    public function test_siswa_bisa_ganti_password_dengan_password_lama_yang_benar(): void
    {
        $siswa = Siswa::factory()->create(['password' => 'password_lama']);
        $hashSebelum = $siswa->password;

        $response = $this->withSession($this->sessionUntukSiswa($siswa))
            ->put(route('siswa.profil.update'), [
                'edit_field' => 'password',
                'current_password' => 'password_lama',
                'edit_value' => 'password_baru_web',
                'edit_value_confirmation' => 'password_baru_web',
            ]);

        $response->assertRedirect();
        $this->assertNotSame($hashSebelum, $siswa->fresh()->password);
        $this->assertTrue($siswa->fresh()->verifyPassword('password_baru_web'));
    }

    public function test_siswa_tidak_bisa_ganti_password_tanpa_current_password(): void
    {
        $siswa = Siswa::factory()->create(['password' => 'password_lama']);
        $hashSebelum = $siswa->password;

        $response = $this->withSession($this->sessionUntukSiswa($siswa))
            ->put(route('siswa.profil.update'), [
                'edit_field' => 'password',
                'edit_value' => 'password_baru_tanpa_lama',
                'edit_value_confirmation' => 'password_baru_tanpa_lama',
            ]);

        $response->assertSessionHasErrors('current_password');
        $this->assertSame($hashSebelum, $siswa->fresh()->password);
    }

    public function test_siswa_tidak_bisa_ganti_password_dengan_current_password_salah(): void
    {
        $siswa = Siswa::factory()->create(['password' => 'password_lama']);
        $hashSebelum = $siswa->password;

        $response = $this->withSession($this->sessionUntukSiswa($siswa))
            ->put(route('siswa.profil.update'), [
                'edit_field' => 'password',
                'current_password' => 'password_salah',
                'edit_value' => 'password_baru_dari_penyerang',
                'edit_value_confirmation' => 'password_baru_dari_penyerang',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertSame($hashSebelum, $siswa->fresh()->password);
    }
}
