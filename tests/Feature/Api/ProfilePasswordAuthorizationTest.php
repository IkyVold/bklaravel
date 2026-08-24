<?php

namespace Tests\Feature\Api;

use App\Models\Admin;
use App\Models\GuruBk;
use App\Models\Kepsek;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Menutup poin revisi 24 Agustus 2026 #1: "Guru BK dan Kepala Sekolah dapat
 * mengganti password siswa melalui API". assertSiswaOwnsNis() memang sengaja
 * membolehkan staff melewati pengecekan kepemilikan NIS (untuk field lain),
 * tapi field 'password' pada ProfileController@update sekarang hanya boleh
 * diisi oleh siswa yang bersangkutan sendiri, atau oleh Admin.
 */
class ProfilePasswordAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guru_cannot_change_siswa_password(): void
    {
        $siswa = Siswa::factory()->create(['nis' => '1111111111', 'password' => 'password_lama']);
        $hashSebelum = $siswa->password;

        $guru = GuruBk::factory()->create();
        Sanctum::actingAs($guru, ['guru']);

        $this->putJson('/api/profile/1111111111', [
            'password' => 'password_baru_dari_guru',
        ])->assertOk();

        $this->assertSame($hashSebelum, $siswa->fresh()->password);
    }

    public function test_kepsek_cannot_change_siswa_password(): void
    {
        $siswa = Siswa::factory()->create(['nis' => '2222222222', 'password' => 'password_lama']);
        $hashSebelum = $siswa->password;

        $kepsek = Kepsek::factory()->create();
        Sanctum::actingAs($kepsek, ['kepsek']);

        $this->putJson('/api/profile/2222222222', [
            'password' => 'password_baru_dari_kepsek',
        ])->assertOk();

        $this->assertSame($hashSebelum, $siswa->fresh()->password);
    }

    public function test_guru_can_still_change_other_profile_fields(): void
    {
        // Memastikan fix ini tidak mematikan hak staff atas field lain,
        // hanya 'password' yang dibatasi.
        $siswa = Siswa::factory()->create(['nis' => '3333333333', 'kelas' => '10 IPA 1']);

        $guru = GuruBk::factory()->create();
        Sanctum::actingAs($guru, ['guru']);

        $this->putJson('/api/profile/3333333333', [
            'kelas' => '11 IPA 1',
            'alamat' => 'Jl. Contoh No. 1',
        ])->assertOk();

        $siswa->refresh();
        $this->assertSame('11 IPA 1', $siswa->kelas);
        $this->assertSame('Jl. Contoh No. 1', $siswa->alamat);
    }

    public function test_admin_can_change_siswa_password(): void
    {
        $siswa = Siswa::factory()->create(['nis' => '4444444444', 'password' => 'password_lama']);
        $hashSebelum = $siswa->password;

        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin, ['admin']);

        $this->putJson('/api/profile/4444444444', [
            'password' => 'password_baru_dari_admin',
        ])->assertOk();

        $this->assertNotSame($hashSebelum, $siswa->fresh()->password);
    }

    public function test_siswa_can_change_own_password(): void
    {
        $siswa = Siswa::factory()->create(['nis' => '5555555555', 'password' => 'password_lama']);
        $hashSebelum = $siswa->password;

        Sanctum::actingAs($siswa, ['siswa']);

        $this->putJson('/api/profile/5555555555', [
            'password' => 'password_baru_dari_siswa',
        ])->assertOk();

        $this->assertNotSame($hashSebelum, $siswa->fresh()->password);
    }

    public function test_siswa_cannot_change_another_siswas_password(): void
    {
        Siswa::factory()->create(['nis' => '6666666666', 'password' => 'password_lama']);
        $penyerang = Siswa::factory()->create(['nis' => '7777777777']);

        Sanctum::actingAs($penyerang, ['siswa']);

        $this->putJson('/api/profile/6666666666', [
            'password' => 'password_dari_penyerang',
        ])->assertForbidden();
    }
}
