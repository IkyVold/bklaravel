@extends('layouts.app')
@section('title', 'Form Guru')
@section('heading', $row ? 'Edit Guru' : 'Tambah Guru')
@section('content')
<div class="card" style="max-width:480px">
<form method="POST" action="{{ $row ? route('admin.guru.update', $row->id) : route('admin.guru.store') }}">
@csrf @if($row) @method('PUT') @endif
<div class="form-group"><label>Username</label><input name="username" class="form-control" value="{{ old('username', $row->username ?? '') }}" required></div>
<div class="form-group"><label>Nama</label><input name="nama" class="form-control" value="{{ old('nama', $row->nama ?? '') }}" required></div>
<div class="form-group"><label>Spesialisasi</label><input name="spesialisasi" class="form-control" value="{{ old('spesialisasi', $row->spesialisasi ?? 'Guru BK') }}"></div>
<div class="form-group"><label>Password {{ $row ? '(opsional)' : '' }}</label><input type="password" name="password" class="form-control" {{ $row ? '' : 'required' }}></div>
@if($row)
<div class="form-group"><label><input type="checkbox" name="is_active" value="1" @checked($row->is_active)> Aktif</label></div>
@endif
<button class="btn btn-primary" type="submit">Simpan</button>
</form></div>
@endsection
