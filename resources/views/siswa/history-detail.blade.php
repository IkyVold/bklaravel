@extends('layouts.app')
@section('title', 'Detail Konseling')
@section('main_class', '')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/detailHistory.css') }}">
<style>
body:has(.detail-history-page) > main,
main:has(.detail-history-page) {
  max-width: none !important;
  margin: 0 !important;
  padding: 0 !important;
}
</style>
@endpush

@section('content')
@php
    $guruNama = $row->guru_bk ?? ($guru->nama ?? '-');
    $status = $row->status ?? 'Menunggu';
    $sk = $row->status_konfirmasi ?? 'Belum Dikonfirmasi';
    if (in_array($sk, ['Tervalidasi', 'Dikonfirmasi'], true)) {
        $statusKonfirmasi = 'Terkonfirmasi';
    } else {
        $statusKonfirmasi = $sk ?: 'Belum Dikonfirmasi';
    }
    $isTerkonfirmasi = $statusKonfirmasi === 'Terkonfirmasi';

    $statusBadgeClass = 'badge-proses';
    if ($status === 'Selesai') $statusBadgeClass = 'badge-selesai';
    elseif ($status === 'Dibatalkan') $statusBadgeClass = 'badge-dibatalkan';

    $hasLaporan = !empty($row->laporan_kesimpulan) || !empty($row->laporan_rekomendasi)
        || !empty($row->laporan_status_penanganan) || !empty($row->laporan);

    $showChatBtn = (($row->jenis ?? '') === 'Daring') && $isTerkonfirmasi && $status !== 'Dibatalkan' && $status !== 'Selesai';
    $canBatalkan = in_array($status, ['Menunggu', 'Proses'], true) && !$isTerkonfirmasi;

    $tgl = $row->tanggal ? \Carbon\Carbon::parse($row->tanggal)->translatedFormat('d M Y') : '—';
    $jam = $row->jam ? substr((string) $row->jam, 0, 5) : '—';
    $tglKonf = $row->tanggal_konfirmasi ? \Carbon\Carbon::parse($row->tanggal_konfirmasi)->translatedFormat('d M Y') : null;
    $jamKonf = $row->jam_konfirmasi ? substr((string) $row->jam_konfirmasi, 0, 5) : null;

    $laporanStatusClass = 'badge-selesai';
    $sp = $row->laporan_status_penanganan ?? '';
    if (str_contains(strtolower($sp), 'monitoring')) $laporanStatusClass = 'badge-proses';
    elseif (str_contains(strtolower($sp), 'rujuk')) $laporanStatusClass = 'badge-dibatalkan';

    $sesiSebelumnya = $sesiSebelumnya ?? null;
    $sesiLanjutan = $sesiLanjutan ?? collect();
@endphp

