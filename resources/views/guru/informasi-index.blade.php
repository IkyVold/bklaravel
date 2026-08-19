@extends('layouts.app')
@section('title', 'Informasi / FAQ — Guru BK')
@section('main_class', '')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/guruBk.css') }}">
<link rel="stylesheet" href="{{ asset('css/guru-shell.css') }}">
<style>
.guru-bk-page .info-banner {
  background: #EEEDFE;
  border-left: 4px solid #534AB7;
  padding: 12px 16px;
  border-radius: 8px;
  margin: 16px 0;
  font-size: 12.5px;
  color: #3C3489;
  line-height: 1.55;
}
.guru-bk-page .aksi-wrap {
  display: flex; gap: 8px; align-items: center; flex-wrap: wrap; white-space: nowrap;
}
.guru-bk-page .btn-aksi-edit {
  background: #EEEDFE; color: #3C3489; border: none; border-radius: 8px;
  padding: 6px 12px; font-size: 12px; font-weight: 600; cursor: pointer; text-decoration: none;
  display: inline-flex; align-items: center; gap: 4px;
}
.guru-bk-page .btn-aksi-hapus {
  background: #FCEBEB; color: #A32D2D; border: none; border-radius: 8px;
  padding: 6px 12px; font-size: 12px; font-weight: 600; cursor: pointer;
  display: inline-flex; align-items: center; gap: 4px;
}
.guru-bk-page .btn-aksi-edit:hover { filter: brightness(0.97); }
.guru-bk-page .btn-aksi-hapus:hover { filter: brightness(0.97); }
.guru-bk-page .isi-cell {
  max-width: 280px; font-size: 12.5px; color: #5F5E5A; line-height: 1.4;
}
.guru-bk-page .table-container {
  background: #fff; border: 1px solid #e8e6e0; border-radius: 16px;
  overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,.04);
}
.guru-bk-page .table-header {
  display: flex; justify-content: space-between; align-items: center;
  padding: 16px 20px; flex-wrap: wrap; gap: 12px;
}
.guru-bk-page .table-header h3 {
  margin: 0; font-size: 15.5px; font-weight: 700; color: #1a1a18;
}
</style>
@endpush

@section('content')
@php
    $kategoriList = [
        'Beasiswa',
        'Pendaftaran Perguruan Tinggi',
        'Bimbingan Karir',
        'Informasi Sekolah',
        'Informasi BK',
        'Umum',
    ];
    $activeTab = $activeTab ?? 'informasi';
    $currentFilter = $currentFilter ?? 'all';
    $prosesCount = $prosesCount ?? 0;
