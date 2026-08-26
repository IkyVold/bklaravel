<?php

namespace Tests\Feature\Auth;

use App\Models\Admin;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MustChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_siswa_dengan_password_default_mengembalikan_flag_wajib_ganti(): void
    {
        $siswa = Siswa::factory()->create(['nis' => '1000000001', 'password' => '1000000001', 'must_change_password' => true]);

        $response = $this->postJson('/api/login', [
            'role' => 'siswa',
            'nis' => $siswa->nis,
            'password' => '1000000001',
        ]);

        $response->assertOk();
        $response->assertJsonPath('must_change_password', true);
    }

    public function test_siswa_wajib_ganti_password_diblokir_dari_endpoint_lain(): void
    {
        $siswa = Siswa::factory()->create(['must_change_password' => true]);
        Sanctum::actingAs($siswa, ['siswa']);

        $this->getJson('/api/notifikasi')->assertStatus(423);
        $this->getJson('/api/konseling/' . $siswa->nis)->assertStatus(423);
    }

    public function test_siswa_wajib_ganti_password_tetap_bisa_akses_profil_sendiri(): void
    {
        $siswa = Siswa::factory()->create(['must_change_password' => true]);
        Sanctum::actingAs($siswa, ['siswa']);

        $this->getJson('/api/profile/' . $siswa->nis)->assertOk();
    }

    public function test_siswa_ganti_password_sendiri_membebaskan_dari_kewajiban(): void
    {
        $siswa = Siswa::factory()->create(['must_change_password' => true]);
        Sanctum::actingAs($siswa, ['siswa']);

        $this->putJson('/api/profile/' . $siswa->nis, [
            'current_password' => 'password',
            'password' => 'password_baru_pilihan_sendiri',
        ])->assertOk();

        $this->assertFalse((bool) $siswa->fresh()->must_change_password);

        // Setelah ganti password sendiri, endpoint lain sudah bisa diakses.
        // Catatan: Sanctum::actingAs() mengikat SATU instance model untuk
        // seluruh request simulasi dalam test ini (tidak otomatis
        // mengambil ulang dari DB seperti request sungguhan) — jadi kita
        // bind ulang dengan model yang sudah di-refresh, meniru bagaimana
        // request HTTP baru akan mengautentikasi ulang dari DB.
        Sanctum::actingAs($siswa->fresh(), ['siswa']);
        $this->getJson('/api/notifikasi')->assertOk();
    }

    public function test_admin_reset_password_siswa_tetap_menandai_wajib_ganti(): void
    {
        $siswa = Siswa::factory()->create(['must_change_password' => false]);
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin, ['admin']);

        $this->putJson('/api/profile/' . $siswa->nis, [
            'password' => 'password_reset_oleh_admin',
        ])->assertOk();

        $this->assertTrue((bool) $siswa->fresh()->must_change_password);
    }

    public function test_guru_dan_kepsek_tidak_terpengaruh_middleware_wajib_ganti_password(): void
    {
        // Guru/Kepsek belum punya mekanisme self-service ganti password,
        // sehingga middleware ini SENGAJA tidak berlaku untuk mereka.
        $guru = \App\Models\GuruBk::factory()->create();
        Sanctum::actingAs($guru, ['guru']);

        $this->getJson('/api/konseling-bk')->assertOk();
    }

    public function test_siswa_baru_dibuat_guru_selalu_wajib_ganti_password(): void
    {
        $guru = \App\Models\GuruBk::factory()->create();
        Sanctum::actingAs($guru, ['guru']);

        $this->postJson('/api/siswa', [
            'nis' => '9999999999',
            'nama' => 'Siswa Baru',
            'kelas' => '10 IPA 1',
        ])->assertCreated();

        $siswaBaru = Siswa::where('nis', '9999999999')->first();
        $this->assertTrue((bool) $siswaBaru->must_change_password);
    }

    public function test_login_web_siswa_dengan_password_default_diarahkan_ke_halaman_profil(): void
    {
        $siswa = Siswa::factory()->create(['nis' => '1000000002', 'password' => '1000000002', 'must_change_password' => true]);

        $response = $this->post(route('login.submit'), [
            'role' => 'siswa',
            'nis' => $siswa->nis,
            'password' => '1000000002',
        ]);

        $response->assertRedirect(route('siswa.profil'));
    }

    public function test_web_siswa_wajib_ganti_password_diarahkan_paksa_dari_halaman_lain(): void
    {
        $siswa = Siswa::factory()->create(['must_change_password' => true]);

        $this->withSession([
            'auth_role' => 'siswa',
            'auth_id' => $siswa->id,
            'auth_user' => ['nis' => $siswa->nis, 'nama' => $siswa->nama, 'must_change_password' => true],
        ])->get(route('siswa.dashboard'))->assertRedirect(route('siswa.profil'));
    }

    public function test_web_siswa_wajib_ganti_password_tetap_bisa_akses_halaman_profil(): void
    {
        $siswa = Siswa::factory()->create(['must_change_password' => true]);

        $this->withSession([
            'auth_role' => 'siswa',
            'auth_id' => $siswa->id,
            'auth_user' => ['nis' => $siswa->nis, 'nama' => $siswa->nama, 'must_change_password' => true],
        ])->get(route('siswa.profil'))->assertOk();
    }

    public function test_web_siswa_ganti_password_lewat_modal_membebaskan_dari_kewajiban(): void
    {
        $siswa = Siswa::factory()->create(['must_change_password' => true]);

        $response = $this->withSession([
            'auth_role' => 'siswa',
            'auth_id' => $siswa->id,
            'auth_user' => ['nis' => $siswa->nis, 'nama' => $siswa->nama, 'must_change_password' => true],
        ])->put(route('siswa.profil.update'), [
            'edit_field' => 'password',
            'current_password' => 'password',
            'edit_value' => 'password_baru_web',
            'edit_value_confirmation' => 'password_baru_web',
        ]);

        $response->assertRedirect();
        $this->assertFalse((bool) $siswa->fresh()->must_change_password);
    }
}
