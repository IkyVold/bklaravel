<?php

namespace Tests\Feature\Api;

use App\Models\Admin;
use App\Models\GuruBk;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Menutup poin revisi 27 Agustus 2026 #9: "Validasi kelas API masih tidak
 * menggunakan master kelas". Sebelumnya Api\SiswaController::create() dan
 * importRows() hanya memvalidasi kelas dengan 'string|max:20', sehingga
 * request API bisa membuat siswa dengan kelas bebas apa pun (mis.
 * "KELAS SEMBARANG") meskipun Web\SiswaController sudah menolaknya lewat
 * VALID_KELAS. Sekarang keduanya memvalidasi terhadap App\Support\
 * MasterKelas, sumber yang sama dipakai Web.
 */
class SiswaMasterKelasValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_siswa_menolak_kelas_di_luar_master(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin, ['admin']);

        $this->postJson('/api/siswa', [
            'nis' => '1111',
            'nama' => 'Siswa Kelas Sembarang',
            'kelas' => 'KELAS SEMBARANG',
        ])->assertStatus(400);

        $this->assertDatabaseMissing('siswa', ['nis' => '1111']);
    }

    public function test_create_siswa_menerima_kelas_yang_ada_di_master(): void
    {
        $guru = GuruBk::factory()->create();
        Sanctum::actingAs($guru, ['guru']);

        $this->postJson('/api/siswa', [
            'nis' => '2222',
            'nama' => 'Siswa Kelas Valid',
            'kelas' => 'XI - 3',
        ])->assertCreated();

        $this->assertDatabaseHas('siswa', ['nis' => '2222', 'kelas' => 'XI - 3']);
    }

    public function test_import_rows_melewati_baris_dengan_kelas_di_luar_master(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin, ['admin']);

        $response = $this->postJson('/api/siswa/import-rows', [
            'rows' => [
                ['nis' => '3001', 'nama' => 'Siswa Valid', 'kelas' => 'X - 5'],
                ['nis' => '3002', 'nama' => 'Siswa Tidak Valid', 'kelas' => 'KELAS SEMBARANG'],
            ],
        ])->assertOk();

        $response->assertJsonPath('inserted', 1);
        $response->assertJsonPath('skipped', 1);

        $this->assertDatabaseHas('siswa', ['nis' => '3001', 'kelas' => 'X - 5']);
        $this->assertDatabaseMissing('siswa', ['nis' => '3002']);
    }
}
