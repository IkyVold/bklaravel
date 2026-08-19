@php
    $activeTab = $activeTab ?? 'konseling';
    $currentFilter = $currentFilter ?? 'all';
    $prosesCount = $prosesCount ?? 0;
    $filters = [
        'all' => ['icon' => '📊', 'label' => 'Semua Konseling'],
        'proses' => ['icon' => '⏳', 'label' => 'Menunggu Konfirmasi'],
        'terkonfirmasi' => ['icon' => '✅', 'label' => 'Sudah Dikonfirmasi'],
        'selesai' => ['icon' => '✨', 'label' => 'Selesai'],
        'dibatalkan' => ['icon' => '❌', 'label' => 'Dibatalkan'],
    ];
@endphp
<div class="sidebar">
    <ul class="sidebar-menu">
        @foreach($filters as $key => $item)
            <li>
                <a href="{{ route('guru.konseling.index', ['filter' => $key]) }}"
                   class="{{ $activeTab === 'konseling' && $currentFilter === $key ? 'active' : '' }}">
                    <span class="menu-icon">{{ $item['icon'] }}</span>
                    <span>{{ $item['label'] }}</span>
                    @if(in_array($key, ['all','proses'], true) && $prosesCount > 0)
                        <span class="notification-badge">{{ $prosesCount }}</span>
                    @endif
                </a>
            </li>
        @endforeach
        <li style="margin-top:12px;border-top:1px solid var(--border,#e8e6e0);padding-top:12px">
            <a href="{{ route('guru.siswa.index') }}" class="{{ $activeTab === 'siswa' ? 'active' : '' }}">
                <span class="menu-icon">👥</span>
                <span>Daftar Siswa</span>
            </a>
        </li>
        @if(\Illuminate\Support\Facades\Route::has('guru.jadwal-rutin.index'))
        <li>
            <a href="{{ route('guru.jadwal-rutin.index') }}" class="{{ ($activeTab ?? '') === 'jadwal-rutin' ? 'active' : '' }}">
                <span class="menu-icon">📅</span>
                <span>Jadwal Rutin</span>
            </a>
        </li>
        @endif
        <li>
            <a href="{{ route('guru.informasi') }}" class="{{ $activeTab === 'informasi' ? 'active' : '' }}">
                <span class="menu-icon">💡</span>
                <span>Informasi / FAQ</span>
            </a>
        </li>
    </ul>
</div>
