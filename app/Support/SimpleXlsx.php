<?php

namespace App\Support;

/**
 * Pembaca XLSX minimal (tanpa PhpSpreadsheet) untuk import siswa/absen.
 */
class SimpleXlsx
{
    public static function sheets(string $path): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('Gagal membuka file Excel.');
        }
        $wb = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
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
        $ss = $zip->getFromName('xl/sharedStrings.xml');
        if ($ss && preg_match_all('/<si>(.*?)<\/si>/s', $ss, $items)) {
            foreach ($items[1] as $si) {
                if (preg_match_all('/<t[^>]*>(.*?)<\/t>/s', $si, $ts)) {
                    $shared[] = html_entity_decode(implode('', $ts[1]), ENT_QUOTES | ENT_XML1, 'UTF-8');
                } else {
                    $shared[] = '';
                }
            }
        }
        $xml = $zip->getFromName($sheetPath);
        $zip->close();
        if (!$xml) {
            return [];
        }

        $rows = [];
        if (!preg_match_all('/<row[^>]*>(.*?)<\/row>/s', $xml, $rowMatches)) {
            return [];
        }
        foreach ($rowMatches[1] as $rowXml) {
            $cells = [];
            if (preg_match_all('/<c r="([A-Z]+)(\d+)"([^>]*)>(?:<v>(.*?)<\/v>)?/s', $rowXml, $cMatches, PREG_SET_ORDER)) {
                foreach ($cMatches as $c) {
                    $col = self::colIndex($c[1]);
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
