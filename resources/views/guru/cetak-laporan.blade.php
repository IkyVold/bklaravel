<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Jurnal Kerja Guru BK — Cetak</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #e5e7eb; font-family: 'Times New Roman', Times, serif; }
        .toolbar {
            position: sticky; top: 0; z-index: 100; background: #1e293b; color: #fff;
            padding: 12px 20px; display: flex; gap: 12px; align-items: center; justify-content: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        .toolbar button, .toolbar a.btn {
            padding: 10px 18px; border-radius: 8px; border: none; font-weight: 600; font-size: 14px;
            cursor: pointer; font-family: 'Segoe UI', sans-serif; text-decoration: none; display: inline-block; color: #fff;
        }
        .btn-print { background: #16a34a; }
        .btn-print:hover { background: #15803d; }
        .btn-back { background: #64748b; }
        .btn-back:hover { background: #475569; }
        .page {
            width: 210mm; min-height: 297mm; margin: 20px auto; background: #fff;
            padding: 18mm 14mm 16mm; box-shadow: 0 4px 24px rgba(0,0,0,0.12); color: #000;
        }
        .header-title { text-align: center; margin-bottom: 6px; }
        .header-title h1 {
            margin: 0; font-size: 15pt; font-weight: bold; letter-spacing: 0.3px;
            text-transform: uppercase; line-height: 1.35;
        }
        .header-title h2 { margin: 2px 0 0; font-size: 13pt; font-weight: bold; text-transform: uppercase; }
        .header-title h3 { margin: 2px 0 0; font-size: 12pt; font-weight: bold; text-transform: uppercase; }
        .meta-row {
            display: flex; justify-content: space-between; margin: 16px 0 10px;
            font-size: 11pt; font-weight: bold;
        }
        table.jurnal { width: 100%; border-collapse: collapse; font-size: 10pt; table-layout: fixed; }
        table.jurnal th, table.jurnal td {
            border: 1px solid #000; padding: 6px 5px; vertical-align: top; word-wrap: break-word;
        }
        table.jurnal th {
            background: #f3f4f6; text-align: center; font-weight: bold;
            font-size: 9.5pt; text-transform: uppercase;
        }
        table.jurnal td.no { text-align: center; width: 28px; }
        table.jurnal td.nama { width: 90px; }
        table.jurnal td.kelas { text-align: center; width: 70px; }
        table.jurnal td.hari { width: 95px; }
        table.jurnal td.jenis { width: 85px; }
        table.jurnal td.materi { width: auto; }
        table.jurnal td.tindak { width: 90px; }
        .footer-sign {
            display: flex; justify-content: space-between; margin-top: 28px;
            font-size: 11pt; page-break-inside: avoid;
        }
        .sign-box { width: 42%; text-align: center; }
        .sign-box .label { margin-bottom: 4px; }
        .sign-space { height: 70px; }
        .sign-name { font-weight: bold; text-decoration: underline; margin-top: 4px; }
        .sign-nip { font-size: 10pt; }
        .sign-right .tempat { margin-bottom: 2px; }
        .empty-wrap {
            min-height: 100vh; display: flex; flex-direction: column; align-items: center;
            justify-content: center; gap: 12px; font-family: 'Segoe UI', sans-serif; color: #444;
        }
        @media print {
            body { background: #fff; }
            .toolbar { display: none !important; }
            .page {
                width: 100%; min-height: auto; margin: 0; padding: 10mm 8mm; box-shadow: none;
            }
            @page { size: A4 portrait; margin: 10mm; }
        }
    </style>
</head>
<body>
@php
    $HARI = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    $BULAN = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

    $parse = function ($raw) {
        if (!$raw) return null;
        try { return \Carbon\Carbon::parse($raw); } catch (\Throwable $e) { return null; }
    };

    $formatHariTanggal = function ($raw) use ($parse, $HARI, $BULAN) {
        $dt = $parse($raw);
        if (!$dt) return $raw ?: '-';
        return $HARI[$dt->dayOfWeek] . ', ' . $dt->day . ' ' . $BULAN[$dt->month - 1] . ' ' . $dt->year;
    };

    $mapJenis = function ($kategori, $deskripsi) {
        $k = strtolower((string) $kategori);
        $d = strtolower((string) $deskripsi);
        if (str_contains($k, 'akademik') || str_contains($d, 'belajar') || str_contains($d, 'mapel')) return 'Pribadi dan Belajar';
        if (str_contains($k, 'karir') || str_contains($d, 'kuliah') || str_contains($d, 'jurusan')) return 'Pribadi dan Karir';
        if (str_contains($k, 'keluarga') || str_contains($k, 'pribadi') || str_contains($k, 'emosional')) return 'Pribadi';
        if (str_contains($k, 'bullying') || str_contains($k, 'sosial')) return 'Pribadi dan Sosial';
        return $kategori ?: 'Pribadi';
    };

    $mapMateri = function ($r) {
        if (!empty($r->laporan_kesimpulan)) return $r->laporan_kesimpulan;
        if (!empty($r->deskripsi) && $r->deskripsi !== 'Tidak ada deskripsi masalah') return $r->deskripsi;
        return '-';
    };

    $mapTindak = function ($r) {
        return $r->laporan_rekomendasi ?? '';
    };

    $refDate = $rows->count() > 0 ? ($parse($rows->first()->tanggal) ?: now()) : now();
    $y = (int) $refDate->year;
    $m = (int) $refDate->month;
    $start = $m >= 7 ? $y : $y - 1;
    $tahunPelajaran = $start . ' - ' . ($start + 1);
    $semester = ($m >= 7 && $m <= 12) ? 'GANJIL' : 'GENAP';
    $bulanLabel = $BULAN[$m - 1];
    $mingguKe = (int) ceil(((int) $refDate->day) / 7);
    $ttdDate = now()->day . ' ' . $BULAN[now()->month - 1] . ' ' . now()->year;
@endphp

@if($rows->isEmpty())
    <div class="empty-wrap">
        <h2>Tidak ada data untuk dicetak</h2>
        <p style="color:#888">Kembali ke dashboard dan pastikan ada data konseling pada filter ini.</p>
        <a href="{{ route('guru.konseling.index', ['filter' => $filter ?? 'all']) }}"
           style="padding:10px 20px;border-radius:8px;background:#0d9488;color:#fff;text-decoration:none;font-weight:600;font-family:'Segoe UI',sans-serif">
            Kembali ke Dashboard
        </a>
    </div>
@else
    <div class="toolbar">
        <button type="button" class="btn-print" onclick="window.print()">🖨️ Cetak / Simpan PDF</button>
        <a href="{{ route('guru.konseling.index', ['filter' => $filter ?? 'all']) }}" class="btn btn-back">← Kembali</a>
        <span style="margin-left:12px;opacity:.85;font-size:13px;font-family:'Segoe UI',sans-serif">
            {{ $rows->count() }} data · Dialog cetak browser → "Save as PDF"
        </span>
    </div>

    <div class="page">
        <div class="header-title">
            <h1>JURNAL KERJA GURU BIMBINGAN DAN KONSELING</h1>
            <h2>TAHUN PELAJARAN {{ $tahunPelajaran }}</h2>
            <h3>SEMESTER {{ $semester }}</h3>
        </div>

        <div class="meta-row">
            <div>BULAN : {{ $bulanLabel }}</div>
            <div>MINGGU KE : {{ $mingguKe }}</div>
        </div>

        <table class="jurnal">
            <thead>
                <tr>
                    <th style="width:28px">NO</th>
                    <th style="width:90px">NAMA SISWA</th>
                    <th style="width:70px">KELAS</th>
                    <th style="width:95px">HARI/TANGGAL</th>
                    <th style="width:85px">JENIS BIMBINGAN</th>
                    <th>MATERI LAYANAN</th>
                    <th style="width:90px">TINDAK LANJUT</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $idx => $r)
                <tr>
                    <td class="no">{{ $idx + 1 }}</td>
                    <td class="nama">{{ optional($r->siswa)->nama ?? '-' }}</td>
                    <td class="kelas">{{ optional($r->siswa)->kelas ?? '-' }}</td>
                    <td class="hari">{{ $formatHariTanggal($r->tanggal) }}</td>
                    <td class="jenis">{{ $mapJenis($r->kategori, $r->deskripsi ?? '') }}</td>
                    <td class="materi">{{ $mapMateri($r) }}</td>
                    <td class="tindak">{{ $mapTindak($r) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="footer-sign">
            <div class="sign-box">
                <div class="label">Mengetahui,</div>
                <div>Kepala Sekolah</div>
                <div class="sign-space"></div>
                <div class="sign-name">{{ $meta['kepalaSekolah'] }}</div>
                <div class="sign-nip">{{ $meta['kepalaSekolahNip'] }}</div>
            </div>
            <div class="sign-box sign-right">
                <div class="tempat">Banyuwangi, {{ $ttdDate }}</div>
                <div>Guru BK</div>
                <div class="sign-space"></div>
                <div class="sign-name">{{ $meta['guruName'] }}</div>
            </div>
        </div>
    </div>
@endif
</body>
</html>