<div class="detail-history-page">
    <div class="connection-status">
        <span class="status-dot connected"></span>
        <span>Terhubung ke server</span>
    </div>

    <div class="page-wrap">
        <div class="breadcrumb">
            <a href="{{ route('siswa.dashboard') }}">Beranda</a>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
            <a href="{{ route('siswa.konseling.index') }}">Riwayat</a>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
            <span>Detail Konseling</span>
        </div>

        {{-- Header guru + status --}}
        <div class="header-card">
            <div class="header-card-left">
                <div class="guru-avatar">{{ strtoupper(mb_substr($guruNama, 0, 1)) }}</div>
                <div>
                    <div class="header-guru-name">{{ $guruNama }}</div>
                    <div class="header-guru-sub">Guru Bimbingan Konseling</div>
                </div>
            </div>
            <span class="badge {{ $statusBadgeClass }}">
                <span class="badge-dot"></span>
                {{ $status }}
            </span>
        </div>

        {{-- Rantai sesi --}}
        @if($sesiSebelumnya || $sesiLanjutan->isNotEmpty() || !empty($row->pengajuan_sebelumnya_id))
        <div class="info-card" style="border-color:#bfdbfe;background:#f8fbff">
            <div class="card-section-title">🔗 Rantai Sesi Konseling</div>

            @if($sesiSebelumnya)
                <div style="margin-bottom:12px">
                    <div style="font-size:12px;color:#64748b;margin-bottom:4px">Sesi sebelumnya</div>
                    <a href="{{ route('siswa.konseling.show', $sesiSebelumnya->id) }}"
                       style="display:block;padding:10px 12px;border-radius:8px;border:1px solid #bfdbfe;background:#fff;text-decoration:none;color:inherit">
                        <strong>#{{ $sesiSebelumnya->id }}</strong>
                        · {{ $sesiSebelumnya->tanggal ? \Carbon\Carbon::parse($sesiSebelumnya->tanggal)->format('Y-m-d') : '–' }}
                        · {{ $sesiSebelumnya->kategori ?: '–' }}
                        · <em>{{ $sesiSebelumnya->status }}</em>
                    </a>
                </div>
            @elseif(!empty($row->pengajuan_sebelumnya_id))
                <div style="margin-bottom:12px;font-size:13px">
                    Lanjutan dari sesi
                    <a href="{{ route('siswa.konseling.show', $row->pengajuan_sebelumnya_id) }}" style="color:#1d4ed8">#{{ $row->pengajuan_sebelumnya_id }}</a>
                </div>
            @endif

            @if($sesiLanjutan->isNotEmpty())
                <div style="font-size:12px;color:#64748b;margin-bottom:4px">Sesi lanjutan</div>
                <div style="display:grid;gap:8px">
                    @foreach($sesiLanjutan as $child)
                    <a href="{{ route('siswa.konseling.show', $child->id) }}"
                       style="display:block;padding:10px 12px;border-radius:8px;border:1px solid #bfdbfe;background:#fff;text-decoration:none;color:inherit">
                        <strong>#{{ $child->id }}</strong>
                        · {{ $child->tanggal ? \Carbon\Carbon::parse($child->tanggal)->format('Y-m-d') : '–' }}
                        · {{ $child->kategori ?: '–' }}
                        · <em>{{ $child->status }}</em>
                    </a>
                    @endforeach
                </div>
            @endif

            @if(!empty($row->pengajuan_sebelumnya_id) && $status === 'Proses')
                <p style="margin:12px 0 0;font-size:12.5px;color:#1e40af">
                    Ini adalah <strong>sesi monitoring lanjutan</strong> dari konseling sebelumnya.
                </p>
            @endif
        </div>
        @endif

        {{-- Info jadwal --}}
        <div class="info-card">
            <div class="card-section-title">📋 Informasi Jadwal</div>
            <div class="info-grid">
                <div class="info-cell">
                    <div class="info-cell-label">Tanggal</div>
                    <div class="info-cell-value">{{ $tgl }}</div>
                </div>
                <div class="info-cell">
                    <div class="info-cell-label">Jam</div>
                    <div class="info-cell-value">{{ $jam }}</div>
                </div>
                <div class="info-cell">
                    <div class="info-cell-label">Jenis</div>
                    <div class="info-cell-value">{{ $row->jenis ?: '—' }}</div>
                </div>
                <div class="info-cell">
                    <div class="info-cell-label">Kategori</div>
                    <div class="info-cell-value">{{ $row->kategori ?: '—' }}</div>
                </div>
                <div class="info-cell">
                    <div class="info-cell-label">Status Konfirmasi</div>
                    <div class="info-cell-value">{{ $statusKonfirmasi }}</div>
                </div>
                @if($isTerkonfirmasi && $tglKonf)
                <div class="info-cell">
                    <div class="info-cell-label">Jadwal Dikonfirmasi</div>
                    <div class="info-cell-value">{{ $tglKonf }}{{ $jamKonf ? ' · '.$jamKonf : '' }}</div>
                </div>
                @endif
            </div>
        </div>

        {{-- Deskripsi --}}
        <div class="desc-card">
            <div class="desc-label">Deskripsi Masalah</div>
            <p class="desc-text">{{ $row->deskripsi ?: 'Tidak ada deskripsi' }}</p>
        </div>

        {{-- Laporan guru --}}
        @if($hasLaporan)
        <div class="info-card">
            <div class="card-section-title">📝 Laporan Guru BK</div>
            <div class="laporan-list">
                <div class="laporan-item">
                    <div class="laporan-item-label">Kesimpulan Konseling</div>
                    <div class="laporan-item-value">{{ $row->laporan_kesimpulan ?: 'Tidak ada kesimpulan' }}</div>
                </div>
                <div class="laporan-item">
                    <div class="laporan-item-label">Rekomendasi / Tindak Lanjut</div>
                    <div class="laporan-item-value">{{ $row->laporan_rekomendasi ?: 'Tidak ada rekomendasi' }}</div>
                </div>
                <div class="laporan-item">
                    <div class="laporan-item-label">Status Penanganan</div>
                    <div class="laporan-item-value">
                        <span class="badge {{ $laporanStatusClass }}">
                            <span class="badge-dot"></span>
                            {{ $row->laporan_status_penanganan ?: 'Selesai' }}
                        </span>
                    </div>
                </div>
                @if(!empty($row->laporan_catatan_tambahan) && $row->laporan_catatan_tambahan !== '-')
                <div class="laporan-item">
                    <div class="laporan-item-label">Catatan Tambahan</div>
                    <div class="laporan-item-value">{{ $row->laporan_catatan_tambahan }}</div>
                </div>
                @endif
            </div>
        </div>
        @endif

        {{-- Alasan batal --}}
        @if($status === 'Dibatalkan')
        <div class="desc-card" style="border-color:#F0B8B8;background:#FDF6F6">
            <div class="desc-label" style="color:#A32D2D">Alasan Pembatalan</div>
            <p class="desc-text">{{ $row->alasan_batal ?? 'Dibatalkan oleh siswa' }}</p>
        </div>
        @endif

        {{-- Aksi --}}
        <div class="action-row">
            <a href="{{ route('siswa.konseling.index') }}" class="btn btn-outline">
                ← Kembali ke Riwayat
            </a>
            @if($canBatalkan)
            <button type="button" class="btn btn-batal" id="openBatalModal">
                Batalkan Pengajuan
            </button>
            @endif
            @if($showChatBtn)
            <a href="{{ route('siswa.chat', $row->id) }}" class="btn btn-primary" style="text-decoration:none">
                Mulai Chat Online
            </a>
            @endif
        </div>
    </div>
