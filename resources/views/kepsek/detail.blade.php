@extends('layouts.app')
@section('title', 'Detail Konseling — Kepsek')
@section('main_class', '')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/kepsekDashboard.css') }}">
<link rel="stylesheet" href="{{ asset('css/kepsek-shell.css') }}">
@endpush
@section('content')
@php
    $siswa = $row['siswa'];
    $sk = $row['status_konfirmasi'] ?? 'Belum Dikonfirmasi';
    if (in_array($sk, ['Tervalidasi', 'Dikonfirmasi'], true)) {
        $sk = 'Terkonfirmasi';
    }
    $statusClass = match ($row['status'] ?? '') {
        'Selesai' => 'status-selesai',
        'Dibatalkan' => 'status-dibatalkan',
        default => 'status-proses',
    };
    $skClass = $sk === 'Terkonfirmasi' ? 'status-selesai' : 'status-belum';
    $tgl = $row['tanggal'] ? \Carbon\Carbon::parse($row['tanggal'])->translatedFormat('d M Y') : '–';
    $jam = $row['jam'] ? substr((string) $row['jam'], 0, 5) : '–';
@endphp
<div class="kepsek-page">
    @include('partials.kepsek-header')
    <div class="container">
        @include('partials.kepsek-sidebar')
        <div class="main-content">
            <div class="content-header">
                <h2>📁 Detail Konseling #{{ $row['id'] }}</h2>
                <p><a href="{{ route('kepsek.konseling') }}" style="color:var(--purple-600,#534AB7);text-decoration:none">← Kembali ke daftar</a></p>
            </div>

            <div class="panel" style="padding:24px;max-width:720px">
                {{-- Hero siswa --}}
                <div class="detail-hero" style="margin-bottom:20px;padding-bottom:16px;border-bottom:1px solid var(--gray-100,#e8e6e0)">
                    <div style="font-size:20px;font-weight:700;color:var(--gray-800,#444441)">{{ $siswa['nama'] ?? '–' }}</div>
                    <div style="font-size:13px;color:var(--gray-600,#5F5E5A);margin-top:4px">
                        NIS: {{ $siswa['nis'] ?? '–' }} · Kelas: {{ $row['kelas_siswa'] ?? $siswa['kelas'] ?? '–' }}
                    </div>
                    <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap">
                        <span class="status-badge {{ $statusClass }}">{{ $row['status'] ?? 'Menunggu' }}</span>
                        <span class="status-badge {{ $skClass }}">{{ $sk }}</span>
                    </div>
                </div>

                {{-- Info grid (data administratif saja — lihat catatan kerahasiaan di bawah) --}}
                <div class="detail-row">
                    <div class="detail-label">Guru BK</div>
                    <div class="detail-value">{{ $row['guru_bk'] ?: '–' }}</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Tanggal / Jam</div>
                    <div class="detail-value">{{ $tgl }} · {{ $jam }}</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Jenis</div>
                    <div class="detail-value">{{ $row['jenis'] ?: '–' }}</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Kategori</div>
                    <div class="detail-value">{{ $row['kategori'] ?: '–' }}</div>
                </div>
                @if(!empty($row['laporan_status_penanganan']))
                <div class="detail-row">
                    <div class="detail-label">Status Penanganan</div>
                    <div class="detail-value">{{ $row['laporan_status_penanganan'] }}</div>
                </div>
                @endif
                <div class="detail-row">
                    <div class="detail-label">Laporan</div>
                    <div class="detail-value">{{ !empty($row['ada_laporan']) ? 'Sudah dibuat oleh ' . ($row['laporan_dibuat_oleh'] ?? 'Guru BK') : 'Belum ada laporan' }}</div>
                </div>

                <div class="detail-deskripsi" style="margin-top:16px;background:#F5F6FA;border-left-color:#8C88B8">
                    <strong>Isi konsultasi bersifat rahasia</strong>
                    <div style="margin-top:6px">
                        Deskripsi masalah, kesimpulan, dan rekomendasi konsultasi hanya dapat dilihat oleh
                        siswa yang bersangkutan dan Guru BK yang dipilihnya. Halaman ini menampilkan data
                        administratif (jadwal, status, dan status penanganan) untuk keperluan monitoring.
                    </div>
                </div>

                @if(($row['status'] ?? '') === 'Dibatalkan')
                <div class="detail-deskripsi" style="background:#FDF6F6;border-left-color:#E24B4A;margin-top:16px">
                    <strong style="color:#A32D2D">Alasan Pembatalan</strong>
                    <div style="margin-top:6px">{{ $row['alasan_batal'] ?? 'Dibatalkan' }}</div>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
