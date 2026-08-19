@extends('layouts.app')
@section('title', 'Admin')
@section('main_class', '')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/admin.css') }}">
@endpush

@section('content')
@php
    $tab = request('tab', 'guru');
    $editGuru = $editGuru ?? null;
    $editKepsek = $editKepsek ?? null;
@endphp
<div class="admin-page">
    <header class="admin-header">
        <div>
            <h1>⚙️ Admin</h1>
            <p>Kelola akun Guru BK &amp; Kepala Sekolah</p>
        </div>
        <div class="admin-user">
            <span>{{ session('auth_user.nama') ?? session('auth_user.username') ?? 'Admin' }}</span>
            <form action="{{ route('logout') }}" method="POST" style="display:inline;margin:0">
                @csrf
                <button type="submit">Logout</button>
            </form>
        </div>
    </header>

    <div class="admin-tabs">
        <a href="{{ route('admin.dashboard', ['tab' => 'guru']) }}"
           class="{{ $tab === 'guru' ? 'active' : '' }}">
            Guru BK ({{ $guruList->count() }})
        </a>
        <a href="{{ route('admin.dashboard', ['tab' => 'kepsek']) }}"
           class="{{ $tab === 'kepsek' ? 'active' : '' }}">
            Kepala Sekolah ({{ $kepsekList->count() }})
        </a>
    </div>

    @if($tab === 'guru')
    <div class="admin-panel">
        <form class="admin-form" method="POST"
              action="{{ $editGuru ? route('admin.guru.update', $editGuru->id) : route('admin.guru.store') }}">
            @csrf
            @if($editGuru) @method('PUT') @endif
            <h3>{{ $editGuru ? 'Edit Guru BK' : 'Tambah Guru BK' }}</h3>
            <div class="admin-form-grid">
                <input name="username" placeholder="Username *" value="{{ old('username', $editGuru->username ?? '') }}" required>
                <input name="password" type="password"
                       placeholder="{{ $editGuru ? 'Password (kosongkan jika tidak diubah)' : 'Password *' }}"
                       {{ $editGuru ? '' : 'required' }}>
                <input name="nama" placeholder="Nama lengkap *" value="{{ old('nama', $editGuru->nama ?? '') }}" required>
                <input name="spesialisasi" placeholder="Spesialisasi" value="{{ old('spesialisasi', $editGuru->spesialisasi ?? 'Guru BK') }}">
                <input name="npsn" placeholder="NPSN" value="{{ old('npsn', $editGuru->npsn ?? '') }}">
                <input name="alamat" placeholder="Alamat" value="{{ old('alamat', $editGuru->alamat ?? '') }}">
            </div>
            @if($editGuru)
            <label style="display:flex;align-items:center;gap:8px;margin-bottom:12px;font-size:14px">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $editGuru->is_active))>
                Aktif
            </label>
            @endif
            <div class="admin-form-actions">
                <button type="submit">{{ $editGuru ? 'Simpan Perubahan' : 'Tambah Akun' }}</button>
                @if($editGuru)
                <a href="{{ route('admin.dashboard', ['tab' => 'guru']) }}" class="btn-secondary"
                   style="display:inline-flex;align-items:center;text-decoration:none;background:#94a3b8;color:#fff;padding:10px 18px;border-radius:8px;font-weight:600">Batal</a>
                @endif
            </div>
        </form>

        <table class="admin-table">
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>Username</th>
                    <th>Spesialisasi</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($guruList as $g)
                <tr class="{{ $g->is_active ? '' : 'inactive' }}">
                    <td>{{ $g->nama }}</td>
                    <td><code>{{ $g->username }}</code></td>
                    <td>{{ $g->spesialisasi ?: '–' }}</td>
                    <td>
                        <span class="badge {{ $g->is_active ? 'on' : 'off' }}">
                            {{ $g->is_active ? 'Aktif' : 'Nonaktif' }}
                        </span>
                    </td>
                    <td class="actions">
                        <a href="{{ route('admin.dashboard', ['tab' => 'guru', 'edit_guru' => $g->id]) }}">Edit</a>
                        @if($g->is_active)
                        <form action="{{ route('admin.guru.deactivate', $g->id) }}" method="POST" style="display:inline"
                              onsubmit="return confirm('Nonaktifkan akun Guru BK &quot;{{ $g->nama }}&quot;?\nAkun tidak akan muncul di Pilih Guru siswa.')">
                            @csrf
                            <button type="submit" class="danger">Nonaktifkan</button>
                        </form>
                        @else
                        <form action="{{ route('admin.guru.activate', $g->id) }}" method="POST" style="display:inline">
                            @csrf
                            <button type="submit">Aktifkan</button>
                        </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" style="text-align:center">Belum ada akun Guru BK</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @endif

    @if($tab === 'kepsek')
    <div class="admin-panel">
        <form class="admin-form" method="POST"
              action="{{ $editKepsek ? route('admin.kepsek.update', $editKepsek->id) : route('admin.kepsek.store') }}">
            @csrf
            @if($editKepsek) @method('PUT') @endif
            <h3>{{ $editKepsek ? 'Edit Kepala Sekolah' : 'Tambah Kepala Sekolah' }}</h3>
            <div class="admin-form-grid">
                <input name="username" placeholder="Username *" value="{{ old('username', $editKepsek->username ?? '') }}" required>
                <input name="password" type="password"
                       placeholder="{{ $editKepsek ? 'Password (kosongkan jika tidak diubah)' : 'Password *' }}"
                       {{ $editKepsek ? '' : 'required' }}>
                <input name="nama" placeholder="Nama lengkap *" value="{{ old('nama', $editKepsek->nama ?? '') }}" required>
                <input name="npsn" placeholder="NPSN" value="{{ old('npsn', $editKepsek->npsn ?? '') }}">
            </div>
            @if($editKepsek)
            <label style="display:flex;align-items:center;gap:8px;margin-bottom:12px;font-size:14px">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $editKepsek->is_active))>
                Aktif
            </label>
            @endif
            <div class="admin-form-actions">
                <button type="submit">{{ $editKepsek ? 'Simpan Perubahan' : 'Tambah Akun' }}</button>
                @if($editKepsek)
                <a href="{{ route('admin.dashboard', ['tab' => 'kepsek']) }}" class="btn-secondary"
                   style="display:inline-flex;align-items:center;text-decoration:none;background:#94a3b8;color:#fff;padding:10px 18px;border-radius:8px;font-weight:600">Batal</a>
                @endif
            </div>
        </form>

        <table class="admin-table">
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>Username</th>
                    <th>NPSN</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($kepsekList as $k)
                <tr class="{{ $k->is_active ? '' : 'inactive' }}">
                    <td>{{ $k->nama }}</td>
                    <td><code>{{ $k->username }}</code></td>
                    <td>{{ $k->npsn ?: '–' }}</td>
                    <td>
                        <span class="badge {{ $k->is_active ? 'on' : 'off' }}">
                            {{ $k->is_active ? 'Aktif' : 'Nonaktif' }}
                        </span>
                    </td>
                    <td class="actions">
                        <a href="{{ route('admin.dashboard', ['tab' => 'kepsek', 'edit_kepsek' => $k->id]) }}">Edit</a>
                        @if($k->is_active)
                        <form action="{{ route('admin.kepsek.deactivate', $k->id) }}" method="POST" style="display:inline"
                              onsubmit="return confirm('Nonaktifkan akun Kepala Sekolah &quot;{{ $k->nama }}&quot;?')">
                            @csrf
                            <button type="submit" class="danger">Nonaktifkan</button>
                        </form>
                        @else
                        <form action="{{ route('admin.kepsek.activate', $k->id) }}" method="POST" style="display:inline">
                            @csrf
                            <button type="submit">Aktifkan</button>
                        </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" style="text-align:center">Belum ada akun Kepala Sekolah</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @endif
</div>
@endsection
