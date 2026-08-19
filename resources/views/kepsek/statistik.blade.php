@extends('layouts.app')
@section('title', 'Statistik Lengkap — Kepala Sekolah')
@section('main_class', '')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/kepsekDashboard.css') }}">
<link rel="stylesheet" href="{{ asset('css/kepsek-shell.css') }}">
<style>
    .kepsek-page .stat-card.stat-clean {
        background: #fff;
        border-radius: 16px;
        padding: 20px 22px;
        box-shadow: 0 2px 12px rgba(0,0,0,.06);
        display: flex;
        align-items: center;
        gap: 16px;
        border: 1px solid #edf2f7;
    }
    .kepsek-page .stat-card.stat-clean .stat-icon-wrap {
        width: 48px; height: 48px;
        border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        font-size: 22px;
        flex-shrink: 0;
    }
    .kepsek-page .stat-card.stat-clean .stat-icon-wrap.blue { background: #ebf4ff; }
    .kepsek-page .stat-card.stat-clean .stat-icon-wrap.purple { background: var(--purple-50); }
    .kepsek-page .stat-card.stat-clean .stat-icon-wrap.teal { background: var(--teal-50); }
    .kepsek-page .stat-card.stat-clean .stat-icon-wrap.orange { background: var(--coral-50); }
    .kepsek-page .stat-card.stat-clean h3 {
        font-size: 13px; font-weight: 500; color: var(--gray-600); margin: 0 0 4px;
    }
    .kepsek-page .stat-card.stat-clean .stat-value {
        font-size: 26px; font-weight: 700; color: var(--gray-800); line-height: 1.1;
    }
    .kepsek-page .stat-card.stat-clean .stat-value small {
        font-size: 13px; font-weight: 500; color: var(--gray-600);
    }
    .kepsek-page .split-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 24px;
    }
    @media (max-width: 900px) {
        .kepsek-page .split-grid { grid-template-columns: 1fr; }
    }
    .kepsek-page .chart-card table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
    }
    .kepsek-page .chart-card table td {
        padding: 10px 8px;
        border-bottom: 1px solid var(--gray-100);
        color: var(--gray-800);
    }
    .kepsek-page .chart-card table tr:last-child td { border-bottom: none; }
    .kepsek-page .chart-card table td:nth-child(2),
    .kepsek-page .chart-card table td:nth-child(3) {
        text-align: right;
        font-weight: 600;
    }
    .kepsek-page .chart-card table td:nth-child(3) { color: var(--gray-600); font-weight: 500; }
</style>
@endpush
@section('content')
@php
    $user = session('auth_user', []);
    $periode = now()->locale('id')->translatedFormat('F Y');
    $total = max(1, (int)($stats['total'] ?? 0));
    $laporanCount = collect($rows ?? [])->filter(fn($r) => !empty($r->laporan_kesimpulan) || !empty($r->laporan_created_at))->count();
    $persenLaporan = ($stats['total'] ?? 0) > 0 ? number_format(($laporanCount / $stats['total']) * 100, 1) : '0.0';
    $pct = fn($v) => number_format(($v / $total) * 100, 1);

    $kategoriRows = [
        ['Akademik', $stats['akademik'] ?? 0],
        ['Sosial',   $stats['sosial'] ?? 0],
        ['Pribadi',  $stats['pribadi'] ?? 0],
        ['Karir',    $stats['karir'] ?? 0],
        ['Bullying', $stats['bullying'] ?? 0],
        ['Keluarga', $stats['keluarga'] ?? 0],
    ];
    $statusRows = [
        ['Proses',        $stats['proses'] ?? 0],
        ['Selesai',       $stats['selesai'] ?? 0],
        ['Dibatalkan',    $stats['dibatalkan'] ?? 0],
        ['Tervalidasi',   $stats['terkonfirmasi'] ?? 0],
    ];
