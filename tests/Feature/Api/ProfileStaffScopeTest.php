<?php

namespace Tests\Feature\Api;

use App\Models\Admin;
use App\Models\GuruBk;
use App\Models\Kepsek;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Menutup poin revisi 25 Agustus 2026 #10: "Guru/Kepsek masih terlalu luas
 * dalam mengubah profil siswa lewat API". Sebelumnya seluruh staff
 * (Guru BK, Kepsek, Admin) lolos assertSiswaOwnsNis() pada
 * update()/updateFoto()/deleteFoto(), sehingga Guru BK maupun Kepala
 * Sekolah bisa mengubah/menghapus data & foto siswa mana pun.
 *
 * Pembagian akses sekarang:
 *  - Siswa   : boleh mengubah profil & foto miliknya sendiri.
 *  - Admin   : master akademik — boleh mengubah profil & foto siswa mana pun.
 *  - Guru BK : hanya baca (GET), ditolak di endpoint tulis.
 *  - Kepsek  : hanya baca (GET), ditolak di endpoint tulis — termasuk kelas & foto.
 */
class ProfileStaffScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guru_cannot_update_siswa_profile(): void
    {
        $siswa = Siswa::factory()->create(['nis' => '1001', 'alamat' => 'Alamat Lama']);

        $guru = GuruBk::factory()->create();
        Sanctum::actingAs($guru, ['guru']);

        $this->putJson('/api/profile/1001', [
            'alamat' => 'Alamat Baru Dari Guru',
        ])->assertForbidden();

        $this->assertSame('Alamat Lama', $siswa->fresh()->alamat);
    }

    public function test_kepsek_cannot_update_siswa_profile(): void
    {
        $siswa = Siswa::factory()->create(['nis' => '1002', 'kelas' => '10 IPA 1']);

        $kepsek = Kepsek::factory()->create();
        Sanctum::actingAs($kepsek, ['kepsek']);

        $this->putJson('/api/profile/1002', [
            'kelas' => '11 IPA 1',
        ])->assertForbidden();

        $this->assertSame('10 IPA 1', $siswa->fresh()->kelas);
    }

    public function test_admin_can_update_siswa_profile_including_kelas(): void
    {
        $siswa = Siswa::factory()->create(['nis' => '1003', 'kelas' => '10 IPA 1']);

        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin, ['admin']);

        $this->putJson('/api/profile/1003', [
            'kelas' => '11 IPA 1',
            'alamat' => 'Alamat Dari Admin',
        ])->assertOk();

        $siswa->refresh();
        $this->assertSame('11 IPA 1', $siswa->kelas);
        $this->assertSame('Alamat Dari Admin', $siswa->alamat);
    }

    public function test_siswa_can_update_own_profile(): void
    {
        $siswa = Siswa::factory()->create(['nis' => '1004', 'alamat' => 'Alamat Lama']);
        Sanctum::actingAs($siswa, ['siswa']);

        $this->putJson('/api/profile/1004', [
            'alamat' => 'Alamat Baru Dari Siswa',
        ])->assertOk();

        $this->assertSame('Alamat Baru Dari Siswa', $siswa->fresh()->alamat);
    }

    public function test_siswa_cannot_set_kelas_on_own_profile(): void
    {
        // 'kelas' hanya termasuk $rules jika pemanggil Admin — siswa yang
        // mengirim 'kelas' tidak akan membuat request gagal (field diabaikan
        // oleh validator), tapi nilainya tidak boleh berubah.
        $siswa = Siswa::factory()->create(['nis' => '1005', 'kelas' => '10 IPA 1']);
        Sanctum::actingAs($siswa, ['siswa']);

        $this->putJson('/api/profile/1005', [
            'kelas' => '12 IPS 3',
            'alamat' => 'Alamat Siswa',
        ])->assertOk();

        $this->assertSame('10 IPA 1', $siswa->fresh()->kelas);
    }

    public function test_guru_cannot_update_siswa_foto(): void
    {
        Storage::fake('public');
        $siswa = Siswa::factory()->create(['nis' => '1006']);

        $guru = GuruBk::factory()->create();
        Sanctum::actingAs($guru, ['guru']);

        // UploadedFile::fake()->create() dipakai alih-alih ->image() —
        // ->image() butuh ekstensi GD untuk benar-benar merender piksel
        // gambar, yang belum tentu terpasang di semua environment. Untuk
        // uji otorisasi (403 sebelum file diproses) ini tidak relevan;
        // ->create() dengan mimeType eksplisit sudah cukup memicu rule
        // validasi 'image' tanpa perlu GD.
        $this->putJson('/api/profile/1006/foto', [
            'foto' => UploadedFile::fake()->create('foto.jpg', 10, 'image/jpeg'),
        ])->assertForbidden();

        $this->assertNull($siswa->fresh()->foto_profile);
    }

    public function test_kepsek_cannot_delete_siswa_foto(): void
    {
        Storage::fake('public');
        $siswa = Siswa::factory()->create(['nis' => '1007', 'foto_profile' => 'siswa/existing.jpg']);

        $kepsek = Kepsek::factory()->create();
        Sanctum::actingAs($kepsek, ['kepsek']);

        $this->deleteJson('/api/profile/1007/foto')->assertForbidden();

        $this->assertSame('siswa/existing.jpg', $siswa->fresh()->foto_profile);
    }

    public function test_admin_can_update_siswa_foto(): void
    {
        Storage::fake('public');
        $siswa = Siswa::factory()->create(['nis' => '1008']);

        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin, ['admin']);

        $this->putJson('/api/profile/1008/foto', [
            'foto' => UploadedFile::fake()->create('foto.jpg', 10, 'image/jpeg'),
        ])->assertOk();

        $this->assertNotNull($siswa->fresh()->foto_profile);
    }

    public function test_guru_and_kepsek_can_still_read_siswa_profile(): void
    {
        $siswa = Siswa::factory()->create(['nis' => '1009']);

        $guru = GuruBk::factory()->create();
        Sanctum::actingAs($guru, ['guru']);
        $this->getJson('/api/profile/1009')->assertOk();

        $kepsek = Kepsek::factory()->create();
        Sanctum::actingAs($kepsek, ['kepsek']);
        $this->getJson('/api/profile/1009')->assertOk();
    }
}
