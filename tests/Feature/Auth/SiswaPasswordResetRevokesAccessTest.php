<?php

namespace Tests\Feature\Auth;

use App\Models\Admin;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * PERBAIKAN (revisi 27 Agustus 2026, poin 2): reset password siswa oleh
 * Admin (Api\ProfileController@update) sebelumnya HANYA mengubah kolom
 * password — tidak ada mekanisme apa pun (baik lewat token Sanctum
 * maupun password_version) yang memutus token/session siswa yang sudah
 * terlanjur aktif sebelum reset ini terjadi. Kalau akun siswa sudah
 * dibajak, reset password oleh Admin tidak menutup akses attacker
 * tersebut sama sekali. Test-test ini mengikuti pola yang sama dengan
 * AccountDeactivationRevokesAccessTest (untuk staff), diterapkan ke
 * Siswa yang sebelumnya tidak punya mekanisme ini.
 */
class SiswaPasswordResetRevokesAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_token_dicabut_saat_password_siswa_direset_admin(): void
    {
        $siswa = Siswa::factory()->create(['nis' => '4444']);
        $token = $siswa->createToken('siswa-token', ['siswa'])->plainTextToken;

        $admin = Admin::factory()->create();
        $adminToken = $admin->createToken('admin-token', ['admin'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->putJson('/api/profile/4444', [
                'password' => 'password-reset-oleh-admin',
            ])->assertOk();

        $this->assertSame(0, $siswa->fresh()->tokens()->count(), 'Token lama siswa harus dicabut begitu password direset Admin.');

        // Token lama (kalau masih dipakai attacker) juga harus langsung
        // ditolak begitu dipakai lagi lewat guard 'sanctum' sungguhan.
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/profile/4444')
            ->assertStatus(401);
    }

    /**
     * Siswa mengganti password-nya sendiri (bukan direset Admin) tidak
     * perlu mencabut token yang sedang ia pakai sendiri untuk melakukan
     * itu — beda dari reset oleh Admin, ini bukan skenario akun yang
     * sudah dibajak.
     */
    public function test_token_siswa_tidak_dicabut_saat_ganti_password_sendiri(): void
    {
        $siswa = Siswa::factory()->create(['nis' => '5555', 'password' => 'password-lama']);
        $siswa->createToken('siswa-token', ['siswa']);

        Sanctum::actingAs($siswa, ['siswa']);

        $this->putJson('/api/profile/5555', [
            'current_password' => 'password-lama',
            'password' => 'password-baru-dari-siswa',
        ])->assertOk();

        $this->assertSame(1, $siswa->fresh()->tokens()->count());
    }

    public function test_session_web_siswa_langsung_ditolak_setelah_password_direset_admin(): void
    {
        $siswa = Siswa::factory()->create(['nis' => '6666']);

        // Simulasikan sesi web siswa yang sudah login, persis seperti
        // Web\AuthController@loginSiswa setelah login sukses.
        Session::put('auth_role', 'siswa');
        Session::put('auth_id', $siswa->id);
        Session::put('auth_user', [
            'nis' => $siswa->nis,
            'nama' => $siswa->nama,
            'kelas' => $siswa->kelas,
            'must_change_password' => false,
        ]);
        Session::put('auth_password_version', (int) $siswa->password_version);

        // Selama password belum diganti, request tetap lolos normal.
        $this->get(route('siswa.dashboard'))->assertOk();

        // Admin mereset password siswa ini dari "sesi lain" (mis. lewat
        // Api\ProfileController@update, yang memicu setPasswordAttribute
        // dan menaikkan password_version-nya).
        $siswa->forceFill(['password' => 'password-baru-dari-admin'])->save();

        // Request BERIKUTNYA dari session siswa yang sama, yang belum
        // pernah logout dan belum kedaluwarsa, harus langsung ditolak dan
        // diarahkan ke login karena password sudah berubah sejak session
        // ini dibuat.
        $response = $this->get(route('siswa.dashboard'));
        $response->assertRedirect(route('login'));
        $response->assertSessionMissing('auth_role');
    }

    /**
     * Sesi yang dibuat SEBELUM fitur baseline ini ada (tidak membawa
     * kunci auth_password_version sama sekali) tidak boleh dipaksa
     * logout hanya karena kekurangan kunci tersebut.
     */
    public function test_session_lama_siswa_tanpa_baseline_password_tidak_dipaksa_logout(): void
    {
        $siswa = Siswa::factory()->create(['nis' => '7777']);

        Session::put('auth_role', 'siswa');
        Session::put('auth_id', $siswa->id);
        Session::put('auth_user', [
            'nis' => $siswa->nis,
            'nama' => $siswa->nama,
            'kelas' => $siswa->kelas,
            'must_change_password' => false,
        ]);
        // Sengaja TIDAK menyimpan 'auth_password_version'.

        $this->get(route('siswa.dashboard'))->assertOk();
    }

    /**
     * Siswa yang mengganti password-nya sendiri lewat halaman profil web
     * tidak boleh langsung ter-logout pada request berikutnya —
     * baseline session harus ikut disinkronkan (lihat
     * Web\ProfileController@update).
     */
    public function test_siswa_tidak_logout_setelah_ganti_password_sendiri_lewat_web(): void
    {
        $siswa = Siswa::factory()->create(['nis' => '8888', 'password' => 'password-lama']);

        Session::put('auth_role', 'siswa');
        Session::put('auth_id', $siswa->id);
        Session::put('auth_user', [
            'nis' => $siswa->nis,
            'nama' => $siswa->nama,
            'kelas' => $siswa->kelas,
            'must_change_password' => false,
        ]);
        Session::put('auth_password_version', (int) $siswa->password_version);

        $this->put(route('siswa.profil.update'), [
            'edit_field' => 'password',
            'edit_value' => 'password-baru-dari-siswa',
            'edit_value_confirmation' => 'password-baru-dari-siswa',
            'current_password' => 'password-lama',
        ])->assertRedirect();

        // Request berikutnya di sesi yang sama harus tetap lolos, bukan
        // dipaksa logout, karena baseline ikut naik bersamaan.
        $this->get(route('siswa.dashboard'))->assertOk();
    }
}
