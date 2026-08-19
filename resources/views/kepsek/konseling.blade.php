@extends('layouts.app')
@section('title', 'Semua Konseling — Kepsek')
@section('main_class', '')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/kepsekDashboard.css') }}">
<link rel="stylesheet" href="{{ asset('css/kepsek-shell.css') }}">
@endpush
@section('content')
<div class="kepsek-page">
    @include('partials.kepsek-header')
    <div class="container">
        @include('partials.kepsek-sidebar')
        <div class="main-content">
            <div class="content-header">
                <h2>📋 Semua Konseling</h2>
                <p>Data seluruh pengajuan konseling di sekolah (read-only)</p>
            </div>
            <div class="panel">
                <form method="GET" class="filters">
                    <select name="filter">
                        <option value="all" @selected(($filter??'all')==='all')>Semua status</option>
                        <option value="proses" @selected(($filter??'')==='proses')>Proses</option>
                        <option value="selesai" @selected(($filter??'')==='selesai')>Selesai</option>
                        <option value="dibatalkan" @selected(($filter??'')==='dibatalkan')>Dibatalkan</option>
                    </select>
                    <select name="kategori">
                        <option value="">Semua kategori</option>
                        @foreach(['Akademik','Sosial','Pribadi','Karir','Bullying','Keluarga','Lainnya'] as $k)
                            <option value="{{ $k }}" @selected(($kategori??'')===$k)>{{ $k }}</option>
                        @endforeach
                    </select>
                    <input type="search" name="q" value="{{ $q ?? '' }}" placeholder="Cari siswa / guru...">
                    <button type="submit" class="btn-sm" style="border:none;cursor:pointer">Filter</button>
                </form>
                <div style="overflow-x:auto">
                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Siswa</th>
                                <th>NIS</th>
                                <th>Kelas</th>
                                <th>Guru BK</th>
                                <th>Tanggal</th>
                                <th>Jenis</th>
                                <th>Kategori</th>
                                <th>Konfirmasi</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($rows as $i => $r)
                            @php
                                $sk = $r->status_konfirmasi ?? 'Belum Dikonfirmasi';
                                if (in_array($sk, ['Tervalidasi','Dikonfirmasi'], true)) $sk = 'Terkonfirmasi';
                            @endphp
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td><strong>{{ optional($r->siswa)->nama ?? '-' }}</strong></td>
                                <td style="font-family:monospace">{{ optional($r->siswa)->nis ?? '-' }}</td>
                                <td>{{ $r->kelas_siswa ?? optional($r->siswa)->kelas ?? '-' }}</td>
                                <td>{{ $r->guru_bk }}</td>
                                <td>{{ $r->tanggal ? \Carbon\Carbon::parse($r->tanggal)->format('d/m/Y') : '–' }} {{ $r->jam ? substr((string)$r->jam,0,5) : '' }}</td>
                                <td>{{ $r->jenis }}</td>
                                <td>{{ $r->kategori }}</td>
                                <td><span class="status-badge {{ $sk==='Terkonfirmasi'?'status-selesai':'status-belum' }}">{{ $sk }}</span></td>
                                <td><span class="status-badge {{ $r->status==='Selesai'?'status-selesai':($r->status==='Dibatalkan'?'status-dibatalkan':'status-proses') }}">{{ $r->status }}</span></td>
                                <td><a href="{{ route('kepsek.konseling.show', $r->id) }}" class="btn-sm">Detail</a></td>
                            </tr>
                            @empty
                            <tr><td colspan="11" style="text-align:center;padding:40px;color:#718096">Tidak ada data</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
