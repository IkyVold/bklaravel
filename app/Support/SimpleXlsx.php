<?php

namespace App\Support;

/**
 * Pembaca XLSX minimal (tanpa PhpSpreadsheet) untuk import siswa/absen.
 *
 * PERBAIKAN (revisi 27 Agustus 2026, poin 11): XLSX pada dasarnya adalah
 * ZIP terkompresi. Sebelumnya file diterima berdasarkan ukuran ZIP saja
 * (max:5120 / ~5MB di validator controller), lalu setiap entry ZIP yang
 * relevan langsung dibaca penuh ke memory (getFromName) dan diproses
 * dengan regex atas seluruh isinya. XML sangat mudah dikompresi (rasio
 * ratusan-ribuan kali lipat), jadi file 5MB yang sengaja dibuat sebagai
 * "zip/compression bomb" bisa menghasilkan XML puluhan-ratusan MB (atau
 * lebih) begitu diekstrak — cukup untuk menghabiskan memory/CPU proses
 * PHP walau endpoint ini hanya bisa dipakai Guru BK (bukan publik).
 *
 * Sekarang ditambahkan beberapa batas keras SEBELUM entry ZIP mana pun
 * diekstrak/diproses penuh:
 *   - jumlah sheet yang dibaca dari workbook.xml
 *   - ukuran UNCOMPRESSED tiap entry ZIP relevan (dicek lewat statName(),
 *     bukan dengan mengekstrak dulu baru mengukur)
 *   - jumlah baris (<row>) yang diproses per sheet
 *   - jumlah kolom/cell per baris
 * Begitu salah satu batas terlampaui, proses dihentikan dengan
 * RuntimeException (pesan singkat, tanpa detail internal) — kedua
 * pemanggil (SiswaController@previewAbsen dan pembacaan file umum) sudah
 * membungkus SimpleXlsx dengan try/catch sehingga ini tampil sebagai
 * pesan error biasa ke pengguna, bukan crash/500.
 */
class SimpleXlsx
{
    /** Maksimum jumlah sheet yang mau dibaca dari satu workbook. */
    private const MAX_SHEETS = 20;

    /**
     * Maksimum ukuran UNCOMPRESSED satu entry ZIP (mis. isi sheet XML atau
     * sharedStrings.xml) yang boleh diekstrak ke memory. Nilai ini jauh di
     * atas kebutuhan wajar untuk data absen/siswa sekolah, tapi jauh di
     * bawah apa yang bisa dihasilkan compression bomb dari file 5MB.
     */
    private const MAX_UNCOMPRESSED_ENTRY_BYTES = 30 * 1024 * 1024; // 30 MB

    /** Maksimum jumlah baris <row> yang diproses per sheet. */
    private const MAX_ROWS_PER_SHEET = 20000;

    /** Maksimum jumlah kolom/cell yang diproses per baris. */
    private const MAX_COLS_PER_ROW = 200;

    /**
     * Ambil isi satu entry ZIP dengan mengecek ukuran uncompressed-nya
     * LEBIH DULU lewat statName() — supaya entry yang sudah "meledak"
     * ukurannya tidak pernah sempat diekstrak penuh ke memory.
     */
    private static function readEntryOrFail(\ZipArchive $zip, string $entryName, bool $required = true): ?string
    {
        $stat = $zip->statName($entryName);
        if ($stat === false) {
            if ($required) {
                throw new \RuntimeException('Struktur Excel tidak dikenali.');
            }
            return null;
        }
        if (($stat['size'] ?? 0) > self::MAX_UNCOMPRESSED_ENTRY_BYTES) {
            throw new \RuntimeException('File Excel ditolak: salah satu bagian file terlalu besar setelah diekstrak.');
        }
        $content = $zip->getFromName($entryName);
        if ($content === false) {
            if ($required) {
                throw new \RuntimeException('Struktur Excel tidak dikenali.');
            }
            return null;
        }
        return $content;
    }

