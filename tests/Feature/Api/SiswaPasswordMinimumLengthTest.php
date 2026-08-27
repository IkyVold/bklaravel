<?php

namespace Tests\Feature\Api;

use App\Models\Admin;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Menutup poin revisi 27 Agustus 2026 #10: "API masih mengizinkan password
 * siswa hanya 4 karakter". Sebelumnya Api\SiswaController::create() memakai
 * rule 'min:4' untuk password custom yang ditentukan Admin, dan
 * importRows() bahkan sama sekali tidak memvalidasi panjang password
 * custom per baris. Sekarang keduanya mensyaratkan minimal 10 karakter
 * untuk password custom (password default = NIS, yang wajib segera
 * diganti lewat must_change_password, tidak terkena aturan ini).
 */
class SiswaPasswordMinimumLengthTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_siswa_menolak_password_custom_kurang_dari_10_karakter(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin, ['admin']);

        $this->postJson('/api/siswa', [
            'nis' => '4444',
            'nama' => 'Siswa Password Pendek',
            'kelas' => 'X - 1',
            'password' => 'pendek123', // 9 karakter
        ])->assertStatus(400);

        $this->assertDatabaseMissing('siswa', ['nis' => '4444']);
    }

    public function test_create_siswa_menerima_password_custom_10_karakter_atau_lebih(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin, ['admin']);

        $this->postJson('/api/siswa', [
            'nis' => '5555',
            'nama' => 'Siswa Password Cukup',
            'kelas' => 'X - 1',
            'password' => 'passwordCukup10', // 15 karakter
        ])->assertCreated();

        $siswa = Siswa::where('nis', '5555')->first();
        $this->assertNotNull($siswa);
        $this->assertTrue($siswa->verifyPassword('passwordCukup10'));
    }

    public function test_create_siswa_tanpa_password_tetap_boleh_default_ke_nis(): void
    {
        // Password default (= NIS, 4 digit) SENGAJA tidak kena aturan
        // minimal 10 karakter — itu bukan password custom, hanya nilai
        // awal sementara yang wajib segera diganti siswa.
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin, ['admin']);

        $this->postJson('/api/siswa', [
            'nis' => '6666',
            'nama' => 'Siswa Password Default',
            'kelas' => 'X - 1',
        ])->assertCreated();

        $siswa = Siswa::where('nis', '6666')->first();
        $this->assertNotNull($siswa);
        $this->assertTrue($siswa->verifyPassword('6666'));
        $this->assertTrue((bool) $siswa->must_change_password);
    }

    public function test_import_rows_melewati_baris_dengan_password_custom_kurang_dari_10_karakter(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin, ['admin']);

        $response = $this->postJson('/api/siswa/import-rows', [
            'rows' => [
                ['nis' => '7001', 'nama' => 'Password Custom Cukup', 'kelas' => 'X - 1', 'password' => 'passwordCukup10'],
                ['nis' => '7002', 'nama' => 'Password Custom Pendek', 'kelas' => 'X - 2', 'password' => 'pendek'],
                ['nis' => '7003', 'nama' => 'Tanpa Password Custom', 'kelas' => 'X - 3'],
            ],
        ])->assertOk();

        $response->assertJsonPath('inserted', 2);
        $response->assertJsonPath('skipped', 1);

        $siswaCukup = Siswa::where('nis', '7001')->first();
        $this->assertNotNull($siswaCukup);
        $this->assertTrue($siswaCukup->verifyPassword('passwordCukup10'));

        $this->assertDatabaseMissing('siswa', ['nis' => '7002']);

        // Baris tanpa password custom tetap boleh default ke NIS.
        $siswaDefault = Siswa::where('nis', '7003')->first();
        $this->assertNotNull($siswaDefault);
        $this->assertTrue($siswaDefault->verifyPassword('7003'));
    }
}
