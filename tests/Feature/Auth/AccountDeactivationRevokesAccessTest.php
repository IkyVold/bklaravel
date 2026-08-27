<?php

namespace Tests\Feature\Auth;

use App\Models\Admin;
use App\Models\GuruBk;
use App\Models\Kepsek;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * PERBAIKAN (revisi 26 Agustus 2026, poin 3): menonaktifkan akun Guru/
 * Kepsek/Admin dulu hanya mengubah is_active = false tanpa mencabut token
 * Sanctum yang sudah diterbitkan, dan tanpa menginvalidasi session web yang
 * sedang berjalan. Akun yang baru dinonaktifkan tetap bisa dipakai penuh
 * sampai token/session-nya kedaluwarsa sendiri.
 */
class AccountDeactivationRevokesAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_token_dicabut_saat_guru_dinonaktifkan_lewat_endpoint_delete(): void
    {
        $guru = GuruBk::factory()->create(['is_active' => true]);
        $token = $guru->createToken('guru-token', ['guru'])->plainTextToken;

        // PERBAIKAN: sebelumnya di sini memakai Sanctum::actingAs($admin, ...).
        // Method itu mem-bypass resolusi guard 'sanctum' untuk SELURUH
        // request berikutnya di test ini — termasuk request di bawah yang
        // sengaja memakai header Authorization berisi token guru yang
        // sudah dicabut. Akibatnya request itu tetap dianggap admin yang
        // sah (200) walau tokennya sudah tidak valid, membuat assertion
        // 401 di bawah gagal — bukan karena revoke tokennya tidak jalan
        // (assertion count()===0 di atas justru lolos), tapi karena
        // otentikasi request keduanya tidak benar-benar diuji lewat token
        // asli. Sekarang admin memakai token Sanctum sungguhan yang
        // dikirim lewat header, sama seperti klien nyata, supaya request
        // memakai token guru lama di bawah benar-benar diautentikasi
        // ulang lewat guard 'sanctum' yang sesungguhnya.
        $admin = Admin::factory()->create();
        $adminToken = $admin->createToken('admin-token', ['admin'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$adminToken}")
            ->deleteJson("/api/akun/guru/{$guru->id}")
            ->assertOk();

        $this->assertSame(0, $guru->fresh()->tokens()->count(), 'Seluruh token milik akun yang dinonaktifkan harus dihapus.');

        // PERBAIKAN: guard 'sanctum' (RequestGuard) meng-cache user yang
        // berhasil diautentikasi pada request PERTAMA (di properti
        // internalnya) selama container aplikasi masih sama — dan di
        // dalam satu method test, seluruh simulasi HTTP request memakai
        // instance aplikasi yang sama (tidak reboot per-request). Tanpa
        // baris ini, request kedua di bawah akan tetap "mewarisi" hasil
        // autentikasi admin dari request pertama alih-alih benar-benar
        // mengevaluasi ulang header Authorization yang baru (berisi token
        // guru yang sudah dihapus) — membuat test lolos secara keliru
        // walau seharusnya gagal (dan sebaliknya, bisa juga gagal secara
        // keliru seperti yang terjadi di sini: 200 padahal seharusnya
        // 401). forgetGuards() memaksa guard di-resolve ulang dari nol
        // pada request berikutnya, sehingga token guru yang sudah dicabut
        // benar-benar diuji lewat jalur autentikasi sesungguhnya.
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

    /**
     * PERBAIKAN (revisi 26 Agustus 2026, poin 3): reset password Guru BK
     * sebelumnya hanya mencabut token Sanctum (API) — session Web yang
     * sudah terlanjur login tetap hidup penuh walau passwordnya baru saja
     * diganti Admin. Sekarang RoleAuth membandingkan ulang
     * password_version akun ke database pada setiap request; kalau
     * tidak cocok lagi dengan baseline saat login, session langsung
     * dipaksa logout.
     */
    public function test_session_web_guru_langsung_ditolak_setelah_password_direset(): void
    {
        $guru = GuruBk::factory()->create(['is_active' => true]);

        // Simulasikan login sungguhan: baseline password_version ikut
        // disimpan di session, persis seperti Web\AuthController@loginStaff.
        Session::put('auth_role', 'guru');
        Session::put('auth_id', $guru->id);
        Session::put('auth_user', ['username' => $guru->username, 'nama' => $guru->nama]);
        Session::put('auth_password_version', (int) $guru->password_version);

        // Selama password belum diganti, request tetap lolos normal.
        $this->get(route('guru.dashboard'))->assertRedirect(route('guru.konseling.index'));

        // Admin mereset password guru ini dari "sesi lain" (mis. lewat
        // Api\AkunController@updateGuru, yang memicu setPasswordAttribute
        // dan menaikkan password_version-nya).
        $guru->forceFill(['password' => 'password-baru-dari-admin'])->save();

        // Request BERIKUTNYA dari session guru yang sama, yang belum
        // pernah logout dan belum kedaluwarsa, harus langsung ditolak dan
        // diarahkan ke login karena password sudah berubah sejak session
        // ini dibuat.
        $response = $this->get(route('guru.dashboard'));
        $response->assertRedirect(route('login'));
        $response->assertSessionMissing('auth_role');
    }

    /**
     * Sesi yang dibuat SEBELUM fitur baseline ini ada (tidak membawa kunci
     * auth_password_version sama sekali) tidak boleh dipaksa logout
     * hanya karena kekurangan kunci tersebut — itu bukan indikasi
     * password berubah, hanya indikasi session lama. Ini memastikan
     * pengecekan baru tidak mengganggu session yang sudah berjalan saat
     * fitur ini pertama kali di-deploy.
     */
    public function test_session_lama_tanpa_baseline_password_tidak_dipaksa_logout(): void
    {
        $guru = GuruBk::factory()->create(['is_active' => true]);

        Session::put('auth_role', 'guru');
        Session::put('auth_id', $guru->id);
        Session::put('auth_user', ['username' => $guru->username, 'nama' => $guru->nama]);
        // Sengaja TIDAK menyimpan 'auth_password_version'.

        $this->get(route('guru.dashboard'))->assertRedirect(route('guru.konseling.index'));
    }
}
