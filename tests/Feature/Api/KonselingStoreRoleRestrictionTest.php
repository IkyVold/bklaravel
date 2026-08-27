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
 * Menutup poin revisi 25 Agustus 2026 #4: "Endpoint pengajuan konsultasi
 * API terlalu luas untuk staff". Sebelumnya POST /api/konseling (pengajuan
 * konsultasi REGULER oleh siswa) tidak punya middleware ability sama
 * sekali dan hanya bergantung pada assertSiswaOwns() di controller — yang
 * memang sengaja meloloskan seluruh staff (Guru BK, Kepsek, Admin) untuk
 * ownership check. Akibatnya staff bisa membuat pengajuan konsultasi
 * reguler atas nama siswa mana pun, padahal Guru BK sudah punya endpoint
 * khusus (/api/konseling/walkin) dan Kepsek/Admin tidak punya alasan
 * bisnis untuk itu. Sekarang endpoint ini dikunci di dua lapis: middleware
 * route 'ability:siswa' dan pengecekan eksplisit isStaff() di controller.
 */
class KonselingStoreRoleRestrictionTest extends TestCase
{
    use RefreshDatabase;

    private function payload(Siswa $siswa, GuruBk $guru): array
    {
        return [
            'nis' => $siswa->nis,
            'guru_id' => $guru->id,
            'tanggal' => now()->addDays(3)->toDateString(),
            'jam' => '10:00',
            'jenis' => 'Luring',
            'kategori' => 'Akademik',
            'deskripsi' => str_repeat('Deskripsi pengajuan konseling. ', 2),
        ];
    }

    public function test_guru_bk_tidak_bisa_mengajukan_konsultasi_reguler_atas_nama_siswa(): void
    {
        $siswa = Siswa::factory()->create();
        $guru = GuruBk::factory()->create();

        Sanctum::actingAs($guru, ['guru']);

        $this->postJson('/api/konseling', $this->payload($siswa, $guru))
            ->assertForbidden();

        $this->assertDatabaseCount('konseling', 0);
    }

    public function test_kepsek_tidak_bisa_mengajukan_konsultasi_atas_nama_siswa(): void
    {
        $siswa = Siswa::factory()->create();
        $guru = GuruBk::factory()->create();
        $kepsek = Kepsek::factory()->create();

        Sanctum::actingAs($kepsek, ['kepsek']);

        $this->postJson('/api/konseling', $this->payload($siswa, $guru))
            ->assertForbidden();

        $this->assertDatabaseCount('konseling', 0);
    }

    public function test_admin_tidak_bisa_mengajukan_konsultasi_atas_nama_siswa(): void
    {
        $siswa = Siswa::factory()->create();
        $guru = GuruBk::factory()->create();
        $admin = Admin::factory()->create();

        Sanctum::actingAs($admin, ['admin']);

        $this->postJson('/api/konseling', $this->payload($siswa, $guru))
            ->assertForbidden();

        $this->assertDatabaseCount('konseling', 0);
    }

    public function test_siswa_tetap_bisa_mengajukan_konsultasi_untuk_dirinya_sendiri(): void
    {
        $siswa = Siswa::factory()->create();
        $guru = GuruBk::factory()->create();

        Sanctum::actingAs($siswa, ['siswa']);

        $this->postJson('/api/konseling', $this->payload($siswa, $guru))
            ->assertCreated();

        $this->assertDatabaseCount('konseling', 1);
    }

    public function test_siswa_tetap_tidak_bisa_mengajukan_konsultasi_atas_nama_siswa_lain(): void
    {
        // Regression guard: pembatasan staff di poin 4 tidak boleh
        // melonggarkan ownership check siswa yang sudah benar
        // (assertSiswaOwns() tetap dipanggil setelah gerbang staff).
        $pemilik = Siswa::factory()->create();
        $penyerang = Siswa::factory()->create();
        $guru = GuruBk::factory()->create();

        Sanctum::actingAs($penyerang, ['siswa']);

        $this->postJson('/api/konseling', $this->payload($pemilik, $guru))
            ->assertForbidden();

        $this->assertDatabaseCount('konseling', 0);
    }
}
