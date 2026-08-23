<?php

namespace Tests\Feature\Web;

use App\Models\GuruBk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Menutup poin revisi: JadwalRutinController dulu hanya memvalidasi format
 * jam, bukan interval-nya — slot 10.00-09.00 atau dua slot yang saling
 * overlap (08.00-10.00 & 09.00-11.00) masih bisa tersimpan.
 */
class JadwalRutinOverlapTest extends TestCase
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

    public function test_jam_selesai_harus_lebih_besar_dari_jam_mulai(): void
    {
        $guru = GuruBk::factory()->create();
        $this->loginAsGuru($guru);

        $this->post(route('guru.jadwal-rutin.store'), [
            'hari' => 1,
            'jam_mulai' => '10:00',
            'jam_selesai' => '09:00',
            'jenis' => 'Luring',
        ])->assertSessionHasErrors('jam_selesai');

        $this->assertDatabaseCount('jadwal_rutin', 0);
    }

    public function test_slot_yang_overlap_pada_hari_sama_ditolak(): void
    {
        $guru = GuruBk::factory()->create();
        $this->loginAsGuru($guru);

        $this->post(route('guru.jadwal-rutin.store'), [
            'hari' => 1,
            'jam_mulai' => '08:00',
            'jam_selesai' => '10:00',
            'jenis' => 'Luring',
        ])->assertSessionDoesntHaveErrors();

        // 09.00-11.00 overlap dengan slot 08.00-10.00 yang baru dibuat.
        $this->post(route('guru.jadwal-rutin.store'), [
            'hari' => 1,
            'jam_mulai' => '09:00',
            'jam_selesai' => '11:00',
            'jenis' => 'Luring',
        ])->assertSessionHasErrors('jam_mulai');

        $this->assertDatabaseCount('jadwal_rutin', 1);
    }

    public function test_slot_yang_tidak_overlap_diterima(): void
    {
        $guru = GuruBk::factory()->create();
        $this->loginAsGuru($guru);

        $this->post(route('guru.jadwal-rutin.store'), [
            'hari' => 1,
            'jam_mulai' => '08:00',
            'jam_selesai' => '10:00',
            'jenis' => 'Luring',
        ])->assertSessionDoesntHaveErrors();

        $this->post(route('guru.jadwal-rutin.store'), [
            'hari' => 1,
            'jam_mulai' => '10:00',
            'jam_selesai' => '12:00',
            'jenis' => 'Luring',
        ])->assertSessionDoesntHaveErrors();

        $this->assertDatabaseCount('jadwal_rutin', 2);
    }
}
