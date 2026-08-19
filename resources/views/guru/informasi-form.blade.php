@extends('layouts.app')
@section('title', $row ? 'Edit Informasi' : 'Tambah Informasi')
@section('main_class', '')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/guruBk.css') }}">
<link rel="stylesheet" href="{{ asset('css/guru-shell.css') }}">
@endpush
@section('content')
@php
    $activeTab = 'informasi'; $currentFilter = 'all'; $prosesCount = 0;
    $kategoriList = ['Beasiswa','Pendaftaran Perguruan Tinggi','Bimbingan Karir','Informasi Sekolah','Informasi BK','Umum'];
@endphp
<div class="guru-bk-page">
    @include('partials.guru-header')
    <div class="container">
        @include('partials.guru-sidebar')
        <div class="guru-main">
            <div class="content-header">
                <h2>{{ $row ? '✏️ Edit Informasi' : '➕ Tambah Informasi' }}</h2>
                <p><a href="{{ route('guru.informasi') }}">← Kembali</a></p>
            </div>
            <div class="panel" style="max-width:560px;padding:24px">
                <form method="POST" action="{{ $row ? route('guru.informasi.update', $row->id) : route('guru.informasi.store') }}">
                    @csrf
                    @if($row) @method('PUT') @endif
                    <div class="form-group">
                        <label>Judul</label>
                        <input type="text" name="judul" class="form-control" value="{{ old('judul', $row->judul ?? '') }}" required placeholder="Contoh: Beasiswa Bidikmisi 2026">
                    </div>
                    <div class="form-group">
                        <label>Kategori</label>
                        <select name="kategori" class="form-control" required>
                            @foreach($kategoriList as $k)
                                <option value="{{ $k }}" @selected(old('kategori', $row->kategori ?? 'FAQ')===$k)>{{ $k }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Isi Informasi</label>
                        <textarea name="isi" class="form-control" rows="8" required placeholder="Tulis detail lengkap — syarat, jadwal, kuota, link, dsb. Chatbot FAQ memakai isi ini.">{{ old('isi', $row->isi ?? '') }}</textarea>
                    </div>
                    <button type="submit" class="btn-cetak btn-cetak-green">💾 Simpan Informasi</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
