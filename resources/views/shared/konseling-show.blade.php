@extends('layouts.app')
@section('title', 'Detail Konseling')
@section('heading', 'Detail Konseling #' . $row->id)
@section('content')
<div class="card">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
        <div><strong>Siswa</strong><br>{{ $row->siswa->nama ?? '-' }} ({{ $row->siswa->nis ?? '-' }})</div>
        <div><strong>Kelas</strong><br>{{ $row->siswa->kelas ?? $row->kelas_siswa }}</div>
        <div><strong>Guru BK</strong><br>{{ $row->guru_bk }}</div>
        <div><strong>Kategori / Jenis</strong><br>{{ $row->kategori }} / {{ $row->jenis }}</div>
        <div><strong>Status</strong><br><span class="badge badge-blue">{{ $row->status }}</span></div>
        <div><strong>Konfirmasi</strong><br><span class="badge badge-yellow">{{ $row->status_konfirmasi }}</span></div>
    </div>
    <div style="margin-top:16px"><strong>Deskripsi</strong><p style="margin-top:6px">{{ $row->deskripsi }}</p></div>
    @if($row->laporan || $row->laporan_kesimpulan)
        <hr style="margin:20px 0;border:none;border-top:1px solid var(--border)">
        <h2>Laporan</h2>
        @if($row->laporan)<p><strong>Isi:</strong> {{ $row->laporan }}</p>@endif
        @if($row->laporan_kesimpulan)<p><strong>Kesimpulan:</strong> {{ $row->laporan_kesimpulan }}</p>@endif
        @if($row->laporan_rekomendasi)<p><strong>Rekomendasi:</strong> {{ $row->laporan_rekomendasi }}</p>@endif
    @endif
</div>
@if(in_array($role, ['guru','admin']))
<div class="card">
    <h2>Konfirmasi Jadwal</h2>
    <form method="POST" action="{{ route('guru.konseling.konfirmasi', $row->id) }}">
        @csrf
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">
            <div class="form-group">
                <label>Status konfirmasi</label>
                <select name="status_konfirmasi" class="form-control" required>
                    <option value="Dikonfirmasi">Dikonfirmasi</option>
                    <option value="Ditolak">Ditolak</option>
                    <option value="Jadwal Ulang">Jadwal Ulang</option>
                </select>
            </div>
            <div class="form-group"><label>Tanggal</label><input type="date" name="tanggal_konfirmasi" class="form-control"></div>
            <div class="form-group"><label>Jam</label><input type="time" name="jam_konfirmasi" class="form-control"></div>
        </div>
        <button type="submit" class="btn btn-primary">Simpan Konfirmasi</button>
    </form>
</div>
<div class="card">
    <h2>Isi Laporan</h2>
    <form method="POST" action="{{ route('guru.konseling.laporan', $row->id) }}">
        @csrf
        <div class="form-group"><label>Laporan</label><textarea name="laporan" class="form-control">{{ $row->laporan }}</textarea></div>
        <div class="form-group"><label>Kesimpulan</label><textarea name="laporan_kesimpulan" class="form-control">{{ $row->laporan_kesimpulan }}</textarea></div>
        <div class="form-group"><label>Rekomendasi</label><textarea name="laporan_rekomendasi" class="form-control">{{ $row->laporan_rekomendasi }}</textarea></div>
        <div class="form-group"><label>Status</label>
            <select name="status" class="form-control">
                <option value="Proses">Proses</option>
                <option value="Selesai">Selesai</option>
            </select>
        </div>
        <button type="submit" class="btn btn-success">Simpan Laporan</button>
    </form>
</div>
@endif
@endsection
