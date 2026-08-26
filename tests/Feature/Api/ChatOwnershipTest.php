<?php

namespace Tests\Feature\Api;

use App\Models\ChatMessage;
use App\Models\GuruBk;
use App\Models\Konseling;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_wajib_konseling_id(): void
    {
        $siswa = Siswa::factory()->create();
        Sanctum::actingAs($siswa, ['siswa']);

        $this->postJson('/api/chat/send', [
            'message' => 'Halo, tanpa konseling_id',
        ])->assertStatus(400);
    }

    public function test_siswa_tidak_bisa_kirim_pesan_ke_konsultasi_siswa_lain(): void
    {
        $pemilik = Siswa::factory()->create();
        $penyerang = Siswa::factory()->create();
        $guru = GuruBk::factory()->create();

        $row = Konseling::factory()->create([
            'siswa_id' => $pemilik->id,
            'guru_id' => $guru->id,
            'guru_bk' => $guru->nama,
        ]);

        Sanctum::actingAs($penyerang, ['siswa']);

        $this->postJson('/api/chat/send', [
            'konseling_id' => $row->id,
            'message' => 'Aku bukan pemilik sesi ini',
        ])->assertForbidden();

        $this->assertDatabaseCount('chat_messages', 0);
    }

    public function test_siswa_pemilik_bisa_kirim_pesan_di_konsultasinya_sendiri(): void
    {
        $siswa = Siswa::factory()->create();
        $guru = GuruBk::factory()->create();

        $row = Konseling::factory()->create([
            'siswa_id' => $siswa->id,
            'guru_id' => $guru->id,
            'guru_bk' => $guru->nama,
            'jenis' => 'Daring',
            'status' => 'Proses',
            'status_konfirmasi' => 'Dikonfirmasi',
        ]);

        Sanctum::actingAs($siswa, ['siswa']);

        $this->postJson('/api/chat/send', [
            'konseling_id' => $row->id,
            'message' => 'Halo Bu Guru',
        ])->assertCreated();

        $this->assertDatabaseCount('chat_messages', 1);
        $this->assertSame($row->fresh()->chat_session_id, ChatMessage::first()->session_id);
    }

    public function test_history_wajib_konseling_id_dan_tidak_ada_fallback_session_id(): void
    {
        $siswa = Siswa::factory()->create();
        Sanctum::actingAs($siswa, ['siswa']);

        // Endpoint history TIDAK boleh menerima session_id sebagai
        // pengganti konseling_id (fallback lama yang membuka akses ke
        // session sembarang milik role staff).
        $this->getJson('/api/chat/history?session_id=session-tebakan')
            ->assertStatus(400);
    }
}
