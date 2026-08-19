@extends('layouts.app')
@section('title', 'Detail Konseling')
@section('main_class', '')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/guruBk.css') }}">
<link rel="stylesheet" href="{{ asset('css/guru-shell.css') }}">
<style>
.guru-bk-page .modal.show{display:flex!important;align-items:center;justify-content:center;position:fixed;inset:0;z-index:2000;background:rgba(16,24,40,.55);backdrop-filter:blur(3px);padding:20px;overflow-y:auto}
.guru-bk-page .modal-content{background:#fff;border-radius:16px;width:100%;max-width:800px;max-height:calc(100vh - 40px);overflow-y:auto;box-shadow:0 24px 60px rgba(16,24,40,.2);display:flex;flex-direction:column;margin:auto}
.guru-bk-page .modal-header{padding:18px 26px;display:flex;justify-content:space-between;align-items:center;background:linear-gradient(120deg,#26215C,#3C3489);color:#fff;border-radius:16px 16px 0 0}
.guru-bk-page .modal-header h3{margin:0;font-size:17px;font-weight:700}
.guru-bk-page .close-modal{background:rgba(255,255,255,.15);border:none;color:#fff;width:34px;height:34px;border-radius:50%;cursor:pointer;font-size:22px;text-decoration:none;display:flex;align-items:center;justify-content:center}
.guru-bk-page .modal-body{padding:26px}
.guru-bk-page .modal-footer{padding:16px 26px;border-top:1px solid #e8e6e0;display:flex;flex-wrap:wrap;justify-content:flex-end;gap:10px;background:#faf9f7;border-radius:0 0 16px 16px}
.guru-bk-page .detail-row{display:flex;margin-bottom:18px;padding-bottom:18px;border-bottom:1px solid #f1efe8;gap:12px}
.guru-bk-page .detail-label{width:170px;font-weight:700;color:#5F5E5A;font-size:13px;flex-shrink:0}
.guru-bk-page .detail-value{flex:1;color:#444;line-height:1.5;font-size:13.5px}
.guru-bk-page .detail-deskripsi{background:#EEEDFE;padding:18px;border-radius:10px;border-left:4px solid #7F77DD;white-space:pre-wrap;max-height:280px;overflow-y:auto}
.guru-bk-page .detail-hero{margin-bottom:20px;padding:15px;background:linear-gradient(135deg,rgba(83,74,183,.12),rgba(60,52,137,.12));border-radius:12px}
.guru-bk-page .detail-hero-inner{display:flex;align-items:center;gap:15px}
.guru-bk-page .detail-avatar{width:50px;height:50px;border-radius:50%;background:linear-gradient(135deg,#534AB7,#7F77DD);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:18px}
.guru-bk-page .info-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.guru-bk-page .konfirmasi-box{display:grid;grid-template-columns:1fr 1fr;gap:12px;padding:14px;background:#faf9f7;border-radius:10px;border:.5px solid #e8e6e0}
.guru-bk-page .konfirmasi-box label{display:block;font-size:12px;font-weight:600;margin-bottom:4px;color:#5F5E5A}
.guru-bk-page .konfirmasi-box input,.guru-bk-page .konfirmasi-box select{width:100%;padding:8px 10px;border:.5px solid #d3d1c7;border-radius:8px;font-size:14px}
.guru-bk-page .status-pill{display:inline-flex;padding:4px 12px;border-radius:999px;font-size:12px;font-weight:600}
.guru-bk-page .status-proses{background:#FFF1E7;color:#993C1D}
.guru-bk-page .status-selesai{background:#E1F5EE;color:#0F6E56}
.guru-bk-page .status-dibatalkan{background:#FCEBEB;color:#A32D2D}
.guru-bk-page .status-belum{background:#F1EFE8;color:#5F5E5A}
.guru-bk-page .laporan-box{background:#E1F5EE;border-radius:10px;padding:18px;border-left:4px solid #1D9E75;margin-top:8px}
.guru-bk-page .btn{display:inline-flex;align-items:center;gap:6px;padding:10px 16px;border-radius:10px;border:none;cursor:pointer;font-size:13.5px;font-weight:600;text-decoration:none;font-family:inherit}
.guru-bk-page .btn-konfirmasi{background:#0F6E56;color:#fff}
.guru-bk-page .btn-batal{background:#FCEBEB;color:#A32D2D}
.guru-bk-page .btn-detail{background:#F1EFE8;color:#444}
.guru-bk-page .btn-laporan{background:#534AB7;color:#fff}
.guru-bk-page .btn-locked{background:#e8e6e0;color:#888;cursor:not-allowed}
.guru-bk-page .laporan-form label{display:block;font-size:12px;font-weight:600;color:#5F5E5A;margin-bottom:4px}
.guru-bk-page .laporan-form textarea,.guru-bk-page .laporan-form select,.guru-bk-page .laporan-form input{width:100%;padding:10px 12px;border:.5px solid #d3d1c7;border-radius:8px;font-size:14px;margin-bottom:12px;box-sizing:border-box;font-family:inherit}
.guru-bk-page .lanjutan-box{margin-top:12px;padding:14px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;display:none}
.guru-bk-page .lanjutan-box.show{display:block}
.guru-bk-page .edit-hint{background:#FFF8EB;border-left:4px solid #EF9F27;padding:10px 14px;border-radius:8px;margin-bottom:14px;font-size:12.5px;color:#854F0B}
@media(max-width:640px){.guru-bk-page .detail-row{flex-direction:column}.guru-bk-page .detail-label{width:auto}.guru-bk-page .info-grid,.guru-bk-page .konfirmasi-box{grid-template-columns:1fr}}
</style>
@endpush

@section('content')
@php
    $siswa = $row->siswa;
    $namaSiswa = $siswa->nama ?? '-';
    $initial = strtoupper(mb_substr($namaSiswa, 0, 1));
    $sk = $row->status_konfirmasi ?? 'Belum Dikonfirmasi';
    if (in_array($sk, ['Tervalidasi', 'Dikonfirmasi'], true)) $skLabel = 'Terkonfirmasi';
    elseif (in_array($sk, ['Belum Dikonfirmasi'], true)) $skLabel = 'Belum Dikonfirmasi';
    else $skLabel = $sk ?: 'Belum Dikonfirmasi';

    $status = $row->status ?? 'Proses';
    $belumKonfirmasi = $skLabel !== 'Terkonfirmasi' && $status === 'Proses';
    $sudahKonfirmasiBelumSelesai = $skLabel === 'Terkonfirmasi' && $status === 'Proses';
    $hasLaporan = !empty($row->laporan_kesimpulan) || !empty($row->laporan_created_at);
    $canEditLaporan = false;
    $sisaEditText = '';
    if ($hasLaporan && $row->laporan_created_at) {
        $jamBerlalu = \Carbon\Carbon::parse($row->laporan_created_at)->diffInMinutes(now()) / 60;
        $canEditLaporan = $jamBerlalu <= 72;
        $sisa = max(0, 72 - $jamBerlalu);
        if ($sisa <= 0) $sisaEditText = 'Waktu edit sudah habis (batas 72 jam)';
        elseif ($sisa < 1) $sisaEditText = 'Sisa ' . round($sisa * 60) . ' menit untuk edit';
        else $sisaEditText = 'Sisa ' . round($sisa) . ' jam untuk edit laporan';
    }

    $tglDiajukan = $row->tanggal ? \Carbon\Carbon::parse($row->tanggal)->format('Y-m-d') : '';
    $jamDiajukan = $row->jam ? substr((string)$row->jam, 0, 5) : '';
    $tglKonf = $row->tanggal_konfirmasi ? \Carbon\Carbon::parse($row->tanggal_konfirmasi)->format('Y-m-d') : ($tglDiajukan ?: now()->format('Y-m-d'));
    $jamKonf = $row->jam_konfirmasi ? substr((string)$row->jam_konfirmasi, 0, 5) : ($jamDiajukan ?: '08:00');
    $jamOptions = [];
    for ($h = 7; $h <= 17; $h++) foreach (['00','30'] as $m) $jamOptions[] = sprintf('%02d:%s', $h, $m);
    $guruNama = $row->guru_bk ?? (session('auth_user')['nama'] ?? 'Guru BK');
    $statusPenangananOptions = [
        'Selesai - Masalah Teratasi' => '✅ Selesai - Masalah Teratasi',
        'Monitoring' => '📊 Perlu Monitoring Lanjutan',
        'Rujuk' => '🔄 Dirujuk ke pihak lain (Guru Mapel/Wali Kelas)',
        'Rujuk BK Lain' => '👨‍🏫 Dirujuk ke Guru BK Lain',
        'Orang Tua' => '👨‍👩‍👧 Perlu keterlibatan Orang Tua',
    ];
    $lanjutanDefault = now()->addDays(7)->format('Y-m-d');
@endphp

<div class="guru-bk-page">
    <div class="modal show" role="dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3>📋 Detail Konseling &amp; Konfirmasi Jadwal</h3>
                <a href="{{ route('guru.konseling.index') }}" class="close-modal">&times;</a>
            </div>
            <div class="modal-body">
                <div class="detail-hero">
                    <div class="detail-hero-inner">
                        <div class="detail-avatar">{{ $initial }}</div>
                        <div>
                            <div style="font-size:20px;font-weight:700">{{ $namaSiswa }}
                                @if(!empty($row->input_manual))<span style="font-size:11px;background:#E1F5EE;color:#0F6E56;padding:2px 10px;border-radius:20px;margin-left:6px">✍️ Walk-in</span>@endif
                            </div>
                            <div style="font-size:13px;color:#5F5E5A;margin-top:4px">
                                NIS: {{ $siswa->nis ?? '-' }} · Kelas: {{ $row->kelas_siswa ?? $siswa->kelas ?? '-' }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="detail-row">
                    <div class="detail-label">Informasi Konseling:</div>
                    <div class="detail-value">
                        <div class="info-grid">
                            <div><strong>Guru BK:</strong> {{ $guruNama }}</div>
                            <div><strong>Tanggal diajukan:</strong> {{ $tglDiajukan ?: '–' }}</div>
                            <div><strong>Jam diajukan:</strong> {{ $jamDiajukan ?: '–' }}</div>
                            <div><strong>Jenis:</strong> {{ $row->jenis ?: '–' }}</div>
                            <div><strong>Kategori:</strong> {{ $row->kategori ?: '–' }}</div>
                            <div><strong>Status:</strong>
                                <span class="status-pill {{ $skLabel==='Terkonfirmasi'?'status-selesai':'status-belum' }}">{{ $skLabel }}</span>
                                <span class="status-pill {{ $status==='Selesai'?'status-selesai':($status==='Dibatalkan'?'status-dibatalkan':'status-proses') }}">{{ $status }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                @if($belumKonfirmasi || $sudahKonfirmasiBelumSelesai)
                <div class="detail-row">
                    <div class="detail-label">{{ $belumKonfirmasi ? 'Konfirmasi Jadwal:' : 'Ubah Jadwal:' }}</div>
                    <div class="detail-value">
                        <form id="formKonfirmasi" method="POST" action="{{ route('guru.konseling.konfirmasi', $row->id) }}">
                            @csrf
                            <input type="hidden" name="status_konfirmasi" value="Terkonfirmasi">
                            <div class="konfirmasi-box">
                                <div>
                                    <label>Tanggal</label>
                                    <input type="date" name="tanggal_konfirmasi" value="{{ $tglKonf }}" required>
                                </div>
                                <div>
                                    <label>Jam</label>
                                    <select name="jam_konfirmasi" required>
                                        @foreach($jamOptions as $j)
                                            <option value="{{ $j }}" @selected($jamKonf===$j)>{{ $j }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                @elseif($skLabel === 'Terkonfirmasi')
                <div class="detail-row">
                    <div class="detail-label">Jadwal dikonfirmasi:</div>
                    <div class="detail-value">{{ $tglKonf }} · {{ $jamKonf }}</div>
                </div>
                @endif

                <div class="detail-row">
                    <div class="detail-label">Deskripsi Masalah:</div>
                    <div class="detail-value">
                        <div class="detail-deskripsi">{{ $row->deskripsi ?: 'Tidak ada deskripsi' }}</div>
                    </div>
                </div>

                @if($status === 'Dibatalkan')
                <div class="detail-row">
                    <div class="detail-label">Alasan Pembatalan:</div>
                    <div class="detail-value"><div class="detail-deskripsi" style="background:#FDF6F6;border-color:#F0B8B8">{{ $row->alasan_batal ?? 'Dibatalkan' }}</div></div>
                </div>
                @endif

                @if($hasLaporan)
                <div class="laporan-box">
                    <div style="font-weight:700;color:#0F6E56;margin-bottom:10px">📋 Laporan Hasil Konseling</div>
                    <p><strong>Kesimpulan:</strong> {{ $row->laporan_kesimpulan }}</p>
                    <p><strong>Rekomendasi:</strong> {{ $row->laporan_rekomendasi }}</p>
                    <p><strong>Status penanganan:</strong> {{ $row->laporan_status_penanganan }}</p>
                    @if($row->laporan_catatan_tambahan && $row->laporan_catatan_tambahan !== '-')
                        <p><strong>Catatan:</strong> {{ $row->laporan_catatan_tambahan }}</p>
                    @endif
                    <p style="font-size:12px;color:#5F5E5A">{{ $sisaEditText }}</p>
                </div>
                @endif

                {{-- Form laporan --}}
                @if($sudahKonfirmasiBelumSelesai || ($status==='Selesai' && $canEditLaporan))
                <div id="laporanSection" style="margin-top:20px;{{ $sudahKonfirmasiBelumSelesai && !$hasLaporan ? 'display:none' : '' }}">
                    @if($canEditLaporan)
                        <div class="edit-hint">Mode edit — {{ $sisaEditText }}</div>
                    @endif
                    <form method="POST" action="{{ route('guru.konseling.laporan', $row->id) }}" class="laporan-form" id="formLaporan">
                        @csrf
                        <label>Kesimpulan Konseling *</label>
                        <textarea name="laporan_kesimpulan" rows="3" required>{{ old('laporan_kesimpulan', $row->laporan_kesimpulan) }}</textarea>
                        <label>Rekomendasi / Tindak Lanjut *</label>
                        <textarea name="laporan_rekomendasi" rows="3" required>{{ old('laporan_rekomendasi', $row->laporan_rekomendasi) }}</textarea>
                        <label>Status Penanganan *</label>
                        <select name="laporan_status_penanganan" id="statusPenanganan" required>
                            @foreach($statusPenangananOptions as $val => $label)
                                <option value="{{ $val }}" @selected(old('laporan_status_penanganan', $row->laporan_status_penanganan)===$val)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <label>Catatan tambahan</label>
                        <textarea name="laporan_catatan_tambahan" rows="2">{{ old('laporan_catatan_tambahan', $row->laporan_catatan_tambahan === '-' ? '' : $row->laporan_catatan_tambahan) }}</textarea>

                        @if(!$hasLaporan)
                        <div class="lanjutan-box" id="lanjutanBox">
                            <label style="display:flex;gap:8px;align-items:center;cursor:pointer;color:#1e40af;font-weight:600">
                                <input type="checkbox" name="buat_lanjutan" value="1" id="chkLanjutan" checked>
                                Buat sesi lanjutan (jadwal monitoring)
                            </label>
                            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-top:10px">
                                <div>
                                    <label>Tanggal lanjutan</label>
                                    <input type="date" name="lanjutan_tanggal" value="{{ $lanjutanDefault }}">
                                </div>
                                <div>
                                    <label>Jam</label>
                                    <select name="lanjutan_jam">
                                        @foreach($jamOptions as $j)
                                            <option value="{{ $j }}" @selected($j==='09:00')>{{ $j }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label>Jenis</label>
                                    <select name="lanjutan_jenis">
                                        <option value="Luring">Luring</option>
                                        <option value="Daring" @selected($row->jenis==='Daring')>Daring</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        @endif

                        <button type="submit" class="btn btn-laporan" id="btnSubmitLaporan" style="margin-top:8px">
                            💾 {{ $hasLaporan ? 'Perbarui Laporan' : 'Simpan Laporan & Selesaikan' }}
                        </button>
                    </form>
                </div>
                @elseif($status==='Selesai' && $hasLaporan && !$canEditLaporan)
                <div class="edit-hint" style="margin-top:16px">🔒 Laporan terkunci — batas edit 72 jam sudah lewat.</div>
                @endif
            </div>

            <div class="modal-footer">
                @if($belumKonfirmasi)
                    <button type="submit" form="formKonfirmasi" class="btn btn-konfirmasi">✅ Konfirmasi Jadwal</button>
                    <form method="POST" action="{{ route('guru.konseling.batal', $row->id) }}" style="margin:0" onsubmit="return confirm('Batalkan pengajuan ini? Hanya bisa sebelum dikonfirmasi.')">
                        @csrf
                        <input type="hidden" name="alasan" value="Dibatalkan oleh Guru BK">
                        <button type="submit" class="btn btn-batal">❌ Batalkan</button>
                    </form>
                    <a href="{{ route('guru.konseling.index') }}" class="btn btn-detail">📁 Tutup</a>
                @elseif($sudahKonfirmasiBelumSelesai)
                    <button type="submit" form="formKonfirmasi" class="btn btn-konfirmasi">🔄 Ubah Jadwal</button>
                    <button type="button" class="btn btn-laporan" id="btnShowLaporan">📝 Buat Laporan &amp; Selesaikan</button>
                    <a href="{{ route('guru.konseling.index') }}" class="btn btn-detail">📁 Tutup</a>
                    {{-- Setelah dikonfirmasi: TIDAK ada tombol Batalkan --}}
                @elseif($status==='Selesai' && $canEditLaporan)
                    <button type="button" class="btn btn-laporan" id="btnShowLaporan">✏️ Edit Laporan</button>
                    <a href="{{ route('guru.konseling.index') }}" class="btn btn-detail">📁 Tutup</a>
                @elseif($status==='Selesai')
                    <span class="btn btn-locked">🔒 Laporan Terkunci</span>
                    <a href="{{ route('guru.konseling.index') }}" class="btn btn-detail">📁 Tutup</a>
                @else
                    <a href="{{ route('guru.konseling.index') }}" class="btn btn-detail">📁 Tutup</a>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function(){
  var btn = document.getElementById('btnShowLaporan');
  var sec = document.getElementById('laporanSection');
  if (btn && sec) btn.addEventListener('click', function(){ sec.style.display='block'; sec.scrollIntoView({behavior:'smooth',block:'nearest'}); });

  var sel = document.getElementById('statusPenanganan');
  var box = document.getElementById('lanjutanBox');
  var chk = document.getElementById('chkLanjutan');
  var submitBtn = document.getElementById('btnSubmitLaporan');
  function syncLanjutan() {
    if (!sel || !box) return;
    var mon = sel.value === 'Monitoring';
    box.classList.toggle('show', mon);
    if (mon && chk) chk.checked = true;
    if (submitBtn && !@json($hasLaporan)) {
      submitBtn.textContent = mon
        ? '💾 Simpan Laporan + Buat Sesi Lanjutan'
        : '💾 Simpan Laporan & Selesaikan';
    }
  }
  if (sel) { sel.addEventListener('change', syncLanjutan); syncLanjutan(); }
})();
</script>
@endpush
