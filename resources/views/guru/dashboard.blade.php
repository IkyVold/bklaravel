@extends('layouts.app')
@section('title', 'Dashboard Guru BK')
@section('heading', 'Dashboard Guru BK')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/guruBk.css') }}">
<link rel="stylesheet" href="{{ asset('css/guru-shell.css') }}">
@endpush
@section('content')
<div class="guru-bk-page">
@include('partials.guru-header')
<div class="container">
@include('partials.guru-sidebar')
<div class="guru-main">
<div class="stats">
    <div class="stat-card orange"><div class="label">Menunggu Konfirmasi</div><div class="value">{{ $pending }}</div></div>
    <div class="stat-card blue"><div class="label">Sedang Proses</div><div class="value">{{ $proses }}</div></div>
    <div class="stat-card green"><div class="label">Selesai</div><div class="value">{{ $selesai }}</div></div>
    <div class="stat-card"><div class="label">Total Siswa</div><div class="value">{{ $totalSiswa }}</div></div>
</div>
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
        <h2 style="margin:0">Konseling Terbaru</h2>
        <div style="display:flex;gap:8px">
            <a href="{{ route('guru.konseling.walkin') }}" class="btn btn-outline btn-sm">Walk-in</a>
            <a href="{{ route('guru.konseling.index') }}" class="btn btn-primary btn-sm">Semua</a>
        </div>
    </div>
    @if($recent->isEmpty())
        <p class="empty">Belum ada data.</p>
    @else
        <div class="table-wrap">
            <table>
                <thead><tr><th>Siswa</th><th>Kelas</th><th>Kategori</th><th>Status</th><th>Konfirmasi</th><th></th></tr></thead>
                <tbody>
                @foreach($recent as $k)
                    <tr>
                        <td>{{ $k->siswa->nama ?? '-' }}</td>
                        <td>{{ $k->siswa->kelas ?? $k->kelas_siswa }}</td>
                        <td>{{ $k->kategori }}</td>
                        <td><span class="badge badge-blue">{{ $k->status }}</span></td>
                        <td><span class="badge {{ str_contains($k->status_konfirmasi, 'Belum') ? 'badge-yellow' : 'badge-green' }}">{{ $k->status_konfirmasi }}</span></td>
                        <td><a href="{{ route('guru.konseling.show', $k->id) }}">Kelola</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
</div></div></div>
@endsection
