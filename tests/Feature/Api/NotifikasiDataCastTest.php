<?php

namespace Tests\Feature\Api;

use App\Models\GuruBk;
use App\Models\Konseling;
use App\Models\Notifikasi;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotifikasiDataCastTest extends TestCase
{
    use RefreshDatabase;

    public function test_konfirmasi_menyimpan_data_notifikasi_sebagai_array_bukan_json_string(): void
    {
        $siswa = Siswa::factory()->create();
        $guru = GuruBk::factory()->create();
        $row = Konseling::factory()->create([
            'siswa_id' => $siswa->id,
            'guru_id' => $guru->id,
            'guru_bk' => $guru->nama,
            'status' => 'Menunggu',
        ]);

        Sanctum::actingAs($guru, ['guru']);

        $this->putJson("/api/konseling/{$row->id}/konfirmasi", [
            'status_konfirmasi' => 'Dikonfirmasi',
        ])->assertOk();

        $notif = Notifikasi::where('penerima_id', $siswa->nis)
            ->where('penerima_role', 'siswa')
            ->latest('id')
            ->first();

        $this->assertNotNull($notif);
        $this->assertIsArray($notif->data);
        $this->assertSame($row->id, $notif->data['konseling_id']);
        $this->assertSame($row->id, $notif->konseling_id);
    }

    public function test_update_status_dibatalkan_menyimpan_data_notifikasi_sebagai_array(): void
    {
        $siswa = Siswa::factory()->create();
        $guru = GuruBk::factory()->create();
        $row = Konseling::factory()->create([
            'siswa_id' => $siswa->id,
            'guru_id' => $guru->id,
            'guru_bk' => $guru->nama,
            'status' => 'Menunggu',
        ]);

        Sanctum::actingAs($guru, ['guru']);

        $this->putJson("/api/konseling/{$row->id}/status", [
            'status' => 'Dibatalkan',
            'alasan_batal' => 'Siswa mengundurkan diri',
        ])->assertOk();

        $notif = Notifikasi::where('penerima_id', $siswa->nis)
            ->where('penerima_role', 'siswa')
            ->latest('id')
            ->first();

        $this->assertNotNull($notif);
        $this->assertIsArray($notif->data);
        $this->assertSame($row->id, $notif->data['konseling_id']);
    }
}
