@extends('layouts.app')
@section('title', 'Walk-in Konseling')
@section('main_class', '')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/guruBk.css') }}">
<link rel="stylesheet" href="{{ asset('css/guru-shell.css') }}">
@endpush
@section('content')
@php $activeTab='konseling'; $currentFilter='all'; $prosesCount=0; @endphp
<div class="guru-bk-page">
    <div class="dashboard-layout">
        @include('partials.guru-sidebar')
        <div class="main-content">
            <div class="page-header">
                <div>
                    <div class="breadcrumb"><a href="{{ route('guru.konseling.index') }}">Konseling</a> / Walk-in</div>
                    <h1>Catat Konseling Walk-in</h1>
                </div>
                <a href="{{ route('guru.konseling.index') }}" class="btn btn-outline">← Kembali</a>
            </div>
            <div class="card" style="max-width:640px">
                <form method="POST" action="{{ route('guru.konseling.walkin.store') }}">
                    @csrf
                    <div class="form-group">
                        <label>Siswa</label>
                        <select name="siswa_id" class="form-control" required>
                            <option value="">— Pilih siswa —</option>
                            @foreach($siswaList as $s)
                                <option value="{{ $s->id }}">{{ $s->nama }} ({{ $s->nis }}) — {{ $s->kelas }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Jenis</label>
                        <select name="jenis" class="form-control">
                            <option value="Walk-in">Walk-in</option>
                            <option value="Luring">Luring</option>
                            <option value="Daring">Daring</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Kategori</label>
                        <input type="text" name="kategori" class="form-control" value="Umum">
                    </div>
                    <div class="form-group">
                        <label>Deskripsi / Masalah</label>
                        <textarea name="deskripsi" class="form-control" rows="4" required></textarea>
                    </div>
                    <div class="form-group">
                        <label>Catatan walk-in</label>
                        <textarea name="catatan_walkin" class="form-control" rows="2"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Simpan Walk-in</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