@endphp
<div class="guru-bk-page">
    @include('partials.guru-header')
    <div class="container">
        @include('partials.guru-sidebar', [
            'activeTab' => $activeTab,
            'currentFilter' => $currentFilter,
            'prosesCount' => $prosesCount,
        ])
        <div class="guru-main">
            <div class="content-header">
                <h2>💡 Informasi / FAQ</h2>
                <p>Kelola informasi yang ditampilkan ke siswa dan dipakai asisten FAQ chatbot</p>
            </div>

            <div class="content-tabs tab-nav">
                <a href="{{ route('guru.konseling.index') }}" class="tab-btn">📋 Konseling</a>
                <a href="{{ route('guru.siswa.index') }}" class="tab-btn">👥 Daftar Siswa</a>
                <a href="{{ route('guru.informasi') }}" class="tab-btn active">💡 Informasi / FAQ</a>
            </div>

            <div class="table-container" style="margin-top:0">
                <div class="table-header" style="border-bottom:none;padding-bottom:0">
                    <h3>💡 Informasi &amp; FAQ untuk Chatbot Siswa</h3>
                    <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap">
                        <div style="font-size:13px;color:#5F5E5A">{{ $rows->count() }} informasi tersimpan</div>
                        <a href="{{ route('guru.informasi.create') }}" class="btn-cetak" style="background:#16a34a;box-shadow:0 2px 8px rgba(22,163,74,0.25);text-decoration:none">
                            <span>➕</span> Tambah Informasi
                        </a>
                    </div>
                </div>

                <div style="padding:0 20px">
                    <div class="info-banner">
                        ℹ️ Informasi di sini otomatis dijadikan referensi jawaban oleh <strong>chatbot AI</strong>
                        yang siswa akses dari halaman Beranda. Cocok buat info beasiswa, jalur pendaftaran
                        perguruan tinggi (SNBP/SNBT/mandiri), bimbingan karir, atau pengumuman sekolah.
                    </div>

                    <div class="siswa-search-bar">
                        <input type="text" id="infoSearch" placeholder="🔍 Cari judul atau isi informasi..." autocomplete="off">
                        <select id="infoKategori">
                            <option value="">🏷️ Semua Kategori</option>
                            @foreach($kategoriList as $k)
                                <option value="{{ $k }}">{{ $k }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="table-wrap" style="padding:0 0 8px">
                    @if($rows->isEmpty())
                        <div class="empty-state" style="padding:48px 20px;text-align:center">
                            <div class="empty-icon" style="font-size:48px;opacity:.45">💡</div>
                            <div style="font-size:18px;font-weight:700;color:#1a1a18;margin:8px 0">Belum ada informasi</div>
                            <div style="font-size:14px;color:#5F5E5A">
                                Tambahkan info beasiswa, pendaftaran PT, atau karir supaya chatbot bisa menjawabnya ke siswa
                            </div>
                        </div>
                    @else
                    <table id="infoTable">
                        <thead>
                            <tr>
                                <th>Judul</th>
                                <th>Kategori</th>
                                <th>Isi</th>
                                <th>Diperbarui</th>
                                <th>Oleh</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rows as $row)
                            <tr data-judul="{{ strtolower($row->judul) }}"
                                data-isi="{{ strtolower(\Illuminate\Support\Str::limit($row->isi, 200)) }}"
                                data-kategori="{{ $row->kategori }}">
                                <td style="font-weight:600;max-width:200px">{{ $row->judul }}</td>
                                <td><span class="badge-tahun">{{ $row->kategori }}</span></td>
                                <td class="isi-cell">{{ \Illuminate\Support\Str::limit($row->isi, 120) }}</td>
                                <td style="white-space:nowrap;font-size:12.5px;color:#5F5E5A">
                                    {{ $row->updated_at ? \Carbon\Carbon::parse($row->updated_at)->format('d/m/Y H:i') : ($row->created_at ? \Carbon\Carbon::parse($row->created_at)->format('d/m/Y') : '–') }}
                                </td>
                                <td style="font-size:12.5px">{{ $row->guru_bk ?? 'Guru BK' }}</td>
                                <td>
                                    <div class="aksi-wrap">
                                        <a href="{{ route('guru.informasi.edit', $row->id) }}" class="btn-aksi-edit">✏️ Edit</a>
                                        <form method="POST" action="{{ route('guru.informasi.destroy', $row->id) }}" style="display:inline;margin:0" onsubmit="return confirm('Hapus informasi ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-aksi-hapus">🗑️ Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div id="infoEmptyFilter" class="empty-state" style="display:none;padding:40px 20px;text-align:center">
                        <div style="font-size:16px;font-weight:600;color:#5F5E5A">Tidak ada informasi yang cocok dengan pencarian</div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
  var search = document.getElementById('infoSearch');
  var kategori = document.getElementById('infoKategori');
  var table = document.getElementById('infoTable');
  var empty = document.getElementById('infoEmptyFilter');
  if (!search || !table) return;

  function filter() {
    var q = (search.value || '').toLowerCase().trim();
    var kat = kategori ? kategori.value : '';
    var rows = table.querySelectorAll('tbody tr');
    var visible = 0;
    rows.forEach(function (tr) {
      var judul = tr.getAttribute('data-judul') || '';
      var isi = tr.getAttribute('data-isi') || '';
      var k = tr.getAttribute('data-kategori') || '';
      var okQ = !q || judul.indexOf(q) !== -1 || isi.indexOf(q) !== -1;
      var okK = !kat || k === kat;
      var show = okQ && okK;
      tr.style.display = show ? '' : 'none';
      if (show) visible++;
    });
    if (empty) empty.style.display = visible === 0 ? 'block' : 'none';
  }
  search.addEventListener('input', filter);
  if (kategori) kategori.addEventListener('change', filter);
})();
</script>
@endpush
