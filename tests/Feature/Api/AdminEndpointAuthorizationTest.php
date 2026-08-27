<?php

namespace Tests\Feature\Api;

use App\Models\Admin;
use App\Models\GuruBk;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Menutup poin revisi: "endpoint akun/siswa/riwayat-kelas hanya berada di
 * bawah auth:sanctum tanpa cek role — token siswa yang sah bisa mencapai
 * fungsi admin". Sekarang endpoint tersebut wajib ability:admin (akun) atau
 * ability:guru,kepsek,admin (siswa/riwayat-kelas).
 */
class AdminEndpointAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_siswa_token_cannot_list_akun_guru(): void
    {
        $siswa = Siswa::factory()->create();
        Sanctum::actingAs($siswa, ['siswa']);

        $this->getJson('/api/akun/guru')->assertForbidden();
    }

    public function test_siswa_token_cannot_create_akun_guru(): void
    {
        $siswa = Siswa::factory()->create();
        Sanctum::actingAs($siswa, ['siswa']);

        $this->postJson('/api/akun/guru', [
            'username' => 'guru_baru',
            'password' => 'password123',
            'nama' => 'Guru Baru',
        ])->assertForbidden();

        $this->assertDatabaseMissing('guru_bk', ['username' => 'guru_baru']);
    }

    public function test_admin_token_can_manage_akun_guru(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin, ['admin']);

        $this->getJson('/api/akun/guru')->assertOk();
    }

    public function test_siswa_token_cannot_create_master_siswa(): void
    {
        $siswa = Siswa::factory()->create();
        Sanctum::actingAs($siswa, ['siswa']);

        $this->postJson('/api/siswa', [
            'nis' => '1234',
            'nama' => 'Siswa Baru',
            'kelas' => 'X - 1',
            'password' => 'password123',
        ])->assertForbidden();
    }

    /**
     * PERBAIKAN (revisi 24 Agustus 2026, poin 10): Guru BK sekarang BOLEH
     * membuat master siswa lewat API (disamakan dengan jalur Web yang
     * memang sudah lama memberi kemampuan ini), tapi tidak boleh
     * menentukan passwordnya — dipaksa server = NIS berapa pun yang
     * dikirim di body.
     */
    public function test_guru_token_can_list_and_create_siswa_but_not_set_password(): void
    {
        $guru = GuruBk::factory()->create();
        Sanctum::actingAs($guru, ['guru']);

        $this->getJson('/api/siswa')->assertOk();

        $this->postJson('/api/siswa', [
            'nis' => '1234',
            'nama' => 'Siswa Baru',
            'kelas' => 'X - 1',
            'password' => 'password123',
        ])->assertStatus(400);

        $response = $this->postJson('/api/siswa', [
            'nis' => '1234',
            'nama' => 'Siswa Baru',
            'kelas' => 'X - 1',
        ])->assertCreated();

        // PEMBARUAN (revisi 27 Agustus 2026, poin 1): password default
        // sekarang acak (bukan lagi = NIS), dan dikembalikan di response
        // supaya Guru BK tahu apa yang harus disampaikan ke siswa.
        $siswa = Siswa::where('nis', '1234')->first();
        $this->assertNotNull($siswa);
        $this->assertFalse($siswa->verifyPassword('1234'));
        $generatedPassword = $response->json('data.password');
        $this->assertNotNull($generatedPassword);
        $this->assertTrue($siswa->verifyPassword($generatedPassword));
    }

    public function test_admin_token_can_create_siswa_with_custom_password(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin, ['admin']);

        $this->postJson('/api/siswa', [
            'nis' => '9876',
            'nama' => 'Siswa Admin',
            'kelas' => 'X - 1',
            'password' => 'passwordAdmin',
        ])->assertCreated();

        $siswa = Siswa::where('nis', '9876')->first();
        $this->assertNotNull($siswa);
        $this->assertTrue($siswa->verifyPassword('passwordAdmin'));
    }

    public function test_siswa_token_cannot_delete_riwayat_kelas(): void
    {
        $siswa = Siswa::factory()->create();
        Sanctum::actingAs($siswa, ['siswa']);

        $this->deleteJson('/api/riwayat-kelas/1')->assertForbidden();
    }
}