    public static function sheets(string $path): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('Gagal membuka file Excel.');
        }
        $wb = self::readEntryOrFail($zip, 'xl/workbook.xml');
        $rels = self::readEntryOrFail($zip, 'xl/_rels/workbook.xml.rels');
        $zip->close();
        if (!$wb || !$rels) {
            throw new \RuntimeException('Struktur Excel tidak dikenali.');
        }

        $relMap = [];
        if (preg_match_all('/Id="(rId\d+)"[^>]*Target="([^"]+)"/', $rels, $m, PREG_SET_ORDER)) {
            foreach ($m as $row) {
                $relMap[$row[1]] = ltrim($row[2], '/');
            }
        }

        $sheets = [];
        if (preg_match_all('/<sheet[^>]*name="([^"]+)"[^>]*r:id="(rId\d+)"/', $wb, $m, PREG_SET_ORDER)) {
            foreach ($m as $row) {
                // Batas jumlah sheet — dicek di sini, sebelum satu pun
                // entry worksheet diekstrak.
                if (count($sheets) >= self::MAX_SHEETS) {
                    throw new \RuntimeException('File Excel ditolak: jumlah sheet melebihi batas (' . self::MAX_SHEETS . ').');
                }
                $target = $relMap[$row[2]] ?? null;
                if ($target && !str_starts_with($target, 'worksheets/')) {
                    $target = 'worksheets/' . basename($target);
                }
                if ($target) {
                    $sheets[$row[1]] = 'xl/' . $target;
                }
            }
        }
        return $sheets;
    }

    public static function sheetToArray(string $path, string $sheetPath): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('Gagal membuka file Excel.');
        }
        $shared = [];
        $ss = self::readEntryOrFail($zip, 'xl/sharedStrings.xml', required: false);
        if ($ss && preg_match_all('/<si>(.*?)<\/si>/s', $ss, $items)) {
            foreach ($items[1] as $si) {
                if (preg_match_all('/<t[^>]*>(.*?)<\/t>/s', $si, $ts)) {
                    $shared[] = html_entity_decode(implode('', $ts[1]), ENT_QUOTES | ENT_XML1, 'UTF-8');
                } else {
                    $shared[] = '';
                }
            }
        }
        $xml = self::readEntryOrFail($zip, $sheetPath, required: false);
        $zip->close();
        if (!$xml) {
            return [];
        }

        $rows = [];
        if (!preg_match_all('/<row[^>]*>(.*?)<\/row>/s', $xml, $rowMatches)) {
            return [];
        }
        // Batas jumlah baris — dicek di awal supaya sheet dengan jutaan
        // baris "sampah" tidak diproses habis-habisan sebelum ditolak.
        if (count($rowMatches[1]) > self::MAX_ROWS_PER_SHEET) {
            throw new \RuntimeException('File Excel ditolak: jumlah baris pada satu sheet melebihi batas (' . self::MAX_ROWS_PER_SHEET . ').');
        }
        foreach ($rowMatches[1] as $rowXml) {
            $cells = [];
            if (preg_match_all('/<c r="([A-Z]+)(\d+)"([^>]*)>(?:<v>(.*?)<\/v>)?/s', $rowXml, $cMatches, PREG_SET_ORDER)) {
                if (count($cMatches) > self::MAX_COLS_PER_ROW) {
                    throw new \RuntimeException('File Excel ditolak: jumlah kolom pada satu baris melebihi batas (' . self::MAX_COLS_PER_ROW . ').');
                }
                foreach ($cMatches as $c) {
                    $col = self::colIndex($c[1]);
                    if ($col >= self::MAX_COLS_PER_ROW) {
                        // Referensi kolom (mis. "ZZ1") bisa jauh melebihi
                        // jumlah cell aktual yang cocok di atas; tetap
                        // ditolak supaya baris hasil for-loop di bawah
                        // (0..$max) tidak ikut membengkak.
                        throw new \RuntimeException('File Excel ditolak: referensi kolom melebihi batas (' . self::MAX_COLS_PER_ROW . ').');
                    }
                    $val = $c[4] ?? '';
                    $attrs = $c[3] ?? '';
                    if (str_contains($attrs, 't="s"') && $val !== '' && isset($shared[(int) $val])) {
                        $val = $shared[(int) $val];
                    }
                    $cells[$col] = $val;
                }
            }
            if ($cells) {
                $max = max(array_keys($cells));
                $line = [];
                for ($i = 0; $i <= $max; $i++) {
                    $line[] = $cells[$i] ?? '';
                }
                $rows[] = $line;
            }
        }
        return $rows;
    }

    private static function colIndex(string $letters): int
    {
        $n = 0;
        foreach (str_split($letters) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }
        return $n - 1;
    }

    /** Baca semua sheet → [name => rows] */
    public static function allSheets(string $path): array
    {
        $map = self::sheets($path);
        $out = [];
        foreach ($map as $name => $sheetPath) {
            $out[$name] = self::sheetToArray($path, $sheetPath);
        }
        return $out;
    }
}
