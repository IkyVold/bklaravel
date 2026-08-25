@extends('layouts.app')

@section('title', 'Profil Saya')
@section('main_class', '')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/profile.css') }}">
@endpush

@section('content')
@php
    $initial = strtoupper(mb_substr($siswa->nama ?? 'S', 0, 1));
    $fotoUrl = $siswa->foto_profile
        ? (str_starts_with($siswa->foto_profile, 'http') ? $siswa->foto_profile : asset('storage/'.$siswa->foto_profile))
        : null;
    $tglLahir = $siswa->tanggal_lahir
        ? \Carbon\Carbon::parse($siswa->tanggal_lahir)->locale('id')->translatedFormat('d F Y')
        : null;
    $terdaftar = $siswa->created_at
        ? \Carbon\Carbon::parse($siswa->created_at)->locale('id')->translatedFormat('d F Y')
        : '-';
@endphp
<div class="profile-page">
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <div class="logo-icon">🛡️</div>
                <h2>StopBully</h2>
            </div>
            <p>Student Portal</p>
        </div>
        <div class="sidebar-divider"></div>
        <p class="sidebar-section-label">Menu</p>
        <ul class="sidebar-menu">
            <li>
                <a href="{{ route('siswa.profil') }}" class="active">
                    <span class="menu-icon">👤</span>
                    <span>Profile</span>
                </a>
            </li>
            <li>
                <a href="{{ route('siswa.konseling.index') }}">
                    <span class="menu-icon">📋</span>
                    <span>History</span>
                </a>
            </li>
        </ul>
    </aside>

    <main class="main-content">
        <div class="page-header">
            <div class="page-header-left">
                <div class="breadcrumb">
                    <a href="{{ route('siswa.dashboard') }}">Home</a>
                    <span>/</span> Profile
                </div>
                <h1>Profil Saya</h1>
            </div>
        </div>

        @if($mustChangePassword ?? false)
            <div class="alert alert-error" style="margin-bottom:16px">
                🔒 Anda wajib mengganti password default sebelum dapat mengakses halaman lain. Klik "Edit" pada baris Password di bawah, lalu masukkan password Anda saat ini (password default/lama) sebelum menentukan password baru.
            </div>
        @endif

        <div class="profile-grid">
            {{-- Identity card --}}
            <div class="card identity-card">
                <div class="identity-card-banner"></div>
                <div class="identity-card-body">
                    <div class="profile-avatar-wrap">
                        @if($fotoUrl)
                            <img src="{{ $fotoUrl }}" alt="{{ $siswa->nama }}" class="avatar-img" width="80" height="80" style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:3px solid #fff">
                        @else
                            <div class="avatar-placeholder" style="width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,#534AB7,#7F77DD);color:#fff;display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:600;border:3px solid #fff">{{ $initial }}</div>
                        @endif
                        <div class="avatar-edit-overlay" id="avatarEditBtn" role="button" tabindex="0">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                                <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z" />
                                <circle cx="12" cy="13" r="4" />
                            </svg>
                            <span>Ganti Foto</span>
                        </div>
                        <form id="fotoForm" method="POST" action="{{ route('siswa.profil.update') }}" enctype="multipart/form-data" style="display:none">
                            @csrf
                            @method('PUT')
                            <input type="file" name="foto" id="fotoInput" accept="image/jpeg,image/png,image/webp">
                        </form>
                    </div>
                    <div class="identity-name">{{ $siswa->nama }}</div>
                    <div class="identity-role">Siswa</div>
                    <div class="identity-stats">
                        <div class="stat-chip">
                            <div class="stat-chip-value">{{ $siswa->nis ?: '-' }}</div>
                            <div class="stat-chip-label">NIS</div>
                        </div>
                        <div class="stat-chip">
                            <div class="stat-chip-value">{{ $siswa->kelas ?: '-' }}</div>
                            <div class="stat-chip-label">Kelas</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Data diri --}}
            <div class="card info-card">
                <div class="card-title">
                    <div class="card-title-icon">📄</div>
                    Data Diri
                </div>

                <div class="info-list">
                    <div class="info-row">
                        <div class="info-label">Nama Lengkap</div>
                        <div class="info-value highlight">{{ $siswa->nama ?: '-' }}</div>
                        <div></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">NIS</div>
                        <div class="info-value highlight">{{ $siswa->nis ?: '-' }}</div>
                        <div></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Kelas</div>
                        <div class="info-value highlight">{{ $siswa->kelas ?: '-' }}</div>
                        <div>
                            <span style="font-size:11px;color:#a0aec0;font-style:italic">Diatur oleh Guru BK</span>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Jenis Kelamin</div>
                        <div class="info-value {{ $siswa->jenis_kelamin ? '' : 'empty' }}">
                            {{ $siswa->jenis_kelamin ?: 'Belum diisi' }}
                        </div>
                        <div>
                            <button type="button" class="edit-btn" data-field="jenis_kelamin" data-label="Jenis Kelamin" data-value="{{ $siswa->jenis_kelamin }}">Edit</button>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Tanggal Lahir</div>
                        <div class="info-value {{ $tglLahir ? '' : 'empty' }}">
                            {{ $tglLahir ?: 'Belum diisi' }}
                        </div>
                        <div>
                            <button type="button" class="edit-btn" data-field="tanggal_lahir" data-label="Tanggal Lahir" data-value="{{ $siswa->tanggal_lahir?->format('Y-m-d') }}">Edit</button>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Alamat</div>
                        <div class="info-value {{ $siswa->alamat ? '' : 'empty' }}">
                            {{ $siswa->alamat ?: 'Belum diisi' }}
                        </div>
                        <div>
                            <button type="button" class="edit-btn" data-field="alamat" data-label="Alamat" data-value="{{ $siswa->alamat }}">Edit</button>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">No. Telepon</div>
                        <div class="info-value {{ $siswa->no_telepon ? '' : 'empty' }}">
                            {{ $siswa->no_telepon ?: 'Belum diisi' }}
                        </div>
                        <div>
                            <button type="button" class="edit-btn" data-field="no_telepon" data-label="No. Telepon" data-value="{{ $siswa->no_telepon }}">Edit</button>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Password</div>
                        <div class="info-value">••••••••</div>
                        <div>
                            <button type="button" class="edit-btn" data-field="password" data-label="Password" data-value="">Edit</button>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Terdaftar Sejak</div>
                        <div class="info-value">{{ $terdaftar }}</div>
                        <div></div>
                    </div>
                </div>

                <div class="action-bar">
                    <a href="{{ route('siswa.dashboard') }}" class="btn btn-primary">🏠 Kembali ke Beranda</a>
                    <form action="{{ route('logout') }}" method="POST" style="display:inline;margin:0">
                        @csrf
                        <button type="submit" class="btn btn-ghost" onclick="return confirm('Apakah Anda yakin ingin logout?')">🚪 Logout</button>
                    </form>
                </div>
            </div>
        </div>
    </main>

    {{-- Modal edit field --}}
    {{-- PERBAIKAN: sebelumnya modal ini diletakkan SETELAH penutup
         </div> dari .profile-page, sehingga bukan lagi keturunan
         .profile-page. Seluruh CSS modal (display:none, .show,
         positioning, z-index) di-scope sebagai ".profile-page .modal",
         jadi tidak pernah kena ke elemen ini — modal tidak pernah
         ter-hide dan class "show" yang ditoggle lewat JS tidak
         berefek apa pun secara visual. Akibatnya klik tombol "Edit"
         terasa seperti tidak merespons. Modal dipindah ke dalam
         .profile-page supaya CSS scoped-nya berlaku, sama seperti pola
         di halaman guru/kepsek (.guru-bk-page .modal, .kepsek-page .modal). --}}
    <div class="modal" id="editModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Edit</h3>
            <button type="button" class="close-modal" id="modalClose">&times;</button>
        </div>
        <form method="POST" action="{{ route('siswa.profil.update') }}" id="editForm">
            @csrf
            @method('PUT')
            <input type="hidden" name="edit_field" id="editField">
            <div class="modal-body">
                <div class="modal-field" id="fieldJenis" style="display:none">
                    <label>Jenis Kelamin</label>
                    <select name="edit_value" id="inputJenis">
                        <option value="">— Pilih —</option>
                        <option value="Laki-laki">Laki-laki</option>
                        <option value="Perempuan">Perempuan</option>
                    </select>
                </div>
                <div class="modal-field" id="fieldTanggal" style="display:none">
                    <label>Tanggal Lahir</label>
                    <input type="date" name="edit_value" id="inputTanggal">
                </div>
                <div class="modal-field" id="fieldAlamat" style="display:none">
                    <label>Alamat</label>
                    <textarea name="edit_value" id="inputAlamat" rows="3"></textarea>
                </div>
                <div class="modal-field" id="fieldTelepon" style="display:none">
                    <label>No. Telepon</label>
                    <input type="text" name="edit_value" id="inputTelepon" maxlength="30">
                </div>
                <div class="modal-field" id="fieldPassword" style="display:none">
                    {{-- PERBAIKAN (revisi 25 Agustus 2026, poin 13): tambah
                         input password saat ini sebelum siswa bisa
                         mengganti password. Tanpa ini, siapa pun yang
                         berhasil mengambil alih session siswa bisa langsung
                         mengganti password dan mengunci pemilik asli dari
                         akunnya sendiri. --}}
                    <label>Password Saat Ini</label>
                    <input type="password" name="current_password" id="inputCurrentPassword" autocomplete="current-password">
                    <label style="margin-top:10px;display:block">Password Baru</label>
                    <input type="password" name="edit_value" id="inputPassword" minlength="6" autocomplete="new-password">
                    <label style="margin-top:10px;display:block">Konfirmasi Password Baru</label>
                    <input type="password" name="edit_value_confirmation" id="inputPasswordConfirm" minlength="6" autocomplete="new-password">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" id="modalCancel">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    // Foto upload
    var avatarBtn = document.getElementById('avatarEditBtn');
    var fotoInput = document.getElementById('fotoInput');
    var fotoForm = document.getElementById('fotoForm');
    if (avatarBtn && fotoInput) {
        avatarBtn.addEventListener('click', function () { fotoInput.click(); });
        fotoInput.addEventListener('change', function () {
            var file = fotoInput.files && fotoInput.files[0];
            if (!file) return;
            var valid = ['image/jpeg', 'image/png', 'image/webp'];
            if (valid.indexOf(file.type) === -1) {
                alert('Format foto harus JPG, PNG, atau WEBP.');
                fotoInput.value = '';
                return;
            }
            if (file.size > 2 * 1024 * 1024) {
                alert('Ukuran foto maksimal 2MB.');
                fotoInput.value = '';
                return;
            }
            fotoForm.submit();
        });
    }

    // Modal edit
    var modal = document.getElementById('editModal');
    var fields = {
        jenis_kelamin: document.getElementById('fieldJenis'),
        tanggal_lahir: document.getElementById('fieldTanggal'),
        alamat: document.getElementById('fieldAlamat'),
        no_telepon: document.getElementById('fieldTelepon'),
        password: document.getElementById('fieldPassword')
    };
    var inputs = {
        jenis_kelamin: [document.getElementById('inputJenis')],
        tanggal_lahir: [document.getElementById('inputTanggal')],
        alamat: [document.getElementById('inputAlamat')],
        no_telepon: [document.getElementById('inputTelepon')],
        // PERBAIKAN (revisi 25 Agustus 2026, poin 13): inputCurrentPassword
        // ditambahkan di sini juga, supaya ikut logika enable/disable +
        // name yang sama seperti input lain — kalau bukan field password
        // yang aktif, atribut name-nya dilepas agar tidak ikut terkirim.
        password: [document.getElementById('inputCurrentPassword'), document.getElementById('inputPassword'), document.getElementById('inputPasswordConfirm')]
    };
    var inputNames = {
        jenis_kelamin: ['edit_value'],
        tanggal_lahir: ['edit_value'],
        alamat: ['edit_value'],
        no_telepon: ['edit_value'],
        password: ['current_password', 'edit_value', 'edit_value_confirmation']
    };

    function openModal(field, label, value) {
        document.getElementById('modalTitle').textContent = 'Edit ' + label;
        document.getElementById('editField').value = field;
        Object.keys(fields).forEach(function (k) {
            fields[k].style.display = 'none';
            // disable other inputs so only the active field's inputs post
            inputs[k].forEach(function (el) {
                if (!el) return;
                el.disabled = true;
                el.removeAttribute('name');
            });
        });
        fields[field].style.display = 'block';
        var activeInputs = inputs[field];
        var activeNames = inputNames[field];
        activeInputs.forEach(function (el, i) {
            if (!el) return;
            el.disabled = false;
            el.name = activeNames[i];
            el.value = (i === 0) ? (value || '') : '';
        });
        modal.classList.add('show');
    }
    function closeModal() {
        modal.classList.remove('show');
    }

    document.querySelectorAll('.edit-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            openModal(btn.dataset.field, btn.dataset.label, btn.dataset.value);
        });
    });
    document.getElementById('modalClose').addEventListener('click', closeModal);
    document.getElementById('modalCancel').addEventListener('click', closeModal);
    modal.addEventListener('click', function (e) {
        if (e.target === modal) closeModal();
    });

    @if($mustChangePassword ?? false)
        // PERBAIKAN (revisi 25 Agustus 2026, poin 11): buka langsung modal
        // ganti password supaya siswa tidak perlu mencari-cari tombol Edit
        // saat wajib mengganti password default.
        openModal('password', 'Password', '');
    @endif
})();
</script>
@endpush
