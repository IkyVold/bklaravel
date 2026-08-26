<?php

namespace Tests\Feature\Api;

use App\Models\GuruBk;
use App\Models\Konseling;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ListByGuruNameFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_guru_dengan_nama_sama_tidak_saling_melihat_record_berdasarkan_guru_id(): void
    {
        $siswa = Siswa::factory()->create();

        $guruA = GuruBk::factory()->create(['nama' => 'Ahmad']);
        $guruB = GuruBk::factory()->create(['nama' => 'Ahmad']);

        $rowA = Konseling::factory()->create([
            'siswa_id' => $siswa->id,
            'guru_id' => $guruA->id,
            'guru_bk' => $guruA->nama,
        ]);

        // Guru B login dan minta daftar konsultasinya sendiri.
        Sanctum::actingAs($guruB, ['guru']);

        $response = $this->getJson('/api/konseling-bk')->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertNotContains($rowA->id, $ids, 'Guru B tidak boleh melihat konsultasi milik Guru A hanya karena nama sama.');
    }

    public function test_guru_tetap_melihat_konsultasi_miliknya_sendiri_berdasarkan_guru_id(): void
    {
        $siswa = Siswa::factory()->create();
        $guruA = GuruBk::factory()->create(['nama' => 'Ahmad']);
        $guruB = GuruBk::factory()->create(['nama' => 'Ahmad']);

        $rowB = Konseling::factory()->create([
            'siswa_id' => $siswa->id,
            'guru_id' => $guruB->id,
            'guru_bk' => $guruB->nama,
        ]);

        Sanctum::actingAs($guruB, ['guru']);

        $response = $this->getJson('/api/konseling-bk')->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertContains($rowB->id, $ids);
    }

    public function test_data_lama_tanpa_guru_id_tetap_terlihat_lewat_fallback_nama(): void
    {
        $siswa = Siswa::factory()->create();
        $guru = GuruBk::factory()->create(['nama' => 'Budi Santoso']);

        // Data lama: belum punya guru_id sama sekali.
        $rowLama = Konseling::factory()->create([
            'siswa_id' => $siswa->id,
            'guru_id' => null,
            'guru_bk' => 'Budi Santoso',
        ]);

        Sanctum::actingAs($guru, ['guru']);

        $response = $this->getJson('/api/konseling-bk')->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertContains($rowLama->id, $ids);
    }
}
