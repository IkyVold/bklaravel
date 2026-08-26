<?php

namespace Tests\Feature\Api;

use App\Models\Admin;
use App\Models\GuruBk;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

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
            'nis' => '1234567890',
            'nama' => 'Siswa Baru',
            'kelas' => '10 IPA 1',
            'password' => 'password123',
        ])->assertForbidden();
    }

    public function test_guru_token_can_list_and_create_siswa_but_not_set_password(): void
    {
        $guru = GuruBk::factory()->create();
        Sanctum::actingAs($guru, ['guru']);

        $this->getJson('/api/siswa')->assertOk();

        $this->postJson('/api/siswa', [
            'nis' => '1234567890',
            'nama' => 'Siswa Baru',
            'kelas' => '10 IPA 1',
            'password' => 'password123',
        ])->assertStatus(400);

        $this->postJson('/api/siswa', [
            'nis' => '1234567890',
            'nama' => 'Siswa Baru',
            'kelas' => '10 IPA 1',
        ])->assertCreated();

        $siswa = Siswa::where('nis', '1234567890')->first();
        $this->assertNotNull($siswa);
        $this->assertTrue($siswa->verifyPassword('1234567890'));
    }

    public function test_admin_token_can_create_siswa_with_custom_password(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin, ['admin']);

        $this->postJson('/api/siswa', [
            'nis' => '9876543210',
            'nama' => 'Siswa Admin',
            'kelas' => '10 IPA 1',
            'password' => 'passwordAdmin',
        ])->assertCreated();

        $siswa = Siswa::where('nis', '9876543210')->first();
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
