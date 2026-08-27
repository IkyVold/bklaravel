<?php

namespace Tests\Feature\Web;

use Tests\TestCase;

/**
 * Menutup poin revisi 26 Agustus 2026 #5: "DOM XSS pada preview import
 * absensi Excel". Nilai sec.label/sec.sheet berasal dari isi file Excel
 * yang di-upload Guru BK, dan dulu langsung dirakit ke string HTML lewat
 * row.innerHTML — file Excel yang dibuat khusus bisa menyisipkan HTML/JS
 * berbahaya ke label kelas dan tereksekusi di browser Guru BK.
 *
 * Tidak ada Laravel Dusk di project ini untuk menjalankan JS sungguhan
 * di browser, jadi test ini memverifikasi pada level sumber: pola
 * innerHTML yang merakit sec.label/sec.sheet sebagai string HTML sudah
 * tidak ada lagi, dan nilai tersebut sekarang diisi lewat textContent.
 */
class SiswaIndexAbsenPreviewXssTest extends TestCase
{
    /**
     * PERBAIKAN: sebelumnya method ini bernama blade() dan dideklarasikan
     * private. Illuminate\Foundation\Testing\TestCase (lewat trait
     * InteractsWithViews) sudah punya method bawaan bernama sama dengan
     * visibilitas protected — override memakai visibilitas yang lebih
     * ketat (private) menyebabkan Fatal error saat class ini di-load
     * ("Access level ... must be protected or weaker"). Diganti nama jadi
     * bladeSource() supaya tidak menabrak method bawaan framework.
     */
    private function bladeSource(): string
    {
        return file_get_contents(resource_path('views/guru/siswa-index.blade.php'));
    }

    public function test_preview_mapping_absen_tidak_lagi_merakit_data_excel_ke_innerhtml(): void
    {
        $js = $this->bladeSource();

        $this->assertStringNotContainsString(
            "row.innerHTML = '<div style=\"flex:1\"><strong>' + sec.label",
            $js,
            'Pola innerHTML lama yang merakit sec.label sebagai string HTML seharusnya sudah dihapus.'
        );
    }

    public function test_preview_mapping_absen_memakai_textcontent_untuk_label_dan_sheet(): void
    {
        $js = $this->bladeSource();

        $this->assertStringContainsString('strong.textContent = sec.label;', $js);
        $this->assertStringContainsString('span.textContent = sec.siswa.length', $js);
    }
}
