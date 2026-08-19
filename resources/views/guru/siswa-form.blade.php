@extends('layouts.app')
@section('title', $siswa ? 'Edit Siswa' : 'Tambah Siswa')
@section('main_class', '')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/guruBk.css') }}">
<link rel="stylesheet" href="{{ asset('css/guru-shell.css') }}">
@endpush
@section('content')
@php
    $activeTab = 'siswa'; $currentFilter = 'all'; $prosesCount = 0;
    $kelasOptions = [];
    foreach (['X','XI','XII'] as $t) { for ($i=1;$i<=10;$i++) $kelasOptions[] = "$t - $i"; }
@endphp
<div class="guru-bk-page">
    @include('partials.guru-header')
    <div class="container">
        @include('partials.guru-sidebar')
        <div class="main-content">
            <div class="content-header">
                <h2>{{ $siswa ? '✏️ Edit Siswa' : '➕ Tambah Siswa' }}</h2>
                <p><a href="{{ route('guru.siswa.index') }}">← Kembali ke daftar</a></p>
            </div>
            <div class="panel" style="max-width:560px;padding:24px">
                <form method="POST" action="{{ $siswa ? route('guru.siswa.update', $siswa->id) : route('guru.siswa.store') }}">
                    @csrf
                    @if($siswa) @method('PUT') @endif
                    <div class="form-group">
                        <label>NIS</label>
                        <input type="text" name="nis" class="form-control" value="{{ old('nis', $siswa->nis ?? '') }}" required maxlength="20">
                    </div>
                    <div class="form-group">
                        <label>Nama lengkap</label>
                        <input type="text" name="nama" class="form-control" value="{{ old('nama', $siswa->nama ?? '') }}" required>
                    </div>
                    <div class="form-group">
                        <label>Kelas</label>
                        <select name="kelas" class="form-control" required>
                            <option value="">— Pilih kelas —</option>
                            @foreach($kelasOptions as $k)
                                <option value="{{ $k }}" @selected(old('kelas', $siswa->kelas ?? '')===$k)>{{ $k }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Jenis kelamin</label>
                        <select name="jenis_kelamin" class="form-control">
                            <option value="">—</option>
                            <option value="Laki-laki" @selected(old('jenis_kelamin', $siswa->jenis_kelamin ?? '')==='Laki-laki')>Laki-laki</option>
                            <option value="Perempuan" @selected(old('jenis_kelamin', $siswa->jenis_kelamin ?? '')==='Perempuan')>Perempuan</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Password {{ $siswa ? '(kosongkan jika tidak diubah)' : '' }}</label>
                        <input type="password" name="password" class="form-control" {{ $siswa ? '' : 'required' }} minlength="4">
                    </div>
                    <button type="submit" class="btn-cetak btn-cetak-green">💾 Simpan</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
