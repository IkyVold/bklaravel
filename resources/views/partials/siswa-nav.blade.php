@php
    $authUser = session('auth_user', []);
    $nama = $authUser['nama'] ?? 'Siswa';
    $firstName = explode(' ', trim($nama))[0] ?: 'Siswa';
    $foto = $authUser['foto'] ?? null;
    $initial = strtoupper(mb_substr($firstName, 0, 1));
    $siswaId = session('auth_id');
    $notifUnread = 0;
    $notifList = collect();
    try {
        if (\Illuminate\Support\Facades\Schema::hasTable('notifikasi')) {
            $q = \App\Models\Notifikasi::query();
            if (\Illuminate\Support\Facades\Schema::hasColumn('notifikasi', 'siswa_id')) {
                $q->where('siswa_id', $siswaId);
            }
            $notifUnread = (clone $q)->where(function ($w) {
                if (\Illuminate\Support\Facades\Schema::hasColumn('notifikasi', 'is_read')) {
                    $w->where('is_read', 0);
                } elseif (\Illuminate\Support\Facades\Schema::hasColumn('notifikasi', 'dibaca')) {
                    $w->where('dibaca', 0);
                }
            })->count();
            $notifList = (clone $q)->orderByDesc('id')->limit(8)->get();
        }
    } catch (\Throwable $e) {
        // ignore
    }
@endphp
<nav class="siswa-nav">
    <div class="nav-left">
        <button type="button" class="nav-hamburger" id="navHamburger" aria-label="Menu" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>
        <a href="{{ route('siswa.dashboard') }}" class="logo">
            <div class="logo-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="#534AB7" stroke-width="2">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                </svg>
            </div>
            <span class="logo-text-full">SMAN Darussholah</span>
            <span class="logo-text-short">SMANDA</span>
        </a>
    </div>

    <div class="nav-links nav-links-desktop">
        <a href="{{ route('siswa.dashboard') }}" class="{{ request()->routeIs('siswa.dashboard') ? 'active' : '' }}">Beranda</a>
        <a href="{{ route('siswa.konseling.create') }}" class="{{ request()->routeIs('siswa.konseling.create') ? 'active' : '' }}">Konseling</a>
        <a href="{{ route('siswa.status.index') }}" class="{{ request()->routeIs('siswa.status*') ? 'active' : '' }}">Status</a>

        <div class="nav-divider"></div>

        {{-- Notification bell (match React NotificationBell) --}}
        <div class="notif-bell-wrap" id="notifWrap">
            <button type="button" class="notif-bell-btn" id="notifBellBtn" aria-label="Notifikasi jadwal konseling">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9" />
                    <path d="M13.73 21a2 2 0 0 1-3.46 0" />
                </svg>
                @if($notifUnread > 0)
                    <span class="notif-badge">{{ $notifUnread > 9 ? '9+' : $notifUnread }}</span>
                @endif
            </button>
            <div class="notif-panel" id="notifDropdown">
                <div class="notif-panel-header">
                    <span>Riwayat Notifikasi</span>
                    @if($notifUnread > 0)
                        <form method="POST" action="{{ route('siswa.notifikasi.readAll') }}" style="margin:0">
                            @csrf
                            <button type="submit" class="notif-mark-all">Tandai semua dibaca</button>
                        </form>
                    @endif
                </div>
                <div class="notif-list">
                    @forelse($notifList as $n)
                        <a href="{{ $n->konseling_id ? route('siswa.status', $n->konseling_id) : route('siswa.konseling.index') }}"
                           class="notif-item {{ empty($n->is_read) ? 'unread' : '' }}">
                            <span class="notif-item-dot"></span>
                            <div class="notif-item-body">
                                <div class="notif-item-title">{{ $n->judul ?? 'Notifikasi' }}</div>
                                <div class="notif-item-msg">{{ \Illuminate\Support\Str::limit($n->pesan ?? '', 90) }}</div>
                                <div class="notif-item-time">{{ $n->created_at ? \Carbon\Carbon::parse($n->created_at)->diffForHumans() : '' }}</div>
                            </div>
                        </a>
                    @empty
                        <div class="notif-empty">Belum ada notifikasi jadwal konseling.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="nav-divider"></div>

        {{-- Profile dropdown: Lihat profil + Keluar (History ada di dalam Profil) --}}
        <div class="profile-dropdown" id="profileDropdown">
            <button type="button" class="profile-btn" id="profileBtn">
                @if($foto)
                    <img src="{{ str_starts_with($foto, 'http') ? $foto : asset('storage/'.$foto) }}" alt="" class="avatar-circle" width="30" height="30" style="border-radius:50%;object-fit:cover">
                @else
                    <span class="avatar-circle" style="width:30px;height:30px;border-radius:50%;background:var(--purple-50,#EEEDFE);color:var(--purple-600,#534AB7);display:inline-flex;align-items:center;justify-content:center;font-size:13px;font-weight:600">{{ $initial }}</span>
                @endif
                <span class="profile-name">{{ $firstName }}</span>
                <svg class="chevron" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" width="14" height="14">
                    <path d="M4 6l4 4 4-4" />
                </svg>
            </button>
            <div class="dropdown-content" id="profileMenu">
                <a href="{{ route('siswa.profil') }}">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="8" r="4" />
                        <path d="M4 20c0-4 3.6-7 8-7s8 3 8 7" />
                    </svg>
                    Lihat profil
                </a>
                <form action="{{ route('logout') }}" method="POST" id="logoutFormNav">
                    @csrf
                    <button type="button" onclick="if(confirm('Apakah Anda yakin ingin logout?')) document.getElementById('logoutFormNav').submit();">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9" />
                        </svg>
                        Keluar
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="nav-right-mobile">
        <button type="button" class="notif-bell-btn" id="notifBellBtnMobile" aria-label="Notifikasi">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
                <path d="M13.73 21a2 2 0 0 1-3.46 0" />
            </svg>
            @if($notifUnread > 0)
                <span class="notif-badge">{{ $notifUnread > 9 ? '9+' : $notifUnread }}</span>
            @endif
        </button>
        <a href="{{ route('siswa.profil') }}" class="nav-avatar-link" aria-label="Profil">
            @if($foto)
                <img src="{{ str_starts_with($foto, 'http') ? $foto : asset('storage/'.$foto) }}" alt="" class="avatar-circle" width="32" height="32" style="border-radius:50%;object-fit:cover">
            @else
                <span class="avatar-circle" style="width:32px;height:32px;border-radius:50%;background:var(--purple-50);color:var(--purple-600);display:inline-flex;align-items:center;justify-content:center;font-weight:600">{{ $initial }}</span>
            @endif
        </a>
    </div>
