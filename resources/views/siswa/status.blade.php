@extends('layouts.app')

@section('title', 'Status Konseling')
@section('main_class', '')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/status.css') }}">
@endpush

@section('content')
@php
    $namaGuru = $row->guru_bk ?? '-';
    $spesialisasi = $guru?->spesialisasi ?? '-';
    $npsn = $guru?->npsn ?? '-';
    $tanggal = $row->tanggal
        ? \Carbon\Carbon::parse($row->tanggal)->locale('id')->translatedFormat('d F Y')
        : '-';
    $jam = $row->jam ? substr((string) $row->jam, 0, 5) : '-';
    $jenis = $row->jenis ?? '-';
    $kategori = $row->kategori ?? '-';
    $deskripsi = $row->deskripsi ?: 'Tidak ada deskripsi';
    $status = $row->status ?? 'Menunggu';
    $statusKonfirmasi = $row->status_konfirmasi ?? 'Belum Dikonfirmasi';

    $statusBadgeClass = 'badge badge-process';
    $statusBadgeLabel = $status;
    if ($status === 'Selesai') {
        $statusBadgeClass = 'badge badge-validated';
        $statusBadgeLabel = 'Selesai';
    } elseif ($status === 'Dibatalkan') {
        $statusBadgeClass = 'badge badge-pending';
        $statusBadgeLabel = 'Dibatalkan';
    }

    $isTerkonfirmasi = in_array($statusKonfirmasi, ['Terkonfirmasi', 'Dikonfirmasi'], true);
    $showChatBtn = $isTerkonfirmasi && $jenis === 'Daring';
@endphp

<div class="status-page">
    <div class="page">
        <div class="page-header">
            <div class="page-badge"><span class="dot"></span> Live Tracking</div>
            <h1 class="page-title">Status Konseling</h1>
            <p class="page-subtitle">Jadwal kemungkinan berubah terkait konfirmasi guru BK</p>
            <div>
                <span class="conn-pill">
                    <span class="conn-indicator connected"></span>
                    <span>Terhubung ke server</span>
                </span>
            </div>
        </div>

        @if(($sesiSebelumnya ?? null) || (($sesiLanjutan ?? collect())->isNotEmpty()) || !empty($row->pengajuan_sebelumnya_id))
        <div class="card" style="border-color:#bfdbfe;background:#f8fbff;margin-bottom:16px">
            <div class="card-header" style="background:transparent;border-bottom:1px solid #dbeafe">
                <span class="card-header-label">🔗 Rantai Sesi Konseling</span>
            </div>
            <div style="padding:14px 16px">
                @if($sesiSebelumnya ?? null)
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
                @if(($sesiLanjutan ?? collect())->isNotEmpty())
                    <div style="font-size:12px;color:#64748b;margin-bottom:4px">Sesi lanjutan</div>
                    <div style="display:grid;gap:8px">
                        @foreach($sesiLanjutan as $child)
                        <a href="{{ route('siswa.status', $child->id) }}"
                           style="display:block;padding:10px 12px;border-radius:8px;border:1px solid #bfdbfe;background:#fff;text-decoration:none;color:inherit">
                            <strong>#{{ $child->id }}</strong>
                            · {{ $child->tanggal ? \Carbon\Carbon::parse($child->tanggal)->format('Y-m-d') : '–' }}
                            {{ $child->jam ? substr((string)$child->jam,0,5) : '' }}
                            · {{ $child->kategori ?: '–' }}
                            · <em>{{ $child->status }}</em>
                        </a>
                        @endforeach
                    </div>
                @endif
                @if(!empty($row->pengajuan_sebelumnya_id))
                    <p style="margin:12px 0 0;font-size:12.5px;color:#1e40af">
                        Ini adalah <strong>sesi monitoring lanjutan</strong>. Jadwal ditetapkan oleh Guru BK setelah laporan “Perlu Monitoring Lanjutan”.
                    </p>
                @endif
            </div>
        </div>
        @endif

        <div class="card">
            <div class="card-header">
                <span class="card-header-label">Informasi Guru &amp; Jadwal</span>
                <span class="{{ $statusBadgeClass }}">
                    <svg width="8" height="8" viewBox="0 0 8 8" fill="currentColor"><circle cx="4" cy="4" r="3" /></svg>
                    {{ $statusBadgeLabel }}
                </span>
            </div>
            <div class="info-grid">
                <div class="info-row">
                    <span class="info-label">Nama Guru</span>
                    <span class="info-value">{{ $namaGuru }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Spesialis Bidang</span>
                    <span class="info-value">{{ $spesialisasi }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">NPSN</span>
                    <span class="info-value">{{ $npsn }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Tanggal</span>
                    <span class="info-value">{{ $tanggal }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Jam</span>
                    <span class="info-value">{{ $jam }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Jenis Konseling</span>
                    <span class="info-value">
                        @if($jenis === 'Daring')
                            <span class="badge badge-daring">Daring</span>
                        @else
                            {{ $jenis }}
                        @endif
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Kategori</span>
                    <span class="info-value">{{ $kategori }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Status Konfirmasi</span>
                    <span class="info-value">
                        @if($isTerkonfirmasi)
                            <span class="badge badge-validated">✓ Terkonfirmasi</span>
                        @else
                            <span class="badge badge-pending">Belum Dikonfirmasi</span>
                        @endif
                    </span>
                </div>
            </div>
        </div>

        <div class="desc-card">
            <div class="desc-label">Deskripsi Masalah</div>
            <p class="desc-text">{{ $deskripsi }}</p>
        </div>

        <div class="actions">
            <a href="{{ route('siswa.konseling.index') }}" class="btn btn-primary" style="text-decoration:none">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="16" height="16">
                    <polyline points="20 6 9 17 4 12" />
                </svg>
                Selesai
            </a>
            <form method="POST" action="{{ route('siswa.konseling.batal', $row->id) }}" style="display:inline" class="js-konsul-ulang-form"
                  onsubmit="return (function(f){
                      var alasan = prompt('Alasan pembatalan (minimal 10 karakter):', 'Ingin mengajukan ulang konsultasi');
                      if (alasan === null) return false;
                      alasan = alasan.trim();
                      if (alasan.length < 10) { alert('Alasan pembatalan minimal 10 karakter.'); return false; }
                      f.querySelector('input[name=alasan]').value = alasan;
                      return confirm('Apakah Anda yakin ingin membatalkan pengajuan ini dan mengajukan ulang?');
                  })(this)">
                @csrf
                <input type="hidden" name="alasan" value="">
                <input type="hidden" name="ajukan_ulang" value="1">
                <button type="submit" class="btn btn-secondary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                        <polyline points="1 4 1 10 7 10" />
                        <path d="M3.51 15a9 9 0 1 0 .49-3.51" />
                    </svg>
                    Konsul Ulang
                </button>
            </form>
            @if($showChatBtn)
                <a href="{{ route('siswa.chat', $row->id) }}" class="btn btn-chat-online" style="text-decoration:none" title="Chat online (fitur chat penuh menyusul)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
                    </svg>
                    Mulai Chat Online
                </a>
            @endif
        </div>
    </div>
</div>
@endsection
