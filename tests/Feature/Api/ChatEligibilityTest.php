<?php

namespace Tests\Feature\Api;

use App\Models\ChatMessage;
use App\Models\GuruBk;
use App\Models\Konseling;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatEligibilityTest extends TestCase
{
    use RefreshDatabase;

    private function buatSiswaDanGuru(): array
    {
        return [Siswa::factory()->create(), GuruBk::factory()->create()];
    }

    public function test_chat_ditolak_untuk_konsultasi_luring_meski_sudah_dikonfirmasi(): void
    {
        [$siswa, $guru] = $this->buatSiswaDanGuru();

        $row = Konseling::factory()->create([
            'siswa_id' => $siswa->id,
            'guru_id' => $guru->id,
            'guru_bk' => $guru->nama,
            'jenis' => 'Luring',
            'status' => 'Proses',
            'status_konfirmasi' => 'Dikonfirmasi',
        ]);

        Sanctum::actingAs($siswa, ['siswa']);

        $this->postJson('/api/chat/send', [
            'konseling_id' => $row->id,
            'message' => 'Bisa chat walau tatap muka?',
        ])->assertForbidden();

        $this->assertDatabaseCount('chat_messages', 0);
    }

    public function test_chat_ditolak_jika_belum_dikonfirmasi_walau_daring(): void
    {
        [$siswa, $guru] = $this->buatSiswaDanGuru();

        $row = Konseling::factory()->create([
            'siswa_id' => $siswa->id,
            'guru_id' => $guru->id,
            'guru_bk' => $guru->nama,
            'jenis' => 'Daring',
            'status' => 'Menunggu',
            'status_konfirmasi' => 'Belum Dikonfirmasi',
        ]);

        Sanctum::actingAs($siswa, ['siswa']);

        $this->postJson('/api/chat/send', [
            'konseling_id' => $row->id,
            'message' => 'Duluan chat sebelum dikonfirmasi',
        ])->assertForbidden();

        $this->assertDatabaseCount('chat_messages', 0);
    }

    public function test_guru_juga_tidak_bisa_mulai_chat_sebelum_konfirmasi(): void
    {
        // Aturan berlaku untuk kedua peserta, bukan cuma siswa.
        [$siswa, $guru] = $this->buatSiswaDanGuru();

        $row = Konseling::factory()->create([
            'siswa_id' => $siswa->id,
            'guru_id' => $guru->id,
            'guru_bk' => $guru->nama,
            'jenis' => 'Daring',
            'status' => 'Menunggu',
            'status_konfirmasi' => 'Belum Dikonfirmasi',
        ]);

        Sanctum::actingAs($guru, ['guru']);

        $this->postJson('/api/chat/send', [
            'konseling_id' => $row->id,
            'message' => 'Guru mulai chat duluan',
        ])->assertForbidden();

        $this->assertDatabaseCount('chat_messages', 0);
    }

    public function test_chat_berhasil_jika_daring_dan_sudah_dikonfirmasi(): void
    {
        [$siswa, $guru] = $this->buatSiswaDanGuru();

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
            'message' => 'Sekarang boleh chat',
        ])->assertCreated();

        $this->assertDatabaseCount('chat_messages', 1);
    }

    /**
     * status_konfirmasi bernilai 'Terkonfirmasi'/'Tervalidasi' adalah nilai
     * legacy dari jalur web/data lama (lihat komentar
     * Konseling::STATUS_KONFIRMASI_TERKONFIRMASI) — harus tetap dianggap
     * sah sebagai "sudah dikonfirmasi", bukan cuma 'Dikonfirmasi'.
     */
    public function test_chat_berhasil_dengan_nilai_status_konfirmasi_legacy_dari_web(): void
    {
        [$siswa, $guru] = $this->buatSiswaDanGuru();

        $row = Konseling::factory()->create([
            'siswa_id' => $siswa->id,
            'guru_id' => $guru->id,
            'guru_bk' => $guru->nama,
            'jenis' => 'Daring',
            'status' => 'Proses',
            'status_konfirmasi' => 'Terkonfirmasi',
        ]);

        Sanctum::actingAs($siswa, ['siswa']);

        $this->postJson('/api/chat/send', [
            'konseling_id' => $row->id,
            'message' => 'Pakai status lama dari web',
        ])->assertCreated();
    }

    public function test_walkin_luring_tidak_bisa_chat_meski_langsung_terkonfirmasi(): void
    {
        // Walk-in (input_manual) langsung berstatus Proses/Dikonfirmasi
        // sejak dibuat, tapi kalau jenisnya default Luring tetap tidak
        // boleh chat.
        [$siswa, $guru] = $this->buatSiswaDanGuru();

        $row = Konseling::factory()->create([
            'siswa_id' => $siswa->id,
            'guru_id' => $guru->id,
            'guru_bk' => $guru->nama,
            'jenis' => 'Luring',
            'status' => 'Proses',
            'status_konfirmasi' => 'Dikonfirmasi',
            'input_manual' => true,
        ]);

        Sanctum::actingAs($guru, ['guru']);

        $this->postJson('/api/chat/send', [
            'konseling_id' => $row->id,
            'message' => 'Walk-in tatap muka',
        ])->assertForbidden();
    }

    public function test_history_tetap_bisa_dilihat_meski_belum_memenuhi_syarat_chat(): void
    {
        // Pembatasan poin 4 hanya untuk MENGIRIM pesan (send()), bukan
        // untuk melihat riwayat (history()) — pemilik tetap boleh
        // membuka riwayat (kosong) walau sesi belum eligible untuk chat.
        [$siswa, $guru] = $this->buatSiswaDanGuru();

        $row = Konseling::factory()->create([
            'siswa_id' => $siswa->id,
            'guru_id' => $guru->id,
            'guru_bk' => $guru->nama,
            'jenis' => 'Luring',
            'status' => 'Menunggu',
            'status_konfirmasi' => 'Belum Dikonfirmasi',
        ]);

        Sanctum::actingAs($siswa, ['siswa']);

        $this->getJson('/api/chat/history?konseling_id=' . $row->id)
            ->assertOk()
            ->assertJson(['success' => true, 'data' => []]);
    }
}
