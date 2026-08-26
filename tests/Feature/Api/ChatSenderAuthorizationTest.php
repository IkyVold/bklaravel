<?php

namespace Tests\Feature\Api;

use App\Models\Admin;
use App\Models\ChatMessage;
use App\Models\GuruBk;
use App\Models\Kepsek;
use App\Models\Konseling;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatSenderAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function buatKonseling(): array
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

        return [$row, $siswa, $guru];
    }

    public function test_admin_tidak_bisa_kirim_pesan_chat_konseling(): void
    {
        [$row] = $this->buatKonseling();

        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin, ['admin']);

        $this->postJson('/api/chat/send', [
            'konseling_id' => $row->id,
            'message' => 'Pesan dari admin',
        ])->assertForbidden();

        $this->assertDatabaseCount('chat_messages', 0);
    }

    public function test_kepsek_tidak_bisa_kirim_pesan_chat_konseling(): void
    {
        [$row] = $this->buatKonseling();

        $kepsek = Kepsek::factory()->create();
        Sanctum::actingAs($kepsek, ['kepsek']);

        $this->postJson('/api/chat/send', [
            'konseling_id' => $row->id,
            'message' => 'Pesan dari kepsek',
        ])->assertForbidden();

        $this->assertDatabaseCount('chat_messages', 0);
    }

    public function test_admin_tidak_bisa_membaca_history_chat_konseling(): void
    {
        [$row] = $this->buatKonseling();

        $admin = Admin::factory()->create();
        Sanctum::actingAs($admin, ['admin']);

        $this->getJson('/api/chat/history?konseling_id=' . $row->id)
            ->assertForbidden();
    }

    public function test_kepsek_tidak_bisa_membaca_history_chat_konseling(): void
    {
        [$row] = $this->buatKonseling();

        $kepsek = Kepsek::factory()->create();
        Sanctum::actingAs($kepsek, ['kepsek']);

        $this->getJson('/api/chat/history?konseling_id=' . $row->id)
            ->assertForbidden();
    }

    public function test_siswa_pemilik_tetap_bisa_membaca_history_chat_sendiri(): void
    {
        [$row, $siswa] = $this->buatKonseling();

        Sanctum::actingAs($siswa, ['siswa']);

        $this->getJson('/api/chat/history?konseling_id=' . $row->id)
            ->assertOk();
    }

    public function test_guru_pemilik_tetap_bisa_membaca_history_chat_sendiri(): void
    {
        [$row, , $guru] = $this->buatKonseling();

        Sanctum::actingAs($guru, ['guru']);

        $this->getJson('/api/chat/history?konseling_id=' . $row->id)
            ->assertOk();
    }

    public function test_guru_pemilik_tetap_bisa_kirim_pesan(): void
    {
        [$row, , $guru] = $this->buatKonseling();

        Sanctum::actingAs($guru, ['guru']);

        $this->postJson('/api/chat/send', [
            'konseling_id' => $row->id,
            'message' => 'Balasan dari guru BK',
        ])->assertCreated();

        $this->assertSame('guru', ChatMessage::first()->sender_type);
    }

    public function test_guru_lain_yang_bukan_pemilik_tidak_bisa_kirim_pesan(): void
    {
        [$row] = $this->buatKonseling();
        $guruLain = GuruBk::factory()->create();

        Sanctum::actingAs($guruLain, ['guru']);

        $this->postJson('/api/chat/send', [
            'konseling_id' => $row->id,
            'message' => 'Aku bukan guru BK di sesi ini',
        ])->assertForbidden();
    }
}
