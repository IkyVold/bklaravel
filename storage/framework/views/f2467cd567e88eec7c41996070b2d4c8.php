<?php
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
?>
<div class="sidebar">
    <ul class="sidebar-menu">
        <?php $__currentLoopData = $filters; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <li>
                <a href="<?php echo e(route('guru.konseling.index', ['filter' => $key])); ?>"
                   class="<?php echo e($activeTab === 'konseling' && $currentFilter === $key ? 'active' : ''); ?>">
                    <span class="menu-icon"><?php echo e($item['icon']); ?></span>
                    <span><?php echo e($item['label']); ?></span>
                    <?php if(in_array($key, ['all','proses'], true) && $prosesCount > 0): ?>
                        <span class="notification-badge"><?php echo e($prosesCount); ?></span>
                    <?php endif; ?>
                </a>
            </li>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        <li style="margin-top:12px;border-top:1px solid var(--border,#e8e6e0);padding-top:12px">
            <a href="<?php echo e(route('guru.siswa.index')); ?>" class="<?php echo e($activeTab === 'siswa' ? 'active' : ''); ?>">
                <span class="menu-icon">👥</span>
                <span>Daftar Siswa</span>
            </a>
        </li>
        <?php if(\Illuminate\Support\Facades\Route::has('guru.jadwal-rutin.index')): ?>
        <li>
            <a href="<?php echo e(route('guru.jadwal-rutin.index')); ?>" class="<?php echo e(($activeTab ?? '') === 'jadwal-rutin' ? 'active' : ''); ?>">
                <span class="menu-icon">📅</span>
                <span>Jadwal Rutin</span>
            </a>
        </li>
        <?php endif; ?>
        <li>
            <a href="<?php echo e(route('guru.informasi')); ?>" class="<?php echo e($activeTab === 'informasi' ? 'active' : ''); ?>">
                <span class="menu-icon">💡</span>
                <span>Informasi / FAQ</span>
            </a>
        </li>
    </ul>
</div>
<?php /**PATH C:\Users\asus\Downloads\bk-full-laravel\resources\views/partials/guru-sidebar.blade.php ENDPATH**/ ?>