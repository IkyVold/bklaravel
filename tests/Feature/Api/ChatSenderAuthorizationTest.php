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

/**
 * Menutup poin revisi 24 Agustus 2026 #2 & #3: "Kepala Sekolah/Admin dapat
 * masuk dan mengirim pesan pada chat konseling" — sebelumnya ChatController
 * @send memakai assertCanViewKonseling() (yang sengaja meloloskan admin &
 * kepsek untuk monitoring) sebagai gerbang kirim pesan juga, dan pesan yang
 * terkirim dari admin/kepsek dicatat seolah-olah dari 'guru'. Sekarang kirim
 * pesan memakai assertCanChatKonseling(), yang hanya meloloskan siswa
 * pemilik dan Guru BK pemilik.
 *
 * Juga menutup poin revisi 25 Agustus 2026 #2: "Kepala Sekolah dan Admin
 * masih dapat membaca isi chat konsultasi" — history() sebelumnya masih
 * memakai assertCanViewKonseling() sehingga walau admin/kepsek sudah tidak
 * bisa MENGIRIM pesan, mereka tetap bisa MEMBACA seluruh isi chat. Sekarang
 * history() memakai assertCanReadChatKonseling(), aturan yang sama dengan
 * hak mengirim pesan (hanya siswa pemilik & Guru BK pemilik).
 */
class ChatSenderAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function buatKonseling(): array
    {
        $siswa = Siswa::factory()->create();
        $guru = GuruBk::factory()->create();
        // jenis Daring + sudah dikonfirmasi: prasyarat chat sejak revisi
        // 24 Agustus 2026 poin 4, supaya kasus-kasus di file ini murni
        // menguji otorisasi pengirim (bukan ikut tersandung aturan
        // kelayakan chat yang diuji terpisah di ChatEligibilityTest).
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
        // PERBAIKAN (revisi 25 Agustus 2026, poin 2): dulu test ini
        // menegaskan sebaliknya (admin/kepsek "tetap bisa melihat history
        // untuk monitoring") karena history() memakai
        // assertCanViewKonseling() yang sengaja meloloskan admin/kepsek.
        // Isi chat ternyata bagian dari isi konsultasi yang menurut UI
        // siswa hanya boleh dilihat siswa & Guru BK yang dipilih — jadi
        // sekarang history() memakai assertCanReadChatKonseling(), yang
        // hanya meloloskan siswa pemilik dan Guru BK pemilik. Hak MELIHAT
        // data administratif konseling (jadwal/status, lewat
        // assertCanViewKonseling()) tetap ada; yang dicabut khusus akses
        // ke ISI CHAT.
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
