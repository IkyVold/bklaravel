<?php
    $authUser = session('auth_user', []);
    $nama = $authUser['nama'] ?? 'Siswa';
    $firstName = explode(' ', trim($nama))[0] ?: 'Siswa';
    $foto = $authUser['foto'] ?? null;
    $initial = strtoupper(mb_substr($firstName, 0, 1));
    $nis = $authUser['nis'] ?? null;
    $notifUnread = 0;
    $notifList = collect();
    try {
        // Skema tunggal: penerima_id (NIS) + penerima_role, sama dengan
        // Api\NotifikasiController — bukan lagi siswa_id/is_read.
        if ($nis) {
            $q = \App\Models\Notifikasi::untukPenerima((string) $nis, 'siswa');
            $notifUnread = (clone $q)->belumDibaca()->count();
            $notifList = (clone $q)->orderByDesc('id')->limit(8)->get();
        }
    } catch (\Throwable $e) {
        // ignore
    }
?>
<nav class="siswa-nav">
    <div class="nav-left">
        <button type="button" class="nav-hamburger" id="navHamburger" aria-label="Menu" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>
        <a href="<?php echo e(route('siswa.dashboard')); ?>" class="logo">
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
        <a href="<?php echo e(route('siswa.dashboard')); ?>" class="<?php echo e(request()->routeIs('siswa.dashboard') ? 'active' : ''); ?>">Beranda</a>
        <a href="<?php echo e(route('siswa.konseling.create')); ?>" class="<?php echo e(request()->routeIs('siswa.konseling.create') ? 'active' : ''); ?>">Konseling</a>
        <a href="<?php echo e(route('siswa.status.index')); ?>" class="<?php echo e(request()->routeIs('siswa.status*') ? 'active' : ''); ?>">Status</a>

        <div class="nav-divider"></div>

        
        <div class="notif-bell-wrap" id="notifWrap">
            <button type="button" class="notif-bell-btn" id="notifBellBtn" aria-label="Notifikasi jadwal konseling">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9" />
                    <path d="M13.73 21a2 2 0 0 1-3.46 0" />
                </svg>
                <?php if($notifUnread > 0): ?>
                    <span class="notif-badge"><?php echo e($notifUnread > 9 ? '9+' : $notifUnread); ?></span>
                <?php endif; ?>
            </button>
            <div class="notif-panel" id="notifDropdown">
                <div class="notif-panel-header">
                    <span>Riwayat Notifikasi</span>
                    <?php if($notifUnread > 0): ?>
                        <form method="POST" action="<?php echo e(route('siswa.notifikasi.readAll')); ?>" style="margin:0">
                            <?php echo csrf_field(); ?>
                            <button type="submit" class="notif-mark-all">Tandai semua dibaca</button>
                        </form>
                    <?php endif; ?>
                </div>
                <div class="notif-list">
                    <?php $__empty_1 = true; $__currentLoopData = $notifList; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $n): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <a href="<?php echo e($n->konseling_id ? route('siswa.status', $n->konseling_id) : route('siswa.konseling.index')); ?>"
                           class="notif-item <?php echo e(!$n->dibaca ? 'unread' : ''); ?>">
                            <span class="notif-item-dot"></span>
                            <div class="notif-item-body">
                                <div class="notif-item-title"><?php echo e($n->judul ?? 'Notifikasi'); ?></div>
                                <div class="notif-item-msg"><?php echo e(\Illuminate\Support\Str::limit($n->pesan ?? '', 90)); ?></div>
                                <div class="notif-item-time"><?php echo e($n->created_at ? \Carbon\Carbon::parse($n->created_at)->diffForHumans() : ''); ?></div>
                            </div>
                        </a>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <div class="notif-empty">Belum ada notifikasi jadwal konseling.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="nav-divider"></div>

        
        <div class="profile-dropdown" id="profileDropdown">
            <button type="button" class="profile-btn" id="profileBtn">
                <?php if($foto): ?>
                    <img src="<?php echo e(str_starts_with($foto, 'http') ? $foto : asset('storage/'.$foto)); ?>" alt="" class="avatar-circle" width="30" height="30" style="border-radius:50%;object-fit:cover">
                <?php else: ?>
                    <span class="avatar-circle" style="width:30px;height:30px;border-radius:50%;background:var(--purple-50,#EEEDFE);color:var(--purple-600,#534AB7);display:inline-flex;align-items:center;justify-content:center;font-size:13px;font-weight:600"><?php echo e($initial); ?></span>
                <?php endif; ?>
                <span class="profile-name"><?php echo e($firstName); ?></span>
                <svg class="chevron" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" width="14" height="14">
                    <path d="M4 6l4 4 4-4" />
                </svg>
            </button>
            <div class="dropdown-content" id="profileMenu">
                <a href="<?php echo e(route('siswa.profil')); ?>">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="8" r="4" />
                        <path d="M4 20c0-4 3.6-7 8-7s8 3 8 7" />
                    </svg>
                    Lihat profil
                </a>
                <form action="<?php echo e(route('logout')); ?>" method="POST" id="logoutFormNav">
                    <?php echo csrf_field(); ?>
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
            <?php if($notifUnread > 0): ?>
                <span class="notif-badge"><?php echo e($notifUnread > 9 ? '9+' : $notifUnread); ?></span>
            <?php endif; ?>
        </button>
        <a href="<?php echo e(route('siswa.profil')); ?>" class="nav-avatar-link" aria-label="Profil">
            <?php if($foto): ?>
                <img src="<?php echo e(str_starts_with($foto, 'http') ? $foto : asset('storage/'.$foto)); ?>" alt="" class="avatar-circle" width="32" height="32" style="border-radius:50%;object-fit:cover">
            <?php else: ?>
                <span class="avatar-circle" style="width:32px;height:32px;border-radius:50%;background:var(--purple-50);color:var(--purple-600);display:inline-flex;align-items:center;justify-content:center;font-weight:600"><?php echo e($initial); ?></span>
            <?php endif; ?>
        </a>
    </div>
