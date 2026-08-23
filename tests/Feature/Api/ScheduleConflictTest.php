<?php

namespace Tests\Feature\Api;

use App\Models\GuruBk;
use App\Models\Konseling;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Menutup poin revisi: sebelum ada ScheduleService, walk-in dan jadwal
 * rutin tidak dicek bentrok sama sekali, dan aturan bentrok antar
 * controller bisa berbeda-beda. Sekarang satu Guru BK tidak boleh punya
 * dua sesi aktif pada tanggal & jam yang sama.
 */
class ScheduleConflictTest extends TestCase
{
    use RefreshDatabase;

    public function test_pengajuan_bentrok_dengan_konsultasi_aktif_guru_yang_sama_ditolak(): void
    {
        $guru = GuruBk::factory()->create();
        $siswaLama = Siswa::factory()->create();
        $siswaBaru = Siswa::factory()->create();

        Konseling::factory()->create([
            'siswa_id' => $siswaLama->id,
            'guru_id' => $guru->id,
            'guru_bk' => $guru->nama,
            'tanggal' => now()->addDays(30)->toDateString(),
            'jam' => '09:00:00',
            'status' => 'Menunggu',
        ]);

        Sanctum::actingAs($siswaBaru, ['siswa']);

        $this->postJson('/api/konseling', [
            'nis' => $siswaBaru->nis,
            'guru_id' => $guru->id,
            'tanggal' => now()->addDays(30)->toDateString(),
            'jam' => '09:00:00',
            'jenis' => 'Luring',
            'kategori' => 'Akademik',
            'deskripsi' => str_repeat('Deskripsi pengajuan konseling. ', 2),
        ])->assertStatus(409);
    }

    public function test_pengajuan_pada_jam_kosong_tidak_ditolak(): void
    {
        $guru = GuruBk::factory()->create();
        $siswaLama = Siswa::factory()->create();
        $siswaBaru = Siswa::factory()->create();

        Konseling::factory()->create([
            'siswa_id' => $siswaLama->id,
            'guru_id' => $guru->id,
            'guru_bk' => $guru->nama,
            'tanggal' => now()->addDays(30)->toDateString(),
            'jam' => '09:00:00',
            'status' => 'Menunggu',
        ]);

        Sanctum::actingAs($siswaBaru, ['siswa']);

        $this->postJson('/api/konseling', [
            'nis' => $siswaBaru->nis,
            'guru_id' => $guru->id,
            'tanggal' => now()->addDays(30)->toDateString(),
            'jam' => '13:00:00',
            'jenis' => 'Luring',
            'kategori' => 'Akademik',
            'deskripsi' => str_repeat('Deskripsi pengajuan konseling. ', 2),
        ])->assertCreated();
    }

    public function test_konsultasi_yang_sudah_dibatalkan_tidak_dihitung_bentrok(): void
    {
        $guru = GuruBk::factory()->create();
        $siswaLama = Siswa::factory()->create();
        $siswaBaru = Siswa::factory()->create();

        Konseling::factory()->create([
            'siswa_id' => $siswaLama->id,
            'guru_id' => $guru->id,
            'guru_bk' => $guru->nama,
            'tanggal' => now()->addDays(30)->toDateString(),
            'jam' => '09:00:00',
            'status' => 'Dibatalkan',
        ]);

        Sanctum::actingAs($siswaBaru, ['siswa']);

        $this->postJson('/api/konseling', [
            'nis' => $siswaBaru->nis,
            'guru_id' => $guru->id,
            'tanggal' => now()->addDays(30)->toDateString(),
            'jam' => '09:00:00',
            'jenis' => 'Luring',
            'kategori' => 'Akademik',
            'deskripsi' => str_repeat('Deskripsi pengajuan konseling. ', 2),
        ])->assertCreated();
    }
}
