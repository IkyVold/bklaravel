<?php

namespace Tests\Feature\Web;

use Tests\TestCase;

class SiswaIndexAbsenPreviewXssTest extends TestCase
{
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