</div>

@if($canBatalkan)
<div class="batal-modal-overlay" id="batalOverlay" style="display:none">
    <div class="batal-modal">
        <h3 class="batal-modal-title">Batalkan Pengajuan?</h3>
        <p class="batal-modal-text">
            Pengajuan akan ditandai sebagai <strong>Dibatalkan</strong>. Tuliskan alasan (minimal 10 karakter).
        </p>
        <form method="POST" action="{{ route('siswa.konseling.batal', $row->id) }}" id="batalForm">
            @csrf
            <textarea name="alasan" id="alasanBatal" class="batal-modal-textarea" rows="4"
                      placeholder="Contoh: Jadwal bentrok dengan kegiatan sekolah..." required minlength="10"></textarea>
            <div class="batal-modal-hint" id="batalHint">Minimal 10 karakter</div>
            <div class="batal-modal-actions">
                <button type="button" class="btn btn-outline" id="closeBatalModal">Tutup</button>
                <button type="submit" class="btn btn-batal">Konfirmasi Batalkan</button>
            </div>
        </form>
    </div>
</div>
@endif
@endsection

@push('scripts')
@if($canBatalkan)
<script>
(function () {
    var overlay = document.getElementById('batalOverlay');
    var openBtn = document.getElementById('openBatalModal');
    var closeBtn = document.getElementById('closeBatalModal');
    var ta = document.getElementById('alasanBatal');
    var hint = document.getElementById('batalHint');
    var form = document.getElementById('batalForm');
    if (!overlay) return;
    function open() { overlay.style.display = 'flex'; }
    function close() { overlay.style.display = 'none'; }
    if (openBtn) openBtn.addEventListener('click', open);
    if (closeBtn) closeBtn.addEventListener('click', close);
    overlay.addEventListener('click', function (e) { if (e.target === overlay) close(); });
    if (ta && hint) {
        ta.addEventListener('input', function () {
            var n = ta.value.trim().length;
            if (n === 0) { hint.textContent = 'Minimal 10 karakter'; hint.style.color = ''; }
            else if (n < 10) { hint.textContent = (10 - n) + ' karakter lagi'; hint.style.color = '#A32D2D'; }
            else { hint.textContent = n + ' karakter'; hint.style.color = '#0F6E56'; }
        });
    }
    if (form) {
        form.addEventListener('submit', function (e) {
            if ((ta.value || '').trim().length < 10) {
                e.preventDefault();
                alert('Alasan pembatalan minimal 10 karakter.');
                return false;
            }
            if (!confirm('Yakin batalkan pengajuan ini?')) {
                e.preventDefault();
                return false;
            }
        });
    }
})();
</script>
@endif
@endpush
