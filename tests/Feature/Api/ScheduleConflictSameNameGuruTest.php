<?php

namespace Tests\Feature\Api;

use App\Models\GuruBk;
use App\Models\Konseling;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * PERBAIKAN (revisi 25 Agustus 2026, poin 8): ScheduleService::hasConflict()
 * dulu memfilter guru dengan OR independen (guru_id COCOK ATAU guru_bk
 * COCOK NAMA). Kalau ada dua Guru BK dengan nama sama persis, jadwal
 * milik Guru A bisa dianggap bentrok dengan permintaan jadwal Guru B
 * hanya karena namanya kebetulan sama, walau guru_id keduanya berbeda.
 * Sekarang begitu guru_id terisi, itu satu-satunya sumber kebenaran;
 * fallback nama hanya dipakai untuk data lama yang guru_id-nya null.
 */
class ScheduleConflictSameNameGuruTest extends TestCase
{
    use RefreshDatabase;

    public function test_jadwal_guru_lain_bernama_sama_tidak_dianggap_bentrok(): void
    {
        $siswaLama = Siswa::factory()->create();
        $siswaBaru = Siswa::factory()->create();

        $guruA = GuruBk::factory()->create(['nama' => 'Ahmad']);
        $guruB = GuruBk::factory()->create(['nama' => 'Ahmad']);

        // Guru A sudah punya sesi jam 10.00 pada tanggal tsb.
        Konseling::factory()->create([
            'siswa_id' => $siswaLama->id,
            'guru_id' => $guruA->id,
            'guru_bk' => $guruA->nama,
            'tanggal' => now()->addDays(30)->toDateString(),
            'jam' => '10:00',
            'status' => 'Menunggu',
        ]);

        // Guru B (nama sama, id beda) mencoba membuat sesi jam 10.00 juga —
        // seharusnya TIDAK dianggap bentrok, karena guru_id berbeda.
        Sanctum::actingAs($siswaBaru, ['siswa']);

        $this->postJson('/api/konseling', [
            'nis' => $siswaBaru->nis,
            'guru_id' => $guruB->id,
            'tanggal' => now()->addDays(30)->toDateString(),
            'jam' => '10:00',
            'jenis' => 'Luring',
            'kategori' => 'Akademik',
            'deskripsi' => str_repeat('Deskripsi pengajuan konseling. ', 2),
        ])->assertCreated();
    }

    public function test_jadwal_guru_yang_sama_tetap_bentrok_walau_guru_id_sama(): void
    {
        $siswaLama = Siswa::factory()->create();
        $siswaBaru = Siswa::factory()->create();
        $guru = GuruBk::factory()->create(['nama' => 'Ahmad']);

        Konseling::factory()->create([
            'siswa_id' => $siswaLama->id,
            'guru_id' => $guru->id,
            'guru_bk' => $guru->nama,
            'tanggal' => now()->addDays(30)->toDateString(),
            'jam' => '10:00',
            'status' => 'Menunggu',
        ]);

        Sanctum::actingAs($siswaBaru, ['siswa']);

        $this->postJson('/api/konseling', [
            'nis' => $siswaBaru->nis,
            'guru_id' => $guru->id,
            'tanggal' => now()->addDays(30)->toDateString(),
            'jam' => '10:00',
            'jenis' => 'Luring',
            'kategori' => 'Akademik',
            'deskripsi' => str_repeat('Deskripsi pengajuan konseling. ', 2),
        ])->assertStatus(409);
    }

    public function test_jadwal_data_lama_tanpa_guru_id_tetap_bentrok_lewat_fallback_nama(): void
    {
        $siswaLama = Siswa::factory()->create();
        $siswaBaru = Siswa::factory()->create();
        $guru = GuruBk::factory()->create(['nama' => 'Budi Santoso']);

        // Data lama: belum punya guru_id sama sekali.
        Konseling::factory()->create([
            'siswa_id' => $siswaLama->id,
            'guru_id' => null,
            'guru_bk' => 'Budi Santoso',
            'tanggal' => now()->addDays(30)->toDateString(),
            'jam' => '10:00',
            'status' => 'Menunggu',
        ]);

        Sanctum::actingAs($siswaBaru, ['siswa']);

        $this->postJson('/api/konseling', [
            'nis' => $siswaBaru->nis,
            'guru_id' => $guru->id,
            'tanggal' => now()->addDays(30)->toDateString(),
            'jam' => '10:00',
            'jenis' => 'Luring',
            'kategori' => 'Akademik',
            'deskripsi' => str_repeat('Deskripsi pengajuan konseling. ', 2),
        ])->assertStatus(409);
    }
}
