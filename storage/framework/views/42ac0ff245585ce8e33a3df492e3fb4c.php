<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>
        <?php if($role === 'siswa'): ?> Login Siswa
        <?php elseif($role === 'guru'): ?> Login Guru BK
        <?php elseif($role === 'kepsek'): ?> Login Kepala Sekolah
        <?php else: ?> Login Admin
        <?php endif; ?>
        — BK System
    </title>
    <link rel="stylesheet" href="<?php echo e(asset('css/auth.css')); ?>">
</head>
<body>
<?php
    $theme = match($role) {
        'siswa' => 'siswa',
        'guru' => 'guru',
        'kepsek' => 'kepsek',
        default => 'admin',
    };
    $title = match($role) {
        'siswa' => 'Login Siswa',
        'guru' => 'Login Guru BK',
        'kepsek' => 'Login Kepala Sekolah',
        default => 'Login Admin',
    };
    $subtitle = match($role) {
        'siswa' => 'Masukkan NIS dan Password untuk<br>mengakses layanan konseling',
        'guru' => 'Masukkan Username dan Password untuk<br>mengakses layanan konseling',
        'kepsek' => 'Masukkan Username dan Password untuk<br>mengakses dashboard monitoring',
        default => 'Masukkan Username dan Password admin',
    };
?>

<div class="auth-page theme-<?php echo e($theme); ?>">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-logo">
                <img src="<?php echo e(asset('logo-smanda.png')); ?>" alt="Logo SMAN Darussholah Singojuruh">
            </div>
            <p class="auth-school">SMAN Darussholah Singojuruh</p>

            <h1 class="auth-title"><?php echo e($title); ?></h1>
            <p class="auth-subtitle"><?php echo $subtitle; ?></p>

            <?php if($errors->any()): ?>
                <div class="auth-field-error" style="margin-bottom:14px;text-align:left">
                    <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div><?php echo e($e); ?></div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            <?php endif; ?>

            <form class="auth-form" method="POST" action="<?php echo e(route('login.submit')); ?>" novalidate>
                <?php echo csrf_field(); ?>
                <input type="hidden" name="role" value="<?php echo e($role); ?>">

                <?php if($role === 'siswa'): ?>
                    <div class="auth-field">
                        <label for="nis">NIS</label>
                        <input
                            type="text"
                            id="nis"
                            name="nis"
                            inputmode="numeric"
                            maxlength="10"
                            placeholder="Masukan NIS (4 digit angka)"
                            value="<?php echo e(old('nis')); ?>"
                            autocomplete="username"
                            class="<?php echo e($errors->has('nis') || $errors->has('login') ? 'has-error' : ''); ?>"
                            required
                        >
                        <div class="auth-field-hint">NIS harus 4 digit angka (contoh: 1234)</div>
                    </div>
                <?php else: ?>
                    <div class="auth-field">
                        <label for="username">Username</label>
                        <input
                            type="text"
                            id="username"
                            name="username"
                            placeholder="<?php echo e($role === 'guru' ? 'Contoh: joko_bk' : 'Username'); ?>"
                            value="<?php echo e(old('username')); ?>"
                            autocomplete="username"
                            class="<?php echo e($errors->has('username') || $errors->has('login') ? 'has-error' : ''); ?>"
                            required
                        >
                    </div>
                <?php endif; ?>

                <div class="auth-field">
                    <label for="password">Password</label>
                    <div class="auth-password-wrap">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Masukan password"
                            autocomplete="current-password"
                            required
                        >
                        <button
                            type="button"
                            class="auth-password-toggle"
                            id="togglePassword"
                            aria-label="Tampilkan password"
                        >
                            <svg id="eyeOpen" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;width:20px;height:20px">
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24" />
                                <line x1="1" y1="1" x2="23" y2="23" />
                            </svg>
                            <svg id="eyeClosed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:20px;height:20px">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                <circle cx="12" cy="12" r="3" />
                            </svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="auth-btn">Login</button>
            </form>

            <div class="auth-switch">
                <div class="auth-switch-label">Login sebagai</div>
                <div class="auth-switch-links">
                    <?php if($role !== 'siswa'): ?>
                        <a href="<?php echo e(route('login.role', 'siswa')); ?>" class="auth-switch-link">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                                <circle cx="12" cy="7" r="4" />
                            </svg>
                            Siswa
                        </a>
                    <?php endif; ?>
                    <?php if($role !== 'guru'): ?>
                        <a href="<?php echo e(route('login.role', 'guru')); ?>" class="auth-switch-link">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                                <circle cx="12" cy="7" r="4" />
                            </svg>
                            Guru BK
                        </a>
                    <?php endif; ?>
                    <?php if($role !== 'kepsek'): ?>
                        <a href="<?php echo e(route('login.role', 'kepsek')); ?>" class="auth-switch-link">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                                <circle cx="12" cy="7" r="4" />
                            </svg>
                            Kepala Sekolah
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('togglePassword')?.addEventListener('click', function () {
    const input = document.getElementById('password');
    const open = document.getElementById('eyeOpen');
    const closed = document.getElementById('eyeClosed');
    if (input.type === 'password') {
        input.type = 'text';
        open.style.display = 'block';
        closed.style.display = 'none';
        this.setAttribute('aria-label', 'Sembunyikan password');
    } else {
        input.type = 'password';
        open.style.display = 'none';
        closed.style.display = 'block';
        this.setAttribute('aria-label', 'Tampilkan password');
    }
});
</script>
</body>
</html>
<?php /**PATH C:\Users\asus\Downloads\bk-full-laravel\resources\views/auth/login-role.blade.php ENDPATH**/ ?>