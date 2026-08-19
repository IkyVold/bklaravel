@php
    $user = session('auth_user', []);
    $nama = $user['nama'] ?? 'Kepala Sekolah';
    $sekolah = $user['sekolah'] ?? ($user['npsn'] ? 'NPSN '.$user['npsn'] : 'Sekolah');
    $initials = collect(explode(' ', $nama))->map(fn($w) => mb_substr($w, 0, 1))->take(2)->implode('');
    $initials = strtoupper($initials ?: 'KS');
@endphp
<div class="header">
    <div class="logo-section">
        <div class="logo">🏫</div>
        <div class="header-info">
            <h1>Dashboard Kepala Sekolah</h1>
            <p>Monitoring &amp; Evaluasi Layanan BK - Stop Bullying</p>
        </div>
    </div>
    <div class="user-info">
        <div class="user-avatar">{{ $initials }}</div>
        <div>
            <div style="font-weight:700;font-size:16px">{{ $nama }}</div>
            <div style="font-size:12px;opacity:.9">{{ $sekolah }}</div>
        </div>
        <form action="{{ route('logout') }}" method="POST" style="margin:0">
            @csrf
            <button type="submit" class="logout-btn" onclick="return confirm('Logout?')">🚪 Logout</button>
        </form>
    </div>
</div>
