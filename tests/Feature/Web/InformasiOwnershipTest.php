<?php

namespace Tests\Feature\Web;

use App\Models\GuruBk;
use App\Models\InformasiBk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InformasiOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private function loginAsGuru(GuruBk $guru): void
    {
        $this->withSession([
            'auth_role' => 'guru',
            'auth_id' => $guru->id,
            'auth_user' => ['username' => $guru->username, 'nama' => $guru->nama],
        ]);
    }

    public function test_guru_lain_tidak_bisa_membuka_form_edit_informasi_guru_pemilik(): void
    {
        $guruA = GuruBk::factory()->create(['nama' => 'Guru A']);
        $guruB = GuruBk::factory()->create(['nama' => 'Guru B']);

        $info = InformasiBk::create([
            'judul' => 'Judul Asli', 'kategori' => 'Umum', 'isi' => 'Isi asli.',
            'guru_bk' => $guruA->nama, 'guru_id' => $guruA->id,
        ]);

        $this->loginAsGuru($guruB);

        $this->get(route('guru.informasi.edit', $info->id))->assertStatus(403);
    }

    public function test_guru_lain_tidak_bisa_mengubah_informasi_guru_pemilik(): void
    {
        $guruA = GuruBk::factory()->create(['nama' => 'Guru A']);
        $guruB = GuruBk::factory()->create(['nama' => 'Guru B']);

        $info = InformasiBk::create([
            'judul' => 'Judul Asli', 'kategori' => 'Umum', 'isi' => 'Isi asli.',
            'guru_bk' => $guruA->nama, 'guru_id' => $guruA->id,
        ]);

        $this->loginAsGuru($guruB);

        $this->put(route('guru.informasi.update', $info->id), [
            'judul' => 'Judul Diubah Guru Lain', 'kategori' => 'Umum', 'isi' => 'Isi diubah.',
        ])->assertStatus(403);

        $this->assertDatabaseHas('informasi_bk', ['id' => $info->id, 'judul' => 'Judul Asli']);
    }

    public function test_guru_lain_tidak_bisa_menghapus_informasi_guru_pemilik(): void
    {
        $guruA = GuruBk::factory()->create(['nama' => 'Guru A']);
        $guruB = GuruBk::factory()->create(['nama' => 'Guru B']);

        $info = InformasiBk::create([
            'judul' => 'Judul Asli', 'kategori' => 'Umum', 'isi' => 'Isi asli.',
            'guru_bk' => $guruA->nama, 'guru_id' => $guruA->id,
        ]);

        $this->loginAsGuru($guruB);

        $this->delete(route('guru.informasi.destroy', $info->id))->assertStatus(403);
        $this->assertDatabaseHas('informasi_bk', ['id' => $info->id]);
    }

    public function test_guru_pemilik_tetap_bisa_mengubah_dan_menghapus_informasinya_sendiri(): void
    {
        $guru = GuruBk::factory()->create(['nama' => 'Guru Pemilik']);

        $info = InformasiBk::create([
            'judul' => 'Judul Asli', 'kategori' => 'Umum', 'isi' => 'Isi asli.',
            'guru_bk' => $guru->nama, 'guru_id' => $guru->id,
        ]);

        $this->loginAsGuru($guru);

        $this->put(route('guru.informasi.update', $info->id), [
            'judul' => 'Judul Baru', 'kategori' => 'Umum', 'isi' => 'Isi baru.',
        ])->assertRedirect();
        $this->assertDatabaseHas('informasi_bk', ['id' => $info->id, 'judul' => 'Judul Baru']);

        $this->delete(route('guru.informasi.destroy', $info->id))->assertRedirect();
        $this->assertDatabaseMissing('informasi_bk', ['id' => $info->id]);
    }
}
