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
 *
 * PEMBARUAN (revisi 25 Agustus 2026, poin 10): dua test di bawah
 * (guru/kepsek tidak bisa ganti password) awalnya menguji kontrak lama —
 * saat itu Guru BK/Kepsek masih lolos masuk ke update() (untuk field lain)
 * dan HANYA field 'password' yang disaring diam-diam, sehingga response
 * tetap 200 tapi password tidak berubah. Sejak poin 10, Guru BK & Kepsek
 * ditolak TOTAL di update() (403) sebelum sempat memproses field apa pun.
 * Assert status code disesuaikan jadi assertForbidden(), tapi assertion inti
 * (password tidak pernah berubah) tetap dipertahankan.
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
        ])->assertForbidden();

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
        ])->assertForbidden();

        $this->assertSame($hashSebelum, $siswa->fresh()->password);
    }

    public function test_guru_cannot_change_other_profile_fields(): void
    {
        // PERBAIKAN (revisi 25 Agustus 2026, poin 10): dulu test ini
        // (dengan nama test_guru_can_still_change_other_profile_fields)
        // justru mendokumentasikan bahwa Guru BK BOLEH mengubah kelas &
        // alamat siswa mana pun. Itulah persis celah yang dilaporkan —
        // sekarang Guru BK ditolak total di endpoint update profil,
        // hanya boleh membaca (lihat ProfileController@get).
        $siswa = Siswa::factory()->create(['nis' => '3333333333', 'kelas' => '10 IPA 1', 'alamat' => 'Alamat Lama']);

        $guru = GuruBk::factory()->create();
        Sanctum::actingAs($guru, ['guru']);

        $this->putJson('/api/profile/3333333333', [
            'kelas' => '11 IPA 1',
            'alamat' => 'Jl. Contoh No. 1',
        ])->assertForbidden();

        $siswa->refresh();
        $this->assertSame('10 IPA 1', $siswa->kelas);
        $this->assertSame('Alamat Lama', $siswa->alamat);
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

        // PEMBARUAN (revisi 25 Agustus 2026, poin 13): sejak sekarang siswa
        // wajib menyertakan 'current_password' yang cocok sebelum password
        // baru diterima — lihat test tambahan di bawah untuk kasus gagal.
        $this->putJson('/api/profile/5555555555', [
            'current_password' => 'password_lama',
            'password' => 'password_baru_dari_siswa',
        ])->assertOk();

        $this->assertNotSame($hashSebelum, $siswa->fresh()->password);
    }

    /**
     * Menutup poin revisi 25 Agustus 2026 #13: "Ganti password siswa tidak
     * meminta password lama". Kalau session/token siswa berhasil diambil
     * orang lain, tanpa pengecekan ini attacker bisa langsung mengganti
     * password dan mengunci pemilik asli dari akunnya sendiri.
     */
    public function test_siswa_cannot_change_password_without_current_password(): void
    {
        $siswa = Siswa::factory()->create(['nis' => '8888888888', 'password' => 'password_lama']);
        $hashSebelum = $siswa->password;

        Sanctum::actingAs($siswa, ['siswa']);

        $this->putJson('/api/profile/8888888888', [
            'password' => 'password_baru_tanpa_lama',
        ])->assertStatus(400);

        $this->assertSame($hashSebelum, $siswa->fresh()->password);
    }

    public function test_siswa_cannot_change_password_with_wrong_current_password(): void
    {
        $siswa = Siswa::factory()->create(['nis' => '9999999999', 'password' => 'password_lama']);
        $hashSebelum = $siswa->password;

        Sanctum::actingAs($siswa, ['siswa']);

        $this->putJson('/api/profile/9999999999', [
            'current_password' => 'password_salah',
            'password' => 'password_baru_dari_penyerang',
        ])->assertStatus(400);

        $this->assertSame($hashSebelum, $siswa->fresh()->password);
    }

    /**
     * Admin mereset password siswa TIDAK mengetahui password lama siswa —
     * pengecualian ini sengaja dipertahankan (lihat komentar poin 13 di
     * ProfileController@update).
     */
    public function test_admin_can_change_siswa_password_without_current_password(): void
    {
        $siswa = Siswa::factory()->create(['nis' => '1010101010', 'password' => 'password_lama']);
        $hashSebelum = $siswa->password;

        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin, ['admin']);

        $this->putJson('/api/profile/1010101010', [
            'password' => 'password_reset_oleh_admin',
        ])->assertOk();

        $this->assertNotSame($hashSebelum, $siswa->fresh()->password);
    }

    public function test_siswa_cannot_change_another_siswas_password(): void
    {
        Siswa::factory()->create(['nis' => '6666666666', 'password' => 'password_lama']);
        $penyerang = Siswa::factory()->create(['nis' => '7777777777']);

        Sanctum::actingAs($penyerang, ['siswa']);

        $this->putJson('/api/profile/6666666666', [
            'current_password' => 'password_lama',
            'password' => 'password_dari_penyerang',
        ])->assertForbidden();
    }
}
