@extends('layouts.app')
@section('title', 'Rekap Guru BK — Kepsek')
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
                <h2>👨‍🏫 Rekap Guru BK</h2>
                <p>Ringkasan beban dan hasil layanan per Guru BK</p>
            </div>
            <div class="panel">
                <div style="overflow-x:auto">
                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Guru BK</th>
                                <th>Total</th>
                                <th>Akademik</th>
                                <th>Sosial</th>
                                <th>Pribadi</th>
                                <th>Bullying</th>
                                <th>Proses</th>
                                <th>Selesai</th>
                                <th>Dibatalkan</th>
                                <th>Laporan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($rekap as $i => $item)
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td><strong>{{ $item['guru']->nama }}</strong></td>
                                <td><strong>{{ $item['total'] }}</strong></td>
                                <td>{{ $item['akademik'] }}</td>
                                <td>{{ $item['sosial'] }}</td>
                                <td>{{ $item['pribadi'] }}</td>
                                <td>{{ $item['bullying'] }}</td>
                                <td><span class="status-badge status-proses">{{ $item['proses'] }}</span></td>
                                <td><span class="status-badge status-selesai">{{ $item['selesai'] }}</span></td>
                                <td><span class="status-badge status-dibatalkan">{{ $item['dibatalkan'] }}</span></td>
                                <td><span class="status-badge status-belum">{{ $item['laporan'] }}</span></td>
                            </tr>
                            @empty
                            <tr><td colspan="11" style="text-align:center;padding:40px;color:#718096">Belum ada data Guru BK</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