</nav>

{{-- Mobile drawer --}}
<div class="nav-drawer-overlay" id="navDrawerOverlay"></div>
<aside class="nav-drawer" id="navDrawer">
    <div class="nav-drawer-header">
        <div class="nav-drawer-user">
            <span class="avatar-circle" style="width:44px;height:44px;border-radius:50%;background:var(--purple-50);color:var(--purple-600);display:inline-flex;align-items:center;justify-content:center;font-weight:600;font-size:18px">{{ $initial }}</span>
            <div>
                <div class="nav-drawer-name">{{ $nama }}</div>
                <div class="nav-drawer-role">Siswa</div>
            </div>
        </div>
    </div>
    <nav class="nav-drawer-links">
        <a href="{{ route('siswa.dashboard') }}" class="{{ request()->routeIs('siswa.dashboard') ? 'active' : '' }}"><span class="drawer-icon">🏠</span> Beranda</a>
        <a href="{{ route('siswa.konseling.create') }}" class="{{ request()->routeIs('siswa.konseling.create') ? 'active' : '' }}"><span class="drawer-icon">💬</span> Konseling</a>
        <a href="{{ route('siswa.status.index') }}" class="{{ request()->routeIs('siswa.status*') ? 'active' : '' }}"><span class="drawer-icon">📋</span> Status Pengajuan</a>
        <a href="{{ route('siswa.profil') }}" class="{{ request()->routeIs('siswa.profil') ? 'active' : '' }}"><span class="drawer-icon">👤</span> Profil</a>
        <a href="{{ route('siswa.konseling.index') }}" class="{{ request()->routeIs('siswa.konseling.index') || request()->routeIs('siswa.konseling.show') ? 'active' : '' }}"><span class="drawer-icon">📂</span> History</a>
        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button type="submit" class="nav-drawer-logout" onclick="return confirm('Apakah Anda yakin ingin logout?')">
                <span class="drawer-icon">🚪</span> Keluar
            </button>
        </form>
    </nav>
</aside>

@push('styles')
{{-- notifikasiBell.css dimuat di layout --}}
@endpush
@push('scripts')
<script>
(function () {
  var profileBtn = document.getElementById('profileBtn');
  var profileMenu = document.getElementById('profileMenu');
  var profileDrop = document.getElementById('profileDropdown');
  var notifBtn = document.getElementById('notifBellBtn');
  var notifDrop = document.getElementById('notifDropdown');
  var ham = document.getElementById('navHamburger');
  var drawer = document.getElementById('navDrawer');
  var overlay = document.getElementById('navDrawerOverlay');

  function closeAll() {
    if (profileMenu) profileMenu.classList.remove('show');
    if (notifDrop) notifDrop.classList.remove('show');
  }

  if (profileBtn && profileMenu) {
    profileBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      if (notifDrop) notifDrop.classList.remove('show');
      profileMenu.classList.toggle('show');
    });
  }
  if (notifBtn && notifDrop) {
    notifBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      if (profileMenu) profileMenu.classList.remove('show');
      notifDrop.classList.toggle('show');
    });
  }
  document.addEventListener('click', closeAll);

  function toggleDrawer(open) {
    if (!drawer) return;
    drawer.classList.toggle('open', open);
    if (overlay) overlay.classList.toggle('open', open);
    if (ham) ham.classList.toggle('open', open);
    document.body.style.overflow = open ? 'hidden' : '';
  }
  if (ham) ham.addEventListener('click', function () {
    toggleDrawer(!drawer.classList.contains('open'));
  });
  if (overlay) overlay.addEventListener('click', function () { toggleDrawer(false); });
})();
</script>
@endpush
