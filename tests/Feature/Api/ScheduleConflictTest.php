<?php

namespace Tests\Feature\Api;

use App\Models\GuruBk;
use App\Models\Konseling;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

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

    public function test_pengajuan_overlap_di_tengah_sesi_lain_ditolak(): void
    {
        $guru = GuruBk::factory()->create();
        $siswaLama = Siswa::factory()->create();
        $siswaBaru = Siswa::factory()->create();

        Konseling::factory()->create([
            'siswa_id' => $siswaLama->id,
            'guru_id' => $guru->id,
            'guru_bk' => $guru->nama,
            'tanggal' => now()->addDays(30)->toDateString(),
            'jam' => '10:00:00',
            'status' => 'Menunggu',
        ]);

        Sanctum::actingAs($siswaBaru, ['siswa']);

        $this->postJson('/api/konseling', [
            'nis' => $siswaBaru->nis,
            'guru_id' => $guru->id,
            'tanggal' => now()->addDays(30)->toDateString(),
            'jam' => '10:30:00',
            'jenis' => 'Luring',
            'kategori' => 'Akademik',
            'deskripsi' => str_repeat('Deskripsi pengajuan konseling. ', 2),
        ])->assertStatus(409);
    }

    /**
     * Sesi back-to-back (sesi lama berakhir tepat saat sesi baru mulai)
     * TIDAK dianggap bentrok — batas persis bersentuhan bukan overlap.
     */
    public function test_pengajuan_back_to_back_tepat_setelah_sesi_lain_tidak_ditolak(): void
    {
        $guru = GuruBk::factory()->create();
        $siswaLama = Siswa::factory()->create();
        $siswaBaru = Siswa::factory()->create();

        Konseling::factory()->create([
            'siswa_id' => $siswaLama->id,
            'guru_id' => $guru->id,
            'guru_bk' => $guru->nama,
            'tanggal' => now()->addDays(30)->toDateString(),
            'jam' => '10:00:00', // durasi default 60 menit -> berakhir 11:00
            'status' => 'Menunggu',
        ]);

        Sanctum::actingAs($siswaBaru, ['siswa']);

        $this->postJson('/api/konseling', [
            'nis' => $siswaBaru->nis,
            'guru_id' => $guru->id,
            'tanggal' => now()->addDays(30)->toDateString(),
            'jam' => '11:00:00',
            'jenis' => 'Luring',
            'kategori' => 'Akademik',
            'deskripsi' => str_repeat('Deskripsi pengajuan konseling. ', 2),
        ])->assertCreated();
    }

    /**
     * Durasi eksplisit dikirim client: sesi 09:00 durasi 90 menit (berakhir
     * 10:30) harus bentrok dengan pengajuan baru jam 10:00.
     */
    public function test_pengajuan_bentrok_dengan_durasi_custom_yang_dikirim_client(): void
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
            'durasi_menit' => 90,
            'status' => 'Menunggu',
        ]);

        Sanctum::actingAs($siswaBaru, ['siswa']);

        $this->postJson('/api/konseling', [
            'nis' => $siswaBaru->nis,
            'guru_id' => $guru->id,
            'tanggal' => now()->addDays(30)->toDateString(),
            'jam' => '10:00:00',
            'jenis' => 'Luring',
            'kategori' => 'Akademik',
            'deskripsi' => str_repeat('Deskripsi pengajuan konseling. ', 2),
        ])->assertStatus(409);
    }
}
