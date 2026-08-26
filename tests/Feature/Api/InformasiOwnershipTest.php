<?php

namespace Tests\Feature\Api;

use App\Models\Admin;
use App\Models\GuruBk;
use App\Models\InformasiBk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InformasiOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_guru_lain_tidak_bisa_mengubah_informasi_milik_guru_pemilik(): void
    {
        $guruA = GuruBk::factory()->create(['nama' => 'Guru A']);
        $guruB = GuruBk::factory()->create(['nama' => 'Guru B']);

        $info = InformasiBk::create([
            'judul' => 'Judul Asli',
            'kategori' => 'Umum',
            'isi' => 'Isi asli.',
            'guru_bk' => $guruA->nama,
            'guru_id' => $guruA->id,
        ]);

        Sanctum::actingAs($guruB, ['guru']);

        $response = $this->putJson('/api/informasi/' . $info->id, [
            'judul' => 'Judul Diubah Guru Lain',
            'kategori' => 'Umum',
            'isi' => 'Isi diubah.',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('informasi_bk', ['id' => $info->id, 'judul' => 'Judul Asli']);
    }

    public function test_guru_lain_tidak_bisa_menghapus_informasi_milik_guru_pemilik(): void
    {
        $guruA = GuruBk::factory()->create(['nama' => 'Guru A']);
        $guruB = GuruBk::factory()->create(['nama' => 'Guru B']);

        $info = InformasiBk::create([
            'judul' => 'Judul Asli',
            'kategori' => 'Umum',
            'isi' => 'Isi asli.',
            'guru_bk' => $guruA->nama,
            'guru_id' => $guruA->id,
        ]);

        Sanctum::actingAs($guruB, ['guru']);

        $response = $this->deleteJson('/api/informasi/' . $info->id);

        $response->assertStatus(403);
        $this->assertDatabaseHas('informasi_bk', ['id' => $info->id]);
    }

    public function test_guru_pemilik_tetap_bisa_mengubah_dan_menghapus_informasinya_sendiri(): void
    {
        $guru = GuruBk::factory()->create(['nama' => 'Guru Pemilik']);

        $info = InformasiBk::create([
            'judul' => 'Judul Asli',
            'kategori' => 'Umum',
            'isi' => 'Isi asli.',
            'guru_bk' => $guru->nama,
            'guru_id' => $guru->id,
        ]);

        Sanctum::actingAs($guru, ['guru']);

        $this->putJson('/api/informasi/' . $info->id, [
            'judul' => 'Judul Baru',
            'kategori' => 'Umum',
            'isi' => 'Isi baru.',
        ])->assertOk();
        $this->assertDatabaseHas('informasi_bk', ['id' => $info->id, 'judul' => 'Judul Baru']);

        $this->deleteJson('/api/informasi/' . $info->id)->assertOk();
        $this->assertDatabaseMissing('informasi_bk', ['id' => $info->id]);
    }

    public function test_admin_tetap_bisa_mengelola_informasi_milik_guru_manapun(): void
    {
        $guru = GuruBk::factory()->create(['nama' => 'Guru Pemilik']);
        $admin = Admin::factory()->create();

        $info = InformasiBk::create([
            'judul' => 'Judul Asli',
            'kategori' => 'Umum',
            'isi' => 'Isi asli.',
            'guru_bk' => $guru->nama,
            'guru_id' => $guru->id,
        ]);

        Sanctum::actingAs($admin, ['admin']);

        $this->putJson('/api/informasi/' . $info->id, [
            'judul' => 'Judul Diubah Admin',
            'kategori' => 'Umum',
            'isi' => 'Isi diubah admin.',
        ])->assertOk();
        $this->assertDatabaseHas('informasi_bk', ['id' => $info->id, 'judul' => 'Judul Diubah Admin']);

        $this->deleteJson('/api/informasi/' . $info->id)->assertOk();
        $this->assertDatabaseMissing('informasi_bk', ['id' => $info->id]);
    }

    public function test_guru_baru_membuat_informasi_menyimpan_guru_id(): void
    {
        $guru = GuruBk::factory()->create(['nama' => 'Guru Baru']);
        Sanctum::actingAs($guru, ['guru']);

        $this->postJson('/api/informasi', [
            'judul' => 'Judul Informasi',
            'kategori' => 'Umum',
            'isi' => 'Isi informasi.',
        ])->assertCreated();

        $this->assertDatabaseHas('informasi_bk', [
            'judul' => 'Judul Informasi',
            'guru_id' => $guru->id,
        ]);
    }
}
