@extends('layouts.app')

@section('title', 'Pilih Guru BK')
@section('main_class', '')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/pilihGuru.css') }}">
@endpush

@section('content')
<div class="pilih-page">
    <div class="page-header">
        <div class="breadcrumb">
            <a href="{{ route('siswa.dashboard') }}">Beranda</a>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 18l6-6-6-6" />
            </svg>
            <span>Konseling</span>
        </div>
        <h1>Pilih Guru BK</h1>
        <p>
            Pilih guru bimbingan konseling yang ingin Anda hubungi. Laporan akan langsung diteruskan
            ke guru yang dipilih.
        </p>
    </div>

    <div class="info-banner">
        <div class="info-inner">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10" />
                <path d="M12 16v-4M12 8h.01" />
            </svg>
            Semua konsultasi bersifat rahasia. Data Anda hanya dapat diakses oleh guru yang Anda
            pilih.
        </div>
    </div>

    @if($guruList->isEmpty())
        <p style="text-align:center;padding:2rem;color:#666">
            Belum ada Guru BK yang tersedia. Hubungi Admin.
        </p>
    @else
        <div class="cards-wrapper">
            @foreach($guruList as $g)
                <div class="counselor-card">
                    @if(!empty($g->foto_profile))
                        <img
                            src="{{ asset('storage/'.$g->foto_profile) }}"
                            alt="{{ $g->nama }}"
                            class="counselor-avatar"
                            onerror="this.style.display='none';this.nextElementSibling.style.display='flex'"
                        >
                        <div class="counselor-avatar counselor-avatar-initials" style="display:none" aria-hidden>
                            {{ strtoupper(\Illuminate\Support\Str::substr($g->nama ?? '?', 0, 2)) }}
                        </div>
                    @else
                        <div class="counselor-avatar counselor-avatar-initials" aria-hidden>
                            {{ strtoupper(\Illuminate\Support\Str::substr($g->nama ?? '?', 0, 2)) }}
                        </div>
                    @endif
                    <div class="counselor-info">
                        <div class="counselor-name">{{ $g->nama }}</div>
                        <div class="counselor-meta">
                            <span class="role-badge">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                                    <circle cx="9" cy="7" r="4" />
                                </svg>
                                {{ $g->spesialisasi ?: 'Guru BK' }}
                            </span>
                            @if(!empty($g->npsn))
                                <span class="meta-pill">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                        <path d="M14 2v6h6" />
                                    </svg>
                                    NPSN {{ $g->npsn }}
                                </span>
                            @endif
                            @if(!empty($g->alamat))
                                <span class="meta-pill">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
                                        <circle cx="12" cy="10" r="3" />
                                    </svg>
                                    {{ $g->alamat }}
                                </span>
                            @endif
                        </div>
                    </div>
                    <a
                        href="{{ route('siswa.konseling.create', ['guru_id' => $g->id, 'guru' => $g->nama]) }}"
                        class="pilih-button"
                        style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center"
                    >
                        Pilih Guru
                    </a>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
