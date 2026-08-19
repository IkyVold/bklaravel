<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pilih Login — BK System</title>
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
    <style>
        .role-pick-grid { display: grid; gap: 12px; margin-top: 8px; text-align: left; }
        .role-pick-item {
            display: flex; align-items: center; gap: 14px;
            padding: 14px 16px; border-radius: 12px;
            border: 1.5px solid #e8e6df; text-decoration: none; color: inherit;
            transition: border-color .15s, box-shadow .15s;
        }
        .role-pick-item:hover {
            border-color: #7F77DD;
            box-shadow: 0 4px 14px rgba(83, 74, 183, 0.12);
        }
        .role-pick-item strong { display: block; font-size: 15px; color: #3C3489; }
        .role-pick-item span { font-size: 12.5px; color: #888780; }
    </style>
</head>
<body>
<div class="auth-page theme-siswa">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-logo">
                <img src="{{ asset('logo-smanda.png') }}" alt="Logo SMAN Darussholah Singojuruh">
            </div>
            <p class="auth-school">SMAN Darussholah Singojuruh</p>
            <h1 class="auth-title">Sistem Bimbingan Konseling</h1>
            <p class="auth-subtitle">Pilih peran untuk masuk</p>

            @if(session('success'))
                <div style="background:#E1F5EE;color:#0F6E56;padding:10px 12px;border-radius:8px;font-size:13px;margin-bottom:12px">
                    {{ session('success') }}
                </div>
            @endif

            <div class="role-pick-grid">
                <a href="{{ route('login.role', 'siswa') }}" class="role-pick-item">
                    <strong>Siswa</strong>
                    <span style="margin-left:auto">NIS + Password</span>
                </a>
                <a href="{{ route('login.role', 'guru') }}" class="role-pick-item">
                    <strong>Guru BK</strong>
                    <span style="margin-left:auto">Username + Password</span>
                </a>
                <a href="{{ route('login.role', 'kepsek') }}" class="role-pick-item">
                    <strong>Kepala Sekolah</strong>
                    <span style="margin-left:auto">Username + Password</span>
                </a>
                <a href="{{ route('login.role', 'admin') }}" class="role-pick-item">
                    <strong>Admin</strong>
                    <span style="margin-left:auto">Username + Password</span>
                </a>
            </div>
        </div>
    </div>
</div>
</body>
</html>