</nav>


<div class="nav-drawer-overlay" id="navDrawerOverlay"></div>
<aside class="nav-drawer" id="navDrawer">
    <div class="nav-drawer-header">
        <div class="nav-drawer-user">
            <span class="avatar-circle" style="width:44px;height:44px;border-radius:50%;background:var(--purple-50);color:var(--purple-600);display:inline-flex;align-items:center;justify-content:center;font-weight:600;font-size:18px"><?php echo e($initial); ?></span>
            <div>
                <div class="nav-drawer-name"><?php echo e($nama); ?></div>
                <div class="nav-drawer-role">Siswa</div>
            </div>
        </div>
    </div>
    <nav class="nav-drawer-links">
        <a href="<?php echo e(route('siswa.dashboard')); ?>" class="<?php echo e(request()->routeIs('siswa.dashboard') ? 'active' : ''); ?>"><span class="drawer-icon">🏠</span> Beranda</a>
        <a href="<?php echo e(route('siswa.konseling.create')); ?>" class="<?php echo e(request()->routeIs('siswa.konseling.create') ? 'active' : ''); ?>"><span class="drawer-icon">💬</span> Konseling</a>
        <a href="<?php echo e(route('siswa.status.index')); ?>" class="<?php echo e(request()->routeIs('siswa.status*') ? 'active' : ''); ?>"><span class="drawer-icon">📋</span> Status Pengajuan</a>
        <a href="<?php echo e(route('siswa.profil')); ?>" class="<?php echo e(request()->routeIs('siswa.profil') ? 'active' : ''); ?>"><span class="drawer-icon">👤</span> Profil</a>
        <a href="<?php echo e(route('siswa.konseling.index')); ?>" class="<?php echo e(request()->routeIs('siswa.konseling.index') || request()->routeIs('siswa.konseling.show') ? 'active' : ''); ?>"><span class="drawer-icon">📂</span> History</a>
        <form action="<?php echo e(route('logout')); ?>" method="POST">
            <?php echo csrf_field(); ?>
            <button type="submit" class="nav-drawer-logout" onclick="return confirm('Apakah Anda yakin ingin logout?')">
                <span class="drawer-icon">🚪</span> Keluar
            </button>
        </form>
    </nav>
</aside>

<?php $__env->startPush('styles'); ?>

<?php $__env->stopPush(); ?>
<?php $__env->startPush('scripts'); ?>
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
<?php $__env->stopPush(); ?>
<?php /**PATH C:\Users\asus\Downloads\bk-full-laravel\resources\views/partials/siswa-nav.blade.php ENDPATH**/ ?>