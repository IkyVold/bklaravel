<?php

namespace Tests\Feature\Api;

use App\Models\Admin;
use App\Models\GuruBk;
use App\Models\InformasiBk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InformasiAttributionTest extends TestCase
{
    use RefreshDatabase;

    public function test_guru_tidak_bisa_memalsukan_nama_guru_lain_saat_membuat_informasi(): void
    {
        $guruA = GuruBk::factory()->create(['nama' => 'Guru A']);
        GuruBk::factory()->create(['nama' => 'Guru B']);

        Sanctum::actingAs($guruA, ['guru']);

        $response = $this->postJson('/api/informasi', [
            'judul' => 'Judul Informasi',
            'kategori' => 'Umum',
            'isi' => 'Isi informasi contoh.',
            'guru_bk' => 'Guru B',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('informasi_bk', [
            'judul' => 'Judul Informasi',
            'guru_bk' => 'Guru A',
        ]);
        $this->assertDatabaseMissing('informasi_bk', ['guru_bk' => 'Guru B']);
    }

    public function test_guru_tidak_bisa_memalsukan_nama_guru_lain_saat_edit_informasi(): void
    {
        $guruA = GuruBk::factory()->create(['nama' => 'Guru A']);
        $info = InformasiBk::create([
            'judul' => 'Judul Lama',
            'kategori' => 'Umum',
            'isi' => 'Isi lama.',
            'guru_bk' => 'Guru A',
        ]);

        Sanctum::actingAs($guruA, ['guru']);

        $response = $this->putJson('/api/informasi/' . $info->id, [
            'judul' => 'Judul Baru',
            'kategori' => 'Umum',
            'isi' => 'Isi baru.',
            'guru_bk' => 'Guru B',
        ]);

        $response->assertOk();
        $info->refresh();
        $this->assertSame('Judul Baru', $info->judul);
        $this->assertSame('Guru A', $info->guru_bk);
    }

    public function test_admin_wajib_menunjuk_guru_id_yang_valid_saat_membuat_informasi(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin, ['admin']);

        $response = $this->postJson('/api/informasi', [
            'judul' => 'Judul Informasi',
            'kategori' => 'Umum',
            'isi' => 'Isi informasi contoh.',
        ]);

        $response->assertStatus(400);
        $this->assertDatabaseCount('informasi_bk', 0);
    }

    public function test_admin_tidak_bisa_menulis_guru_bk_bebas_hanya_bisa_lewat_guru_id(): void
    {
        $guru = GuruBk::factory()->create(['nama' => 'Guru Asli']);
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin, ['admin']);

        $response = $this->postJson('/api/informasi', [
            'judul' => 'Judul Informasi',
            'kategori' => 'Umum',
            'isi' => 'Isi informasi contoh.',
            'guru_id' => $guru->id,
            'guru_bk' => 'Nama Sembarang Dari Client',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('informasi_bk', [
            'judul' => 'Judul Informasi',
            'guru_bk' => 'Guru Asli',
        ]);
    }

    public function test_admin_tidak_bisa_membuat_informasi_atas_nama_guru_yang_tidak_ada(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin, ['admin']);

        $response = $this->postJson('/api/informasi', [
            'judul' => 'Judul Informasi',
            'kategori' => 'Umum',
            'isi' => 'Isi informasi contoh.',
            'guru_id' => 999999,
        ]);

        $response->assertStatus(404);
        $this->assertDatabaseCount('informasi_bk', 0);
    }

    public function test_admin_tidak_bisa_membuat_informasi_atas_nama_guru_nonaktif(): void
    {
        $guru = GuruBk::factory()->create(['is_active' => false]);
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin, ['admin']);

        $response = $this->postJson('/api/informasi', [
            'judul' => 'Judul Informasi',
            'kategori' => 'Umum',
            'isi' => 'Isi informasi contoh.',
            'guru_id' => $guru->id,
        ]);

        $response->assertStatus(400);
        $this->assertDatabaseCount('informasi_bk', 0);
    }
}
