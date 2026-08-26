<?php

namespace Tests\Feature\Auth;

use App\Models\Admin;
use App\Models\GuruBk;
use App\Models\Kepsek;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountDeactivationRevokesAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_token_dicabut_saat_guru_dinonaktifkan_lewat_endpoint_delete(): void
    {
        $guru = GuruBk::factory()->create(['is_active' => true]);
        $token = $guru->createToken('guru-token', ['guru'])->plainTextToken;

        $admin = Admin::factory()->create();
        $adminToken = $admin->createToken('admin-token', ['admin'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->deleteJson("/api/akun/guru/{$guru->id}")
            ->assertOk();

        $this->assertSame(0, $guru->fresh()->tokens()->count(), 'Seluruh token milik akun yang dinonaktifkan harus dihapus.');

        $this->app['auth']->forgetGuards();

        // Token lama (kalau masih dipakai oleh klien) juga harus langsung
        // ditolak begitu dipakai lagi.
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/notifikasi');
        $response->assertStatus(401);
    }

    public function test_token_dicabut_saat_password_guru_direset_lewat_endpoint_update(): void
    {
        $guru = GuruBk::factory()->create(['is_active' => true]);
        $guru->createToken('guru-token', ['guru']);

        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin, ['admin']);

        $this->putJson("/api/akun/guru/{$guru->id}", [
            'username' => $guru->username,
            'nama' => $guru->nama,
            'password' => 'password-baru-123',
        ])->assertOk();

        $this->assertSame(0, $guru->fresh()->tokens()->count(), 'Token lama harus dicabut begitu password direset Admin.');
    }

    public function test_token_dicabut_saat_kepsek_dinonaktifkan(): void
    {
        $kepsek = Kepsek::factory()->create(['is_active' => true]);
        $kepsek->createToken('kepsek-token', ['kepsek']);

        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin, ['admin']);

        $this->deleteJson("/api/akun/kepsek/{$kepsek->id}")->assertOk();

        $this->assertSame(0, $kepsek->fresh()->tokens()->count());
    }

    public function test_session_web_guru_yang_dinonaktifkan_langsung_ditolak_pada_request_berikutnya(): void
    {
        $guru = GuruBk::factory()->create(['is_active' => true]);

        // Simulasikan sesi web guru yang sudah login (tanpa lewat form
        // login, langsung isi session seperti yang dilakukan AuthController
        // setelah login sukses).
        Session::put('auth_role', 'guru');
        Session::put('auth_id', $guru->id);
        Session::put('auth_user', ['username' => $guru->username, 'nama' => $guru->nama]);

        // Selama masih aktif, RoleAuth meloloskan request ke controller
        // (dashboard guru sendiri redirect ke daftar konseling — bukan 401/
        // redirect ke login).
        $this->get(route('guru.dashboard'))->assertRedirect(route('guru.konseling.index'));

        // Admin menonaktifkan akun ini dari "sesi lain".
        $guru->update(['is_active' => false]);

        // Request BERIKUTNYA dari sesi guru yang sudah dinonaktifkan tadi
        // harus langsung ditolak oleh RoleAuth dan diarahkan ke login,
        // walau session-nya sendiri belum kedaluwarsa dan belum pernah
        // logout.
        $response = $this->get(route('guru.dashboard'));
        $response->assertRedirect(route('login'));
        $response->assertSessionMissing('auth_role');
    }

    public function test_guru_yang_masih_aktif_tidak_terganggu(): void
    {
        $guru = GuruBk::factory()->create(['is_active' => true]);

        Session::put('auth_role', 'guru');
        Session::put('auth_id', $guru->id);
        Session::put('auth_user', ['username' => $guru->username, 'nama' => $guru->nama]);

        $this->get(route('guru.dashboard'))->assertRedirect(route('guru.konseling.index'));
        $this->get(route('guru.dashboard'))->assertRedirect(route('guru.konseling.index'));
    }
}
