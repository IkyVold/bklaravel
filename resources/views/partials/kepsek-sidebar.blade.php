@php $activeTab = $activeTab ?? 'dashboard'; @endphp
<div class="sidebar">
    <ul class="sidebar-menu">
        <li>
            <a href="{{ route('kepsek.dashboard') }}" class="{{ $activeTab === 'dashboard' ? 'active' : '' }}">
                <span class="menu-icon">📊</span>
                <span>Dashboard</span>
            </a>
        </li>
        <li>
            <a href="{{ route('kepsek.rekap') }}" class="{{ $activeTab === 'rekap-guru' ? 'active' : '' }}">
                <span class="menu-icon">👨‍🏫</span>
                <span>Rekap Guru BK</span>
            </a>
        </li>
        <li>
            <a href="{{ route('kepsek.konseling') }}" class="{{ $activeTab === 'semua-konseling' ? 'active' : '' }}">
                <span class="menu-icon">📋</span>
                <span>Semua Konseling</span>
            </a>
        </li>
        <li>
            <a href="{{ route('kepsek.statistik') }}" class="{{ $activeTab === 'statistik' ? 'active' : '' }}">
                <span class="menu-icon">📈</span>
                <span>Statistik Lengkap</span>
            </a>
        </li>
    </ul>
</div>
