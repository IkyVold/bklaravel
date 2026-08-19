@extends('layouts.app')
@section('title', 'Informasi BK')
@section('heading', 'Informasi BK')
@section('actions')
@if(in_array($role, ['guru','admin']))
<a href="{{ route('guru.informasi.create') }}" class="btn btn-primary">+ Tulis</a>
@endif
@endsection
@section('content')
@forelse($rows as $info)
<div class="card">
    <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
        <div>
            <h2 style="margin:0">{{ $info->judul }}</h2>
            <div style="color:var(--muted);font-size:.85rem;margin-top:4px">
                {{ $info->kategori }} · {{ $info->guru_bk }} · {{ $info->created_at?->format('d/m/Y') }}
            </div>
        </div>
        @if(in_array($role, ['guru','admin']))
        <div style="display:flex;gap:8px">
            <a href="{{ route('guru.informasi.edit', $info->id) }}" class="btn btn-outline btn-sm">Edit</a>
            <form method="POST" action="{{ route('guru.informasi.destroy', $info->id) }}" onsubmit="return confirm('Hapus?')">
                @csrf @method('DELETE')
                <button class="btn btn-danger btn-sm" type="submit">Hapus</button>
            </form>
        </div>
        @endif
    </div>
    <p style="margin-top:12px;white-space:pre-wrap">{{ $info->isi }}</p>
</div>
@empty
<div class="card"><p class="empty">Belum ada informasi.</p></div>
@endforelse
@endsection
