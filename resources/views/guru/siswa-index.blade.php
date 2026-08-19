@extends('layouts.app')
@section('title', 'Daftar Siswa — Guru BK')
@section('main_class', '')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/guruBk.css') }}">
<link rel="stylesheet" href="{{ asset('css/guru-shell.css') }}">
<style>
.guru-modal-overlay {
  display: none; position: fixed; inset: 0; z-index: 4000;
  background: rgba(16,24,40,0.55); backdrop-filter: blur(3px);
  align-items: center; justify-content: center; padding: 16px;
}
.guru-modal-overlay.show { display: flex; }
.guru-modal {
  background: #fff; border-radius: 16px; width: 100%; max-width: 520px;
  max-height: 90vh; overflow-y: auto; box-shadow: 0 24px 60px rgba(16,24,40,0.25);
}
.guru-modal-lg { max-width: 640px; }
.guru-modal-header {
  background: linear-gradient(120deg, #26215C, #3C3489);
  color: #fff; padding: 16px 20px; border-radius: 16px 16px 0 0;
  display: flex; justify-content: space-between; align-items: center;
}
.guru-modal-header h3 { margin: 0; font-size: 16px; font-weight: 700; }
.guru-modal-close {
  background: rgba(255,255,255,0.15); border: none; color: #fff;
  width: 32px; height: 32px; border-radius: 50%; cursor: pointer; font-size: 18px;
}
.guru-modal-body { padding: 20px; }
.guru-modal-body label { display: block; font-size: 12px; font-weight: 700; color: #5F5E5A; margin-bottom: 6px; text-transform: uppercase; letter-spacing: .02em; }
.guru-modal-body input, .guru-modal-body select, .guru-modal-body textarea {
  width: 100%; padding: 10px 12px; border: 0.5px solid #d3d1c7; border-radius: 8px;
  font-size: 14px; margin-bottom: 12px; box-sizing: border-box;
}
.guru-modal-hint {
  background: #FFF8EB; border-left: 4px solid #EF9F27; padding: 10px 14px;
  border-radius: 8px; margin-bottom: 16px; font-size: 12.5px; color: #854F0B;
}
.btn-simpan-modal {
  width: 100%; padding: 12px; border: none; border-radius: 10px;
  background: #16a34a; color: #fff; font-weight: 700; font-size: 14px; cursor: pointer;
}
.btn-simpan-modal:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-secondary-modal {
  width: 100%; padding: 10px; border: 0.5px solid #d3d1c7; border-radius: 10px;
  background: #fff; font-weight: 600; cursor: pointer; margin-top: 8px;
}
.import-result { margin-top: 14px; padding: 12px; border-radius: 8px; font-size: 13px; }
.import-result.ok { background: #E1F5EE; color: #0F6E56; }
.import-result.err { background: #FCEBEB; color: #A32D2D; }
.absen-map-row {
  display: flex; gap: 10px; align-items: center; padding: 10px 0;
  border-bottom: 0.5px solid #f1efe8; font-size: 13px;
}
.absen-map-row select { margin: 0; flex: 1; }

.guru-pagination {
  display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between;
  gap: 12px; padding: 14px 18px; border-top: 1px solid #e8e6e0;
}
.guru-pagination-info { font-size: 13px; color: #5F5E5A; }
.guru-pagination-links { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
.guru-pagination .pg-btn {
  display: inline-flex; align-items: center; justify-content: center;
  min-width: 36px; height: 34px; padding: 0 12px;
  border-radius: 8px; border: 1px solid #e8e6e0; background: #fff;
  color: #1a1a18; font-size: 13px; font-weight: 600; text-decoration: none;
}
.guru-pagination a.pg-btn:hover { background: #EEEDFE; border-color: #7F77DD; color: #3C3489; }
.guru-pagination .pg-btn.active {
  background: #3C3489; border-color: #3C3489; color: #fff;
}
.guru-pagination .pg-btn.disabled {
  opacity: 0.45; cursor: default; pointer-events: none;
}
/* Sembunyikan default Tailwind pagination SVG raksasa kalau masih ketarik */
nav[role="navigation"] svg { width: 16px !important; height: 16px !important; max-width: 16px !important; }

</style>
@endpush

@section('content')
@php
    $kelasOptions = $kelasOptions ?? [];
@endphp
<div class="guru-bk-page">
    @include('partials.guru-header')
    <div class="container">
        @include('partials.guru-sidebar')
        <div class="guru-main">
            <div class="content-header">
                <h2>👥 Daftar Siswa</h2>
                <p>Kelola data siswa terdaftar untuk layanan konseling</p>
            </div>
            <div class="content-tabs tab-nav">
                <a href="{{ route('guru.konseling.index') }}" class="tab-btn">📋 Konseling</a>
                <a href="{{ route('guru.siswa.index') }}" class="tab-btn active">👥 Daftar Siswa</a>
            </div>

            <div class="panel">
                <div class="panel-head">
                    <h3>👥 Daftar Siswa Terdaftar</h3>
                    <div class="panel-actions">
                        <span class="muted-count">{{ $rows->total() }} dari {{ $totalCount }} siswa</span>
                        <button type="button" class="btn-cetak btn-cetak-green" id="btnTambahSiswa">➕ Tambah Siswa</button>
                        <button type="button" class="btn-cetak" id="btnImportExcel" style="background:#534AB7">📥 Import Excel</button>
                        <button type="button" class="btn-cetak" id="btnImportAbsen" style="background:#3C3489">📋 Import dari Absen</button>
                    </div>
                </div>
                <form method="GET" action="{{ route('guru.siswa.index') }}" class="siswa-search-bar">
                    <input type="text" name="q" value="{{ $q }}" placeholder="🔍 Cari NIS atau nama siswa...">
                    <select name="kelas">
                        <option value="">Semua Kelas</option>
                        @foreach($kelasList as $k)
                            <option value="{{ $k }}" @selected($kelas===$k)>{{ $k }}</option>
                        @endforeach
                    </select>
                    <select name="jk">
                        <option value="">⚧ Semua</option>
                        <option value="Laki-laki" @selected($jk==='Laki-laki')>Laki-laki</option>
                        <option value="Perempuan" @selected($jk==='Perempuan')>Perempuan</option>
                    </select>
                    <button type="submit" class="btn-sm">Filter</button>
                </form>
                <div class="table-wrap">
                    @if($rows->isEmpty())
                        <div class="empty-state">
                            <div class="empty-icon">👥</div>
                            <div style="font-size:18px;font-weight:700;margin-bottom:8px">Tidak ada siswa ditemukan</div>
                            <div style="font-size:14px">Coba ubah filter atau tambah/import siswa</div>
                        </div>
                    @else
                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>NIS</th>
                                <th>Nama Siswa</th>
                                <th>Kelas Aktif</th>
                                <th>Jenis Kelamin</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rows as $i => $s)
                            <tr>
                                <td style="font-weight:600">{{ $rows->firstItem() + $i }}</td>
                                <td style="font-family:monospace;font-weight:600">{{ $s->nis }}</td>
                                <td><strong>{{ $s->nama }}</strong></td>
                                <td><span class="badge-kelas">{{ $s->kelas ?: '-' }}</span></td>
                                <td>{{ $s->jenis_kelamin ?: '-' }}</td>
                                <td><a href="{{ route('guru.siswa.edit', $s->id) }}" class="btn-sm">✏️ Edit</a></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="guru-pagination">
                        <div class="guru-pagination-info">
                            Menampilkan {{ $rows->firstItem() ?? 0 }}–{{ $rows->lastItem() ?? 0 }} dari {{ $rows->total() }} siswa
                        </div>
                        <div class="guru-pagination-links">
                            @if($rows->onFirstPage())
                                <span class="pg-btn disabled">← Prev</span>
                            @else
                                <a class="pg-btn" href="{{ $rows->previousPageUrl() }}">← Prev</a>
                            @endif

                            @foreach($rows->getUrlRange(max(1, $rows->currentPage()-2), min($rows->lastPage(), $rows->currentPage()+2)) as $page => $url)
                                @if($page == $rows->currentPage())
                                    <span class="pg-btn active">{{ $page }}</span>
                                @else
                                    <a class="pg-btn" href="{{ $url }}">{{ $page }}</a>
                                @endif
                            @endforeach

                            @if($rows->hasMorePages())
                                <a class="pg-btn" href="{{ $rows->nextPageUrl() }}">Next →</a>
                            @else
                                <span class="pg-btn disabled">Next →</span>
                            @endif
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal Tambah Siswa --}}
<div class="guru-modal-overlay" id="modalTambah">
    <div class="guru-modal">
        <div class="guru-modal-header">
            <h3>➕ Tambah Siswa</h3>
            <button type="button" class="guru-modal-close" data-close>&times;</button>
        </div>
        <div class="guru-modal-body">
            <form id="formTambah" method="POST" action="{{ route('guru.siswa.store') }}">
                @csrf
                <label>NIS</label>
                <input type="text" name="nis" required pattern="[0-9]+" placeholder="Hanya angka">
                <label>Nama lengkap</label>
                <input type="text" name="nama" required>
                <label>Kelas</label>
                <select name="kelas" required>
                    <option value="">— Pilih kelas —</option>
                    @foreach($kelasOptions as $k)
                        <option value="{{ $k }}">{{ $k }}</option>
                    @endforeach
                </select>
                <label>Jenis kelamin</label>
                <select name="jenis_kelamin">
                    <option value="">—</option>
                    <option value="Laki-laki">Laki-laki</option>
                    <option value="Perempuan">Perempuan</option>
                </select>
                <p style="font-size:12px;color:#888;margin:0 0 12px">Password default = NIS siswa</p>
                <button type="submit" class="btn-simpan-modal">💾 Simpan Siswa</button>
            </form>
        </div>
    </div>
</div>

{{-- Modal Import Excel --}}
<div class="guru-modal-overlay" id="modalImportExcel">
    <div class="guru-modal">
        <div class="guru-modal-header">
            <h3>📥 Import Siswa dari Excel</h3>
            <button type="button" class="guru-modal-close" data-close>&times;</button>
        </div>
        <div class="guru-modal-body">
            <div class="guru-modal-hint">
                💡 Kolom header: <strong>NIS</strong>, <strong>Nama</strong>, <strong>Kelas</strong>, <strong>Jenis Kelamin</strong> (opsional).
                Format kelas harus persis seperti <strong>X - 1</strong>. NIS yang sudah ada akan <strong>diperbarui</strong>.
                Password default siswa baru = NIS.
            </div>
            <p style="margin:0 0 10px">
                <a href="{{ route('guru.siswa.template') }}" style="color:#534AB7;font-weight:600;font-size:13px">⬇️ Download template CSV</a>
            </p>
            <label>Pilih file (.xlsx / .csv)</label>
            <input type="file" id="fileExcel" accept=".xlsx,.xls,.csv">
            <button type="button" class="btn-simpan-modal" id="btnProsesExcel">⚙️ Proses Import</button>
            <div id="resultExcel" class="import-result" style="display:none"></div>
        </div>
    </div>
</div>

{{-- Modal Import Absen --}}
<div class="guru-modal-overlay" id="modalImportAbsen">
    <div class="guru-modal guru-modal-lg">
        <div class="guru-modal-header">
            <h3>📋 Import dari Absen</h3>
            <button type="button" class="guru-modal-close" data-close>&times;</button>
        </div>
        <div class="guru-modal-body">
            <div id="absenStep1">
                <div class="guru-modal-hint">
                    File daftar hadir dengan sheet bernama <strong>X / XI / XII</strong>, blok per kelas
                    (baris “KELAS X - 1”), lalu kolom No · NIS · Nama · L/P.
                </div>
                <label>Pilih file absen (.xlsx)</label>
                <input type="file" id="fileAbsen" accept=".xlsx,.xls">
                <button type="button" class="btn-simpan-modal" id="btnBacaAbsen">📖 Baca File</button>
            </div>
            <div id="absenStep2" style="display:none">
                <p id="absenSummary" style="font-size:13px;margin:0 0 12px;color:#5F5E5A"></p>
                <div id="absenMapping"></div>
                <button type="button" class="btn-simpan-modal" id="btnSimpanAbsen" style="margin-top:14px">💾 Simpan ke Database</button>
                <button type="button" class="btn-secondary-modal" id="btnAbsenBack">← Ganti file</button>
            </div>
            <div id="resultAbsen" class="import-result" style="display:none"></div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
  var csrf = document.querySelector('meta[name="csrf-token"]').content;
  var kelasOptions = @json($kelasOptions);

  function openModal(id) {
    document.getElementById(id).classList.add('show');
  }
  function closeModal(el) {
    var o = el.closest ? el.closest('.guru-modal-overlay') : el;
    if (o) o.classList.remove('show');
  }
  document.querySelectorAll('[data-close]').forEach(function (btn) {
    btn.addEventListener('click', function () { closeModal(btn); });
  });
  document.querySelectorAll('.guru-modal-overlay').forEach(function (ov) {
    ov.addEventListener('click', function (e) { if (e.target === ov) ov.classList.remove('show'); });
  });

  document.getElementById('btnTambahSiswa').addEventListener('click', function () { openModal('modalTambah'); });
  document.getElementById('btnImportExcel').addEventListener('click', function () {
    document.getElementById('resultExcel').style.display = 'none';
    openModal('modalImportExcel');
  });
  document.getElementById('btnImportAbsen').addEventListener('click', function () {
    document.getElementById('absenStep1').style.display = '';
    document.getElementById('absenStep2').style.display = 'none';
    document.getElementById('resultAbsen').style.display = 'none';
    openModal('modalImportAbsen');
  });

  @if(request('open') === 'tambah')
  openModal('modalTambah');
  @endif

  // Import Excel
  document.getElementById('btnProsesExcel').addEventListener('click', function () {
    var file = document.getElementById('fileExcel').files[0];
    if (!file) { alert('Pilih file Excel terlebih dahulu!'); return; }
    var btn = this;
    btn.disabled = true;
    btn.textContent = 'Memproses...';
    var fd = new FormData();
    fd.append('file', file);
    fetch(@json(route('guru.siswa.import')), {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: fd
    }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
      .then(function (res) {
        var box = document.getElementById('resultExcel');
        box.style.display = 'block';
        box.className = 'import-result ' + (res.j.success ? 'ok' : 'err');
        box.textContent = res.j.message || res.j.error || 'Selesai';
        if (res.j.success) setTimeout(function () { location.reload(); }, 1200);
      })
      .catch(function () {
        var box = document.getElementById('resultExcel');
        box.style.display = 'block';
        box.className = 'import-result err';
        box.textContent = 'Gagal mengunggah file';
      })
      .finally(function () { btn.disabled = false; btn.textContent = '⚙️ Proses Import'; });
  });

  // Absen
  var absenSections = [];
  document.getElementById('btnBacaAbsen').addEventListener('click', function () {
    var file = document.getElementById('fileAbsen').files[0];
    if (!file) { alert('Pilih file absen terlebih dahulu!'); return; }
    var btn = this;
    btn.disabled = true;
    btn.textContent = 'Membaca...';
    var fd = new FormData();
    fd.append('file', file);
    fetch(@json(route('guru.siswa.importAbsen.preview')), {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: fd
    }).then(function (r) { return r.json(); })
      .then(function (j) {
        if (!j.success) { alert(j.error || 'Gagal membaca file'); return; }
        if (!j.sections || !j.sections.length) {
          alert('Tidak ada kelas terbaca. Pastikan sheet X/XI/XII dan format KELAS.');
          return;
        }
        absenSections = j.sections;
        document.getElementById('absenSummary').textContent = j.message;
        var map = document.getElementById('absenMapping');
        map.innerHTML = '';
        j.sections.forEach(function (sec, idx) {
          var dugaan = (sec.label || '').replace(/^KELAS\s*/i, '').trim();
          var grade = sec.sheet;
          var opts = kelasOptions.filter(function (k) { return k.indexOf(grade + ' ') === 0 || k.indexOf(grade + ' -') === 0; });
          var row = document.createElement('div');
          row.className = 'absen-map-row';
          var sel = '<select data-idx="' + idx + '"><option value="">— Pilih kelas sistem —</option>';
          opts.forEach(function (k) {
            sel += '<option value="' + k + '"' + (k === dugaan ? ' selected' : '') + '>' + k + '</option>';
          });
          sel += '</select>';
          row.innerHTML = '<div style="flex:1"><strong>' + sec.label + '</strong><br><span style="color:#888">' + sec.siswa.length + ' siswa · sheet ' + sec.sheet + '</span></div>' + sel;
          map.appendChild(row);
        });
        document.getElementById('absenStep1').style.display = 'none';
        document.getElementById('absenStep2').style.display = '';
      })
      .catch(function () { alert('Gagal membaca file'); })
      .finally(function () { btn.disabled = false; btn.textContent = '📖 Baca File'; });
  });

  document.getElementById('btnAbsenBack').addEventListener('click', function () {
    document.getElementById('absenStep1').style.display = '';
    document.getElementById('absenStep2').style.display = 'none';
  });

  document.getElementById('btnSimpanAbsen').addEventListener('click', function () {
    var rows = [];
    document.querySelectorAll('#absenMapping select').forEach(function (sel) {
      var idx = parseInt(sel.getAttribute('data-idx'), 10);
      var kelas = sel.value;
      if (!kelas || !absenSections[idx]) return;
      absenSections[idx].siswa.forEach(function (s) {
        rows.push({
          nis: s.nis,
          nama: s.nama,
          kelas: kelas,
          jenis_kelamin: s.jk === 'L' ? 'Laki-laki' : (s.jk === 'P' ? 'Perempuan' : null)
        });
      });
    });
    if (!rows.length) { alert('Pilih minimal satu mapping kelas'); return; }
    var btn = this;
    btn.disabled = true;
    fetch(@json(route('guru.siswa.importRows')), {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': csrf,
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify({ rows: rows })
    }).then(function (r) { return r.json(); })
      .then(function (j) {
        var box = document.getElementById('resultAbsen');
        box.style.display = 'block';
        box.className = 'import-result ' + (j.success ? 'ok' : 'err');
        box.textContent = j.message || j.error || 'Selesai';
        if (j.success) setTimeout(function () { location.reload(); }, 1200);
      })
      .finally(function () { btn.disabled = false; });
  });
})();
</script>
@endpush