@endphp
<div class="kepsek-page">
    @include('partials.kepsek-header')
    <div class="container">
        @include('partials.kepsek-sidebar')
        <div class="main-content">
            <div class="content-header">
                <h2>📈 Statistik Lengkap</h2>
                <p>Analisis data konseling periode {{ $periode }}</p>
            </div>

            <div class="stats-grid" style="margin-bottom:24px">
                <div class="stat-card stat-clean">
                    <div class="stat-icon-wrap blue">📊</div>
                    <div>
                        <h3>Total Konseling</h3>
                        <div class="stat-value">{{ $stats['total'] ?? 0 }}</div>
                    </div>
                </div>
                <div class="stat-card stat-clean">
                    <div class="stat-icon-wrap purple">👥</div>
                    <div>
                        <h3>Siswa Aktif</h3>
                        <div class="stat-value">{{ $stats['siswaAktif'] ?? 0 }}</div>
                    </div>
                </div>
                <div class="stat-card stat-clean">
                    <div class="stat-icon-wrap teal">👨‍🏫</div>
                    <div>
                        <h3>Guru BK Aktif</h3>
                        <div class="stat-value">{{ $stats['guruAktif'] ?? 0 }}</div>
                    </div>
                </div>
                <div class="stat-card stat-clean">
                    <div class="stat-icon-wrap orange">📋</div>
                    <div>
                        <h3>Laporan Tersedia</h3>
                        <div class="stat-value">
                            {{ $laporanCount }}
                            <small>({{ $persenLaporan }}%)</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="split-grid">
                <div class="chart-card">
                    <div class="chart-title">📊 Distribusi Kategori</div>
                    <div class="chart-container">
                        <table>
                            <tbody>
                                @foreach($kategoriRows as [$label, $val])
                                <tr>
                                    <td>{{ $label }}</td>
                                    <td>{{ $val }}</td>
                                    <td>{{ $pct($val) }}%</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="chart-card">
                    <div class="chart-title">📈 Status Konseling</div>
                    <div class="chart-container">
                        <table>
                            <tbody>
                                @foreach($statusRows as [$label, $val])
                                <tr>
                                    <td>{{ $label }}</td>
                                    <td>{{ $val }}</td>
                                    <td>{{ $pct($val) }}%</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="table-container" id="laporan">
                <div class="table-header">
                    <h3>👨‍🏫 Statistik per Guru BK</h3>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Guru BK</th>
                            <th>Total</th>
                            <th>Akademik</th>
                            <th>Sosial</th>
                            <th>Pribadi</th>
                            <th>Bullying</th>
                            <th>Proses</th>
                            <th>Selesai</th>
                            <th>Laporan</th>
                            <th>% Selesai</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $guruGroups = collect($rows ?? [])->groupBy(fn($r) => $r->guru_bk ?: '—');
                        @endphp
                        @forelse($guruGroups as $guruNama => $items)
                            @php
                                $t = $items->count();
                                $selesai = $items->where('status', 'Selesai')->count();
                                $laporan = $items->filter(fn($r) => !empty($r->laporan_kesimpulan) || !empty($r->laporan_created_at))->count();
                                $persenSelesai = $t > 0 ? number_format(($selesai / $t) * 100, 1) : '0.0';
                            @endphp
                            <tr>
                                <td><strong>{{ $guruNama }}</strong></td>
                                <td>{{ $t }}</td>
                                <td>{{ $items->filter(fn($r) => strcasecmp((string)$r->kategori, 'Akademik')===0)->count() }}</td>
                                <td>{{ $items->filter(fn($r) => strcasecmp((string)$r->kategori, 'Sosial')===0)->count() }}</td>
                                <td>{{ $items->filter(fn($r) => strcasecmp((string)$r->kategori, 'Pribadi')===0)->count() }}</td>
                                <td>{{ $items->filter(fn($r) => strcasecmp((string)$r->kategori, 'Bullying')===0)->count() }}</td>
                                <td>{{ $items->where('status', 'Proses')->count() }}</td>
                                <td>{{ $selesai }}</td>
                                <td>
                                    <span class="status-badge {{ $laporan >= $selesai && $selesai > 0 ? 'status-selesai' : 'status-proses' }}">
                                        {{ $laporan }}/{{ $selesai }}
                                    </span>
                                </td>
                                <td><span class="guru-value">{{ $persenSelesai }}%</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" style="text-align:center;padding:32px;color:var(--gray-600)">Belum ada data</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
