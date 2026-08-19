<?php $activeTab = $activeTab ?? 'dashboard'; ?>
<div class="sidebar">
    <ul class="sidebar-menu">
        <li>
            <a href="<?php echo e(route('kepsek.dashboard')); ?>" class="<?php echo e($activeTab === 'dashboard' ? 'active' : ''); ?>">
                <span class="menu-icon">📊</span>
                <span>Dashboard</span>
            </a>
        </li>
        <li>
            <a href="<?php echo e(route('kepsek.rekap')); ?>" class="<?php echo e($activeTab === 'rekap-guru' ? 'active' : ''); ?>">
                <span class="menu-icon">👨‍🏫</span>
                <span>Rekap Guru BK</span>
            </a>
        </li>
        <li>
            <a href="<?php echo e(route('kepsek.konseling')); ?>" class="<?php echo e($activeTab === 'semua-konseling' ? 'active' : ''); ?>">
                <span class="menu-icon">📋</span>
                <span>Semua Konseling</span>
            </a>
        </li>
        <li>
            <a href="<?php echo e(route('kepsek.statistik')); ?>" class="<?php echo e($activeTab === 'statistik' ? 'active' : ''); ?>">
                <span class="menu-icon">📈</span>
                <span>Statistik Lengkap</span>
            </a>
        </li>
    </ul>
</div>
<?php /**PATH C:\Users\asus\Downloads\bk-full-laravel\resources\views/partials/kepsek-sidebar.blade.php ENDPATH**/ ?>