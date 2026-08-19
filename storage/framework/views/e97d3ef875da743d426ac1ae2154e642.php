<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title><?php echo $__env->yieldContent('title', 'BK System'); ?> — Bimbingan Konseling</title>
    <link rel="stylesheet" href="<?php echo e(asset('css/theme.css')); ?>">
    <link rel="stylesheet" href="<?php echo e(asset('css/app.css')); ?>">
    <link rel="stylesheet" href="<?php echo e(asset('css/navbar.css')); ?>">
    <link rel="stylesheet" href="<?php echo e(asset('css/siswaNav.css')); ?>">
    <link rel="stylesheet" href="<?php echo e(asset('css/notifikasiBell.css')); ?>">
    <link rel="stylesheet" href="<?php echo e(asset('css/app-extra.css')); ?>">
    <?php echo $__env->yieldPushContent('styles'); ?>
</head>
<body>
<?php
    $role = $authRole ?? session('auth_role');
    $user = $authUser ?? session('auth_user', []);
?>

<?php if($role === 'siswa'): ?>
    
    <?php if (! (request()->routeIs('siswa.konseling.index') || request()->routeIs('siswa.konseling.show'))): ?>
        <?php echo $__env->make('partials.siswa-nav', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php endif; ?>
<?php elseif($role === 'guru'): ?>
    
<?php elseif($role === 'admin'): ?>
    
<?php elseif($role): ?>
<nav class="app-nav">
    <a href="<?php echo e(route($role.'.dashboard')); ?>" class="app-nav-logo">
        <span class="app-nav-logo-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
            </svg>
        </span>
        BK System
    </a>
    <div class="app-nav-links">
        <?php if($role === 'guru'): ?>
            <a href="<?php echo e(route('guru.dashboard')); ?>" class="<?php echo e(request()->routeIs('guru.dashboard') ? 'active' : ''); ?>">Dashboard</a>
            <a href="<?php echo e(route('guru.konseling.index')); ?>" class="<?php echo e(request()->routeIs('guru.konseling.*') ? 'active' : ''); ?>">Konseling</a>
            <a href="<?php echo e(route('guru.siswa.index')); ?>" class="<?php echo e(request()->routeIs('guru.siswa.*') ? 'active' : ''); ?>">Siswa</a>
            <a href="<?php echo e(route('guru.informasi')); ?>" class="<?php echo e(request()->routeIs('guru.informasi*') ? 'active' : ''); ?>">Informasi</a>
        <?php elseif($role === 'kepsek'): ?>
            <a href="<?php echo e(route('kepsek.dashboard')); ?>" class="<?php echo e(request()->routeIs('kepsek.dashboard') ? 'active' : ''); ?>">Dashboard</a>
            <a href="<?php echo e(route('kepsek.konseling')); ?>" class="<?php echo e(request()->routeIs('kepsek.konseling*') ? 'active' : ''); ?>">Konseling</a>
        <?php endif; ?>
        <form action="<?php echo e(route('logout')); ?>" method="POST" style="display:inline;margin-left:8px">
            <?php echo csrf_field(); ?>
            <button type="submit" class="btn" style="background:transparent;color:var(--gray-600);padding:8px 12px;font-size:14px">Keluar</button>
        </form>
    </div>
</nav>
<?php endif; ?>

<main class="<?php echo $__env->yieldContent('main_class', 'main-content'); ?>">
    <?php if(isset($errors) && $errors->any()): ?>
        <div class="alert alert-error" style="max-width:1100px;margin:12px auto;padding:12px 16px">
            <ul style="margin:0;padding-left:18px">
                <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($e); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </ul>
        </div>
    <?php endif; ?>
    <?php if(session('success')): ?>
        <div class="alert alert-success" style="max-width:1100px;margin:12px auto;padding:12px 16px"><?php echo e(session('success')); ?></div>
    <?php endif; ?>
    <?php if(session('error')): ?>
        <div class="alert alert-error" style="max-width:1100px;margin:12px auto;padding:12px 16px"><?php echo e(session('error')); ?></div>
    <?php endif; ?>
    <?php echo $__env->yieldContent('content'); ?>
</main>
<?php echo $__env->yieldPushContent('scripts'); ?>
</body>
</html>
<?php /**PATH C:\Users\asus\Downloads\bk-full-laravel\resources\views/layouts/app.blade.php ENDPATH**/ ?>