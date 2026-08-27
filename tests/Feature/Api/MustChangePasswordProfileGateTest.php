<?php

namespace Tests\Feature\Api;

use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Menutup revisi 27 Agustus 2026, poin 2 (lanjutan — hasil review dosen
 * penguji): "ProfileController@update tetap memperbolehkan perubahan
 * alamat, nomor telepon, jenis kelamin, dan sebagainya tanpa mengganti
 * password" selama akun siswa masih must_change_password = true.
 *
 * EnsurePasswordChanged mengecualikan ProfileController@get/@update dari
 * gate 423-nya supaya siswa punya jalan untuk mematuhi kewajiban ganti
 * password. Test ini memastikan endpoint @update ITU SENDIRI sekarang
 * menutup celah tsb: token apa pun (termasuk token yang mungkin sudah
 * dibajak sebelum Admin mereset password) tidak bisa lagi memakai
 * pengecualian itu untuk mengubah data lain tanpa benar-benar mengganti
 * password.
 */
class MustChangePasswordProfileGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_siswa_wajib_ganti_password_ditolak_mengubah_field_lain_tanpa_ganti_password(): void
    {
        $siswa = Siswa::factory()->create([
            'nis' => '1234',
            'alamat' => 'Alamat Lama',
            'must_change_password' => true,
        ]);

        Sanctum::actingAs($siswa, ['siswa']);

        $this->putJson('/api/profile/1234', [
            'alamat' => 'Alamat Baru Dari Attacker',
            'no_telepon' => '081234567890',
        ])->assertStatus(423)
            ->assertJson(['must_change_password' => true]);

        $this->assertSame('Alamat Lama', $siswa->fresh()->alamat);
    }

    public function test_field_lain_yang_diselipkan_bersama_password_diabaikan(): void
    {
        $siswa = Siswa::factory()->create([
            'nis' => '2345',
            'alamat' => 'Alamat Lama',
            'password' => 'password_lama',
            'must_change_password' => true,
        ]);

        Sanctum::actingAs($siswa, ['siswa']);

        $this->putJson('/api/profile/2345', [
            'current_password' => 'password_lama',
            'password' => 'password_baru_siswa',
            'alamat' => 'Alamat Yang Diselipkan',
        ])->assertOk();

        $siswa->refresh();
        // Password harus berubah (permintaan utamanya sah)...
        $this->assertTrue($siswa->verifyPassword('password_baru_siswa'));
        // ...tapi alamat yang ikut diselipkan dalam request yang sama
        // TIDAK boleh ikut tersimpan.
        $this->assertSame('Alamat Lama', $siswa->alamat);
        $this->assertFalse((bool) $siswa->must_change_password);
    }

    public function test_siswa_yang_sudah_tidak_wajib_ganti_password_tetap_bisa_ubah_field_lain(): void
    {
        $siswa = Siswa::factory()->create([
            'nis' => '3456',
            'alamat' => 'Alamat Lama',
            'must_change_password' => false,
        ]);

        Sanctum::actingAs($siswa, ['siswa']);

        $this->putJson('/api/profile/3456', [
            'alamat' => 'Alamat Baru Yang Sah',
        ])->assertOk();

        $this->assertSame('Alamat Baru Yang Sah', $siswa->fresh()->alamat);
    }

    public function test_admin_mereset_password_siswa_lain_tidak_terpengaruh_gate_ini(): void
    {
        $siswa = Siswa::factory()->create([
            'nis' => '4567',
            'password' => 'password_lama',
            'must_change_password' => false,
        ]);

        $admin = \App\Models\Admin::factory()->create();
        Sanctum::actingAs($admin, ['admin']);

        // Admin mereset password siswa yang TIDAK sedang wajib ganti
        // password sama sekali — gate ini merujuk ke must_change_password
        // milik siswa yang di-update, bukan Admin, jadi tidak relevan di
        // sini. Memastikan tidak ada regresi pada alur reset oleh Admin.
        $this->putJson('/api/profile/4567', [
            'password' => 'password_reset_oleh_admin',
        ])->assertOk();

        $this->assertTrue($siswa->fresh()->verifyPassword('password_reset_oleh_admin'));
    }
}
