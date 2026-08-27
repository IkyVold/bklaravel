<?php

namespace Tests\Feature\Api;

use App\Models\Admin;
use App\Models\GuruBk;
use App\Models\RiwayatKelas;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * PERBAIKAN (revisi 27 Agustus 2026, poin 3): riwayat_kelas sebelumnya
 * direlasikan ke siswa lewat kolom 'nis' (string, varchar(20) — tidak
 * konsisten dengan siswa.nis yang sudah varchar(4)). Sekarang
 * riwayat_kelas.siswa_id adalah foreign key sesungguhnya ke siswa.id;
 * kolom 'nis' fisik sudah dihapus dari tabel riwayat_kelas. Kontrak API
 * publik (endpoint berbasis {nis} di URL) tetap dipertahankan.
 */
class RiwayatKelasSiswaIdRelationTest extends TestCase
{
    use RefreshDatabase;

    public function test_kolom_nis_fisik_sudah_tidak_ada_di_tabel_riwayat_kelas(): void
    {
        $this->assertFalse(Schema::hasColumn('riwayat_kelas', 'nis'));
        $this->assertTrue(Schema::hasColumn('riwayat_kelas', 'siswa_id'));
    }

    public function test_admin_membuat_riwayat_kelas_tersimpan_via_siswa_id(): void
    {
        $siswa = Siswa::factory()->create(['nis' => '1234']);
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin, ['admin']);

        $response = $this->postJson('/api/riwayat-kelas', [
            'nis' => '1234',
            'tahun_ajaran' => '2025/2026',
            'kelas' => '10 IPA 1',
            'status' => 'aktif',
        ]);

        $response->assertCreated();
        // Kontrak response tetap menyertakan 'nis' walau kolom fisiknya
        // sudah tidak ada — diturunkan lewat accessor dari relasi siswa.
        $response->assertJsonPath('data.nis', '1234');

        $this->assertDatabaseHas('riwayat_kelas', [
            'siswa_id' => $siswa->id,
            'tahun_ajaran' => '2025/2026',
            'kelas' => '10 IPA 1',
        ]);
    }

    public function test_membuat_riwayat_kelas_untuk_nis_yang_tidak_ada_ditolak(): void
    {
        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin, ['admin']);

        $this->postJson('/api/riwayat-kelas', [
            'nis' => '9999',
            'tahun_ajaran' => '2025/2026',
            'kelas' => '10 IPA 1',
        ])->assertStatus(404);
    }

    public function test_list_riwayat_kelas_by_nis_mengembalikan_data_lewat_relasi_siswa_id(): void
    {
        $siswa = Siswa::factory()->create(['nis' => '2222']);
        RiwayatKelas::create(['siswa_id' => $siswa->id, 'tahun_ajaran' => '2024/2025', 'kelas' => '9 A', 'status' => 'arsip']);
        RiwayatKelas::create(['siswa_id' => $siswa->id, 'tahun_ajaran' => '2025/2026', 'kelas' => '10 IPA 1', 'status' => 'aktif']);

        $guru = GuruBk::factory()->create();
        Sanctum::actingAs($guru, ['guru']);

        $response = $this->getJson('/api/riwayat-kelas/2222');
        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.nis', '2222');
    }

    public function test_get_aktif_riwayat_kelas_by_nis(): void
    {
        $siswa = Siswa::factory()->create(['nis' => '3333']);
        RiwayatKelas::create(['siswa_id' => $siswa->id, 'tahun_ajaran' => '2025/2026', 'kelas' => '10 IPA 1', 'status' => 'aktif']);

        $guru = GuruBk::factory()->create();
        Sanctum::actingAs($guru, ['guru']);

        $response = $this->getJson('/api/riwayat-kelas/3333/aktif');
        $response->assertOk();
        $response->assertJsonPath('data.kelas', '10 IPA 1');
        $response->assertJsonPath('data.nis', '3333');
    }

    /**
     * Kalau NIS seorang siswa berubah (mis. diperbaiki lewat manajemen
     * data siswa), riwayat kelas lamanya harus TETAP terhubung ke siswa
     * yang sama karena relasinya lewat siswa_id, bukan string NIS —
     * inilah inti perbaikan poin ini.
     */
    public function test_riwayat_kelas_tetap_terhubung_setelah_nis_siswa_berubah(): void
    {
        $siswa = Siswa::factory()->create(['nis' => '4444']);
        RiwayatKelas::create(['siswa_id' => $siswa->id, 'tahun_ajaran' => '2025/2026', 'kelas' => '10 IPA 1', 'status' => 'aktif']);

        // NIS siswa berubah (mis. dikoreksi Admin).
        $siswa->update(['nis' => '5555']);

        $guru = GuruBk::factory()->create();
        Sanctum::actingAs($guru, ['guru']);

        // NIS lama sudah tidak menemukan riwayat apa pun (bukan error —
        // memang tidak ada siswa dengan NIS itu lagi).
        $this->getJson('/api/riwayat-kelas/4444')->assertOk()->assertJsonCount(0, 'data');

        // NIS baru langsung menemukan riwayat yang SAMA, karena relasinya
        // memakai siswa_id (foreign key), bukan nilai NIS yang sudah
        // berubah.
        $response = $this->getJson('/api/riwayat-kelas/5555');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.kelas', '10 IPA 1');
    }

    public function test_relasi_model_siswa_riwayatkelas_memakai_siswa_id(): void
    {
        $siswa = Siswa::factory()->create(['nis' => '6666']);
        RiwayatKelas::create(['siswa_id' => $siswa->id, 'tahun_ajaran' => '2025/2026', 'kelas' => '10 IPA 1', 'status' => 'aktif']);

        $this->assertCount(1, $siswa->riwayatKelas);
        $this->assertSame('10 IPA 1', $siswa->riwayatKelas->first()->kelas);
    }
}
