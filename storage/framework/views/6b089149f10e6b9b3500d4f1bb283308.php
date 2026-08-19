<?php
    $user = session('auth_user', []);
    $namaGuru = $user['nama'] ?? 'Guru BK';
    $username = $user['username'] ?? '';
    $initials = collect(explode(' ', $namaGuru))->map(fn($w) => mb_substr($w, 0, 1))->take(2)->implode('');
    $initials = strtoupper($initials ?: 'GB');

    $guruNotifs = collect();
    $guruUnread = 0;
    try {
        \App\Http\Controllers\Web\NotifikasiWebController::ensureTable();
        if ($username) {
            $guruNotifs = \App\Models\NotifikasiGuru::where('guru_username', $username)
                ->orderByDesc('created_at')->limit(20)->get();
            $guruUnread = \App\Models\NotifikasiGuru::where('guru_username', $username)->where('is_read', 0)->count();
        }
    } catch (\Throwable $e) {}
?>
<div class="header">
    <div class="logo-section">
        <div class="logo">📚</div>
        <div class="header-info">
            <h1>Dashboard Guru BK</h1>
            <p>Stop Bullying - Monitoring &amp; Konfirmasi Konseling Siswa</p>
        </div>
    </div>
    <div class="user-info" style="position:relative">
        <div style="position:relative;margin-right:6px">
            <button type="button" id="guruNotifBtn" title="Notifikasi" style="background:rgba(255,255,255,0.15);border:none;color:#fff;border-radius:20px;padding:6px 12px;font-size:13px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:6px">
                <span>🔔</span>
                <?php if($guruUnread > 0): ?>
                    <span style="background:#E24B4A;color:#fff;border-radius:10px;min-width:18px;height:18px;font-size:11px;line-height:18px;text-align:center;padding:0 5px"><?php echo e($guruUnread); ?></span>
                <?php else: ?>
                    <span style="opacity:.85;font-size:11px">Notif</span>
                <?php endif; ?>
            </button>
            <div id="guruNotifPanel" style="display:none;position:absolute;top:calc(100% + 8px);right:0;width:320px;max-height:360px;overflow-y:auto;background:#fff;color:#1a1a18;border-radius:12px;box-shadow:0 12px 40px rgba(0,0,0,.2);z-index:2500;border:1px solid #e8e6dc">
                <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 14px;border-bottom:1px solid #eee">
                    <strong style="font-size:14px">Notifikasi</strong>
                    <?php if($guruUnread > 0): ?>
                    <form method="POST" action="<?php echo e(route('guru.notifikasi.readAll')); ?>" style="margin:0">
                        <?php echo csrf_field(); ?>
                        <button type="submit" style="border:none;background:none;color:#534AB7;font-size:12px;cursor:pointer;font-weight:600">Tandai semua dibaca</button>
                    </form>
                    <?php endif; ?>
                </div>
                <?php $__empty_1 = true; $__currentLoopData = $guruNotifs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $n): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <a href="<?php echo e($n->konseling_id ? route('guru.notifikasi.read', $n->id) : '#'); ?>"
                       style="display:block;padding:12px 14px;border-bottom:1px solid #f5f5f0;text-decoration:none;color:inherit;<?php echo e($n->is_read ? 'opacity:.7' : 'background:#f8f7ff'); ?>">
                        <div style="font-weight:700;font-size:13px;margin-bottom:4px"><?php echo e($n->judul); ?></div>
                        <div style="font-size:12.5px;line-height:1.4;color:#555"><?php echo e($n->pesan); ?></div>
                        <div style="font-size:11px;color:#999;margin-top:4px"><?php echo e($n->created_at ? \Carbon\Carbon::parse($n->created_at)->diffForHumans() : ''); ?></div>
                    </a>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <div style="padding:24px;text-align:center;color:#888;font-size:13px">Belum ada notifikasi</div>
                <?php endif; ?>
            </div>
        </div>
        <div class="user-avatar"><?php echo e($initials); ?></div>
        <div class="user-details">
            <div class="user-name"><?php echo e($namaGuru); ?></div>
            <div class="user-role">Konselor Sekolah</div>
        </div>
        <form action="<?php echo e(route('logout')); ?>" method="POST" style="margin:0">
            <?php echo csrf_field(); ?>
            <button type="submit" class="logout-btn" onclick="return confirm('Logout?')">Logout</button>
        </form>
    </div>
</div>
<script>
(function(){
  var btn = document.getElementById('guruNotifBtn');
  var panel = document.getElementById('guruNotifPanel');
  if (!btn || !panel) return;
  btn.addEventListener('click', function(e){
    e.stopPropagation();
    panel.style.display = panel.style.display === 'none' || !panel.style.display ? 'block' : 'none';
  });
  document.addEventListener('click', function(){ panel.style.display = 'none'; });
  panel.addEventListener('click', function(e){ e.stopPropagation(); });
})();
</script>
<?php /**PATH C:\Users\asus\Downloads\bk-full-laravel\resources\views/partials/guru-header.blade.php ENDPATH**/ ?>