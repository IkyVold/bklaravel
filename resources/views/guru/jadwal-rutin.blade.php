@extends('layouts.app')
@section('title', 'Jadwal Rutin')
@section('main_class', '')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/guruBk.css') }}">
<link rel="stylesheet" href="{{ asset('css/guru-shell.css') }}">
<link rel="stylesheet" href="{{ asset('css/jadwal-rutin.css') }}">
@endpush
@section('content')
@php $activeTab = 'jadwal-rutin'; @endphp
<div class="guru-bk-page">
    @include('partials.guru-header')
    <div class="container">
        @include('partials.guru-sidebar')
        <div class="main-content">
            <div class="content-header">
                <h2>📅 Jadwal Konseling Rutin</h2>
                <p>Atur slot tetap (hari &amp; jam) agar siswa dapat memilih konsultasi <strong>rutin</strong>. Pengajuan di luar slot = <strong>nonrutin</strong>.</p>
            </div>

            <div class="panel" style="padding:20px;margin-bottom:20px;max-width:720px">
                <h3 style="margin-top:0;font-size:16px">+ Tambah Slot Rutin</h3>
                <form method="POST" action="{{ route('guru.jadwal-rutin.store') }}" class="jr-form">
                    @csrf
                    <div class="jr-grid">
                        <div>
                            <label>Hari</label>
                            <select name="hari" required>
                                @foreach($hariList as $num => $label)
                                    <option value="{{ $num }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label>Jam mulai</label>
                            <input type="time" name="jam_mulai" required value="09:00">
                        </div>
                        <div>
                            <label>Jam selesai (opsional)</label>
                            <input type="time" name="jam_selesai">
                        </div>
                        <div>
                            <label>Jenis</label>
                            <select name="jenis" required>
                                <option value="Luring">Luring</option>
                                <option value="Daring">Daring</option>
                            </select>
                        </div>
                        <div style="grid-column:1/-1">
                            <label>Keterangan (opsional)</label>
                            <input type="text" name="keterangan" placeholder="Contoh: Ruang BK / untuk kelas X" maxlength="150">
                        </div>
                    </div>
                    <button type="submit" class="btn-cetak btn-cetak-green" style="margin-top:12px">Simpan Slot</button>
                </form>
            </div>

            <div class="panel" style="padding:0;overflow:hidden">
                <table class="jr-table">
                    <thead>
                        <tr>
                            <th>Hari</th>
                            <th>Jam</th>
                            <th>Jenis</th>
                            <th>Keterangan</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($slots as $s)
                        <tr class="{{ $s->is_active ? '' : 'inactive' }}">
                            <td><strong>{{ $s->hari_label }}</strong></td>
                            <td>{{ $s->jam_label }}</td>
                            <td>{{ $s->jenis }}</td>
                            <td>{{ $s->keterangan ?: '–' }}</td>
                            <td>
                                <span class="badge-tipe {{ $s->is_active ? 'badge-rutin' : 'badge-nonrutin' }}">
                                    {{ $s->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td style="white-space:nowrap">
                                <form action="{{ route('guru.jadwal-rutin.toggle', $s->id) }}" method="POST" style="display:inline">
                                    @csrf
                                    <button type="submit" class="jr-btn">{{ $s->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                </form>
                                <form action="{{ route('guru.jadwal-rutin.destroy', $s->id) }}" method="POST" style="display:inline"
                                      onsubmit="return confirm('Hapus slot ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="jr-btn danger">Hapus</button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" style="text-align:center;padding:28px;color:#888">
                                Belum ada slot rutin. Tambahkan di form atas agar siswa bisa memilih konsultasi rutin.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
