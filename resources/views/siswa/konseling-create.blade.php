@extends('layouts.app')

@section('title', 'Ajukan Penjadwalan Konseling')
@section('main_class', '')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/jadwal.css') }}">
<link rel="stylesheet" href="{{ asset('css/siswa-history-shell.css') }}">
@endpush

@section('content')
@php
    $today = now()->format('Y-m-d');
    $jamList = [
        '07:00','07:30','08:00','08:30','09:00','09:30','10:00','10:30',
        '11:00','11:30','12:00','12:30','13:00','13:30','14:00','14:30',
        '15:00','15:30','16:00','16:30','17:00',
    ];
    $guruNama = $selectedGuru ?? old('guru_bk', '');
@endphp
<div class="jadwal-page">
    <div class="page-wrap">
        <div class="breadcrumb">
            <a href="{{ route('siswa.dashboard') }}">Beranda</a>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6" /></svg>
            <a href="{{ route('siswa.konseling.create') }}">Konseling</a>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6" /></svg>
            <span>Penjadwalan</span>
        </div>

        <div class="page-title">Ajukan Penjadwalan Konseling</div>
        <div class="page-sub">
            Isi formulir berikut dengan lengkap agar Guru BK dapat mempersiapkan sesi konseling terbaik untuk Anda.
        </div>

        <div class="guru-card">
            <div class="guru-avatar">{{ $guruNama ? strtoupper(mb_substr($guruNama, 0, 1)) : 'G' }}</div>
            <div>
                <div class="guru-label">Guru BK yang dipilih</div>
                <div class="guru-name">{{ $guruNama ?: '–' }}</div>
            </div>
        </div>

        <div class="notice notice-info">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10" /><path d="M12 16v-4M12 8h.01" />
            </svg>
            Isi deskripsi masalah dengan jelas dan detail agar Guru BK dapat memahami situasi Anda dengan lebih baik.
        </div>

        <form method="POST" action="{{ route('siswa.konseling.store') }}" id="konselingForm">
            @csrf
            <input type="hidden" name="guru_id" value="{{ $selectedGuruId ?? '' }}">
            <input type="hidden" name="guru_bk" value="{{ $guruNama }}">


            {{-- Tipe Jadwal: Rutin vs Nonrutin --}}
            <div class="form-card" style="margin-top:16px">
                <div class="form-section-title">Tipe Jadwal Konseling</div>
                <div class="form-body">
                    <div class="form-group">
                        <label>Pilih tipe jadwal</label>
                        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:8px">
                            <label style="display:flex;align-items:center;gap:8px;padding:12px 16px;border:1.5px solid #d3d1c7;border-radius:10px;cursor:pointer;flex:1;min-width:180px">
                                <input type="radio" name="tipe_jadwal" value="Rutin" id="tipeRutin"
                                    {{ old('tipe_jadwal') === 'Rutin' ? 'checked' : '' }}>
                                <span><strong>Rutin</strong><br><small style="color:#888">Pilih slot jadwal tetap Guru BK</small></span>
                            </label>
                            <label style="display:flex;align-items:center;gap:8px;padding:12px 16px;border:1.5px solid #d3d1c7;border-radius:10px;cursor:pointer;flex:1;min-width:180px">
                                <input type="radio" name="tipe_jadwal" value="Nonrutin" id="tipeNonrutin"
                                    {{ old('tipe_jadwal', 'Nonrutin') === 'Nonrutin' ? 'checked' : '' }}>
                                <span><strong>Nonrutin</strong><br><small style="color:#888">Ajukan tanggal &amp; jam bebas (insidental)</small></span>
                            </label>
                        </div>
                    </div>

                    <div id="panelRutin" style="display:none;margin-top:14px">
                        @php $slotsRutin = $slotsRutin ?? collect(); @endphp
                        @if($slotsRutin->isEmpty())
                            <div class="notice" style="padding:12px;background:#FAEEDA;border-radius:8px;font-size:13px;color:#633806">
                                Guru BK ini belum mengatur slot <strong>jadwal rutin</strong>.
                                Silakan pilih <strong>Nonrutin</strong>, atau hubungi Guru BK.
                            </div>
                        @else
                            <div class="form-group">
                                <label for="jadwal_rutin_id">Slot jadwal rutin</label>
                                <select name="jadwal_rutin_id" id="jadwal_rutin_id">
                                    <option value="">— Pilih slot —</option>
                                    @foreach($slotsRutin as $s)
                                        <option value="{{ $s->id }}"
                                            data-hari="{{ $s->hari }}"
                                            data-jam="{{ substr((string)$s->jam_mulai,0,5) }}"
                                            data-jenis="{{ $s->jenis }}"
                                            @selected(old('jadwal_rutin_id') == $s->id)>
                                            {{ $s->hari_label }} · {{ $s->jam_label }} · {{ $s->jenis }}
                                            @if($s->keterangan) ({{ $s->keterangan }}) @endif
                                        </option>
                                    @endforeach
                                </select>
                                <small style="color:#888;display:block;margin-top:6px">
                                    Tanggal yang Anda pilih harus sesuai hari slot (mis. slot Senin → pilih tanggal yang hari Senin).
                                </small>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="form-card" style="margin-top:16px">
                <div class="form-section-title">Waktu &amp; Jenis Konseling</div>
                <div class="form-body">
                    <div class="field">
                        <label for="jenis-konseling">Jenis konseling</label>
                        <select id="jenis-konseling" name="jenis" required>
                            <option value="Luring" @selected(old('jenis', 'Luring') === 'Luring')>Luring (Tatap Muka)</option>
                            <option value="Daring" @selected(old('jenis') === 'Daring')>Daring (Online)</option>
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="field">
                            <label for="tanggal">Tanggal</label>
                            <input
                                type="date"
                                id="tanggal"
                                name="tanggal"
                                min="{{ $today }}"
                                value="{{ old('tanggal') }}"
                                required
                            >
                        </div>
                        <div class="field">
                            <label for="jam">Jam</label>
                            <select id="jam" name="jam" required>
                                <option value="">Pilih jam</option>
                                @foreach($jamList as $j)
                                    <option value="{{ $j }}" @selected(old('jam') === $j)>{{ $j }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-card" style="margin-top:12px">
                <div class="form-section-title">Detail Masalah</div>
                <div class="form-body">
                    <div class="field">
                        <label for="kategori">Kategori masalah</label>
                        <select id="kategori" name="kategori" required>
                            <option value="">Pilih kategori</option>
                            @foreach(\App\Support\KategoriKonseling::ALL as $kat)
                                <option value="{{ $kat }}" @selected(old('kategori') === $kat)>{{ $kat }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="deskripsi">Deskripsi masalah</label>
                        <textarea
                            id="deskripsi"
                            name="deskripsi"
                            placeholder="Jelaskan masalah atau topik yang ingin dikonsultasikan dengan detail...

Contoh:
- Apa yang terjadi?
- Kapan kejadiannya?
- Siapa saja yang terlibat?
- Bagaimana perasaan Anda?"
                            required
                        >{{ old('deskripsi') }}</textarea>
                        <div class="char-hint" id="charHint" style="color:#b4b2a9">Minimal 20 karakter</div>
                    </div>
                </div>
            </div>

            <div class="notice notice-chat" id="noticeDaring" style="margin-top:12px;display:none">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
                </svg>
                Konseling daring dipilih. Setelah jadwal dikonfirmasi oleh Guru BK, Anda dapat mengakses fitur chat
                real-time di halaman Status.
            </div>

            <div class="notice notice-warning" style="margin-top:12px">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
                    <line x1="12" y1="9" x2="12" y2="13" /><line x1="12" y1="17" x2="12.01" y2="17" />
                </svg>
                Jadwal konseling dapat berubah sesuai konfirmasi dari Guru BK. Periksa status secara berkala di halaman
                Status.
            </div>

            <div class="form-actions" style="margin-top:24px">
                <a href="{{ route('siswa.konseling.create') }}" class="btn-cancel">Kembali</a>
                <button type="submit" class="btn-submit" id="submitBtn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="22" y1="2" x2="11" y2="13" /><polygon points="22 2 15 22 11 13 2 9 22 2" />
                    </svg>
                    Kirim Pengajuan
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var ta = document.getElementById('deskripsi');
    var hint = document.getElementById('charHint');
    var jenis = document.getElementById('jenis-konseling');
    var noticeDaring = document.getElementById('noticeDaring');
    var form = document.getElementById('konselingForm');

    function updateHint() {
        var len = (ta.value || '').trim().length;
        if (len === 0) {
            hint.textContent = 'Minimal 20 karakter';
            hint.style.color = '#b4b2a9';
        } else if (len < 20) {
            hint.textContent = (20 - len) + ' karakter lagi';
            hint.style.color = '#A32D2D';
        } else {
            hint.textContent = len + ' karakter';
            hint.style.color = '#0F6E56';
        }
    }

    function updateDaring() {
        noticeDaring.style.display = jenis.value === 'Daring' ? 'flex' : 'none';
    }

    if (ta) {
        ta.addEventListener('input', updateHint);
        updateHint();
    }
    if (jenis) {
        jenis.addEventListener('change', updateDaring);
        updateDaring();
    }

    
    // Tipe Rutin / Nonrutin
    var tipeRutin = document.getElementById('tipeRutin');
    var tipeNon = document.getElementById('tipeNonrutin');
    var panelRutin = document.getElementById('panelRutin');
    var slotSel = document.getElementById('jadwal_rutin_id');
    var jamSel = document.getElementById('jam');
    var jenisSel = document.getElementById('jenis-konseling');

    function syncTipe() {
        var isRutin = tipeRutin && tipeRutin.checked;
        if (panelRutin) panelRutin.style.display = isRutin ? 'block' : 'none';
        if (slotSel) slotSel.required = !!isRutin;
    }
    function applySlot() {
        if (!slotSel || !slotSel.value) return;
        var opt = slotSel.options[slotSel.selectedIndex];
        var jam = opt.getAttribute('data-jam');
        var jenis = opt.getAttribute('data-jenis');
        if (jam && jamSel) {
            // pastikan opsi jam ada
            var found = false;
            for (var i = 0; i < jamSel.options.length; i++) {
                if (jamSel.options[i].value === jam) { found = true; break; }
            }
            if (!found) {
                var o = document.createElement('option');
                o.value = jam; o.textContent = jam;
                jamSel.appendChild(o);
            }
            jamSel.value = jam;
        }
        if (jenis && jenisSel) jenisSel.value = jenis;
    }
    if (tipeRutin) tipeRutin.addEventListener('change', syncTipe);
    if (tipeNon) tipeNon.addEventListener('change', syncTipe);
    if (slotSel) slotSel.addEventListener('change', applySlot);
    syncTipe();

    if (form) {
        form.addEventListener('submit', function (e) {
            var desk = (ta.value || '').trim();
            if (desk.length < 20) {
                e.preventDefault();
                alert('Deskripsi terlalu pendek. Minimal 20 karakter agar Guru BK dapat memahami masalah Anda.');
                return false;
            }
            if (!document.querySelector('input[name="guru_bk"]').value) {
                e.preventDefault();
                alert('Guru BK belum dipilih. Silakan pilih Guru BK terlebih dahulu.');
                window.location = @json(route('siswa.konseling.create'));
                return false;
            }
        });
    }
})();
</script>
@endpush
