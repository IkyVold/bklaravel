<?php $__env->startSection('title', 'Admin'); ?>
<?php $__env->startSection('main_class', ''); ?>
<?php $__env->startPush('styles'); ?>
<link rel="stylesheet" href="<?php echo e(asset('css/admin.css')); ?>">
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
<?php
    $tab = request('tab', 'guru');
    $editGuru = $editGuru ?? null;
    $editKepsek = $editKepsek ?? null;
?>
<div class="admin-page">
    <header class="admin-header">
        <div>
            <h1>⚙️ Admin</h1>
            <p>Kelola akun Guru BK &amp; Kepala Sekolah</p>
        </div>
        <div class="admin-user">
            <span><?php echo e(session('auth_user.nama') ?? session('auth_user.username') ?? 'Admin'); ?></span>
            <form action="<?php echo e(route('logout')); ?>" method="POST" style="display:inline;margin:0">
                <?php echo csrf_field(); ?>
                <button type="submit">Logout</button>
            </form>
        </div>
    </header>

    <div class="admin-tabs">
        <a href="<?php echo e(route('admin.dashboard', ['tab' => 'guru'])); ?>"
           class="<?php echo e($tab === 'guru' ? 'active' : ''); ?>">
            Guru BK (<?php echo e($guruList->count()); ?>)
        </a>
        <a href="<?php echo e(route('admin.dashboard', ['tab' => 'kepsek'])); ?>"
           class="<?php echo e($tab === 'kepsek' ? 'active' : ''); ?>">
            Kepala Sekolah (<?php echo e($kepsekList->count()); ?>)
        </a>
    </div>

    <?php if($tab === 'guru'): ?>
    <div class="admin-panel">
        <form class="admin-form" method="POST"
              action="<?php echo e($editGuru ? route('admin.guru.update', $editGuru->id) : route('admin.guru.store')); ?>">
            <?php echo csrf_field(); ?>
            <?php if($editGuru): ?> <?php echo method_field('PUT'); ?> <?php endif; ?>
            <h3><?php echo e($editGuru ? 'Edit Guru BK' : 'Tambah Guru BK'); ?></h3>
            <div class="admin-form-grid">
                <input name="username" placeholder="Username *" value="<?php echo e(old('username', $editGuru->username ?? '')); ?>" required>
                <input name="password" type="password"
                       placeholder="<?php echo e($editGuru ? 'Password (kosongkan jika tidak diubah)' : 'Password *'); ?>"
                       <?php echo e($editGuru ? '' : 'required'); ?>>
                <input name="nama" placeholder="Nama lengkap *" value="<?php echo e(old('nama', $editGuru->nama ?? '')); ?>" required>
                <input name="spesialisasi" placeholder="Spesialisasi" value="<?php echo e(old('spesialisasi', $editGuru->spesialisasi ?? 'Guru BK')); ?>">
                <input name="npsn" placeholder="NPSN" value="<?php echo e(old('npsn', $editGuru->npsn ?? '')); ?>">
                <input name="alamat" placeholder="Alamat" value="<?php echo e(old('alamat', $editGuru->alamat ?? '')); ?>">
            </div>
            <?php if($editGuru): ?>
            <label style="display:flex;align-items:center;gap:8px;margin-bottom:12px;font-size:14px">
                <input type="checkbox" name="is_active" value="1" <?php if(old('is_active', $editGuru->is_active)): echo 'checked'; endif; ?>>
                Aktif
            </label>
            <?php endif; ?>
            <div class="admin-form-actions">
                <button type="submit"><?php echo e($editGuru ? 'Simpan Perubahan' : 'Tambah Akun'); ?></button>
                <?php if($editGuru): ?>
                <a href="<?php echo e(route('admin.dashboard', ['tab' => 'guru'])); ?>" class="btn-secondary"
                   style="display:inline-flex;align-items:center;text-decoration:none;background:#94a3b8;color:#fff;padding:10px 18px;border-radius:8px;font-weight:600">Batal</a>
                <?php endif; ?>
            </div>
        </form>

        <table class="admin-table">
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>Username</th>
                    <th>Spesialisasi</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $guruList; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $g): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr class="<?php echo e($g->is_active ? '' : 'inactive'); ?>">
                    <td><?php echo e($g->nama); ?></td>
                    <td><code><?php echo e($g->username); ?></code></td>
                    <td><?php echo e($g->spesialisasi ?: '–'); ?></td>
                    <td>
                        <span class="badge <?php echo e($g->is_active ? 'on' : 'off'); ?>">
                            <?php echo e($g->is_active ? 'Aktif' : 'Nonaktif'); ?>

                        </span>
                    </td>
                    <td class="actions">
                        <a href="<?php echo e(route('admin.dashboard', ['tab' => 'guru', 'edit_guru' => $g->id])); ?>">Edit</a>
                        <?php if($g->is_active): ?>
                        <form action="<?php echo e(route('admin.guru.deactivate', $g->id)); ?>" method="POST" style="display:inline"
                              onsubmit="return confirm('Nonaktifkan akun Guru BK &quot;<?php echo e($g->nama); ?>&quot;?\nAkun tidak akan muncul di Pilih Guru siswa.')">
                            <?php echo csrf_field(); ?>
                            <button type="submit" class="danger">Nonaktifkan</button>
                        </form>
                        <?php else: ?>
                        <form action="<?php echo e(route('admin.guru.activate', $g->id)); ?>" method="POST" style="display:inline">
                            <?php echo csrf_field(); ?>
                            <button type="submit">Aktifkan</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr><td colspan="5" style="text-align:center">Belum ada akun Guru BK</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if($tab === 'kepsek'): ?>
    <div class="admin-panel">
        <form class="admin-form" method="POST"
              action="<?php echo e($editKepsek ? route('admin.kepsek.update', $editKepsek->id) : route('admin.kepsek.store')); ?>">
            <?php echo csrf_field(); ?>
            <?php if($editKepsek): ?> <?php echo method_field('PUT'); ?> <?php endif; ?>
            <h3><?php echo e($editKepsek ? 'Edit Kepala Sekolah' : 'Tambah Kepala Sekolah'); ?></h3>
            <div class="admin-form-grid">
                <input name="username" placeholder="Username *" value="<?php echo e(old('username', $editKepsek->username ?? '')); ?>" required>
                <input name="password" type="password"
                       placeholder="<?php echo e($editKepsek ? 'Password (kosongkan jika tidak diubah)' : 'Password *'); ?>"
                       <?php echo e($editKepsek ? '' : 'required'); ?>>
                <input name="nama" placeholder="Nama lengkap *" value="<?php echo e(old('nama', $editKepsek->nama ?? '')); ?>" required>
                <input name="npsn" placeholder="NPSN" value="<?php echo e(old('npsn', $editKepsek->npsn ?? '')); ?>">
            </div>
            <?php if($editKepsek): ?>
            <label style="display:flex;align-items:center;gap:8px;margin-bottom:12px;font-size:14px">
                <input type="checkbox" name="is_active" value="1" <?php if(old('is_active', $editKepsek->is_active)): echo 'checked'; endif; ?>>
                Aktif
            </label>
            <?php endif; ?>
            <div class="admin-form-actions">
                <button type="submit"><?php echo e($editKepsek ? 'Simpan Perubahan' : 'Tambah Akun'); ?></button>
                <?php if($editKepsek): ?>
                <a href="<?php echo e(route('admin.dashboard', ['tab' => 'kepsek'])); ?>" class="btn-secondary"
                   style="display:inline-flex;align-items:center;text-decoration:none;background:#94a3b8;color:#fff;padding:10px 18px;border-radius:8px;font-weight:600">Batal</a>
                <?php endif; ?>
            </div>
        </form>

        <table class="admin-table">
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>Username</th>
                    <th>NPSN</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $kepsekList; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr class="<?php echo e($k->is_active ? '' : 'inactive'); ?>">
                    <td><?php echo e($k->nama); ?></td>
                    <td><code><?php echo e($k->username); ?></code></td>
                    <td><?php echo e($k->npsn ?: '–'); ?></td>
                    <td>
                        <span class="badge <?php echo e($k->is_active ? 'on' : 'off'); ?>">
                            <?php echo e($k->is_active ? 'Aktif' : 'Nonaktif'); ?>

                        </span>
                    </td>
                    <td class="actions">
                        <a href="<?php echo e(route('admin.dashboard', ['tab' => 'kepsek', 'edit_kepsek' => $k->id])); ?>">Edit</a>
                        <?php if($k->is_active): ?>
                        <form action="<?php echo e(route('admin.kepsek.deactivate', $k->id)); ?>" method="POST" style="display:inline"
                              onsubmit="return confirm('Nonaktifkan akun Kepala Sekolah &quot;<?php echo e($k->nama); ?>&quot;?')">
                            <?php echo csrf_field(); ?>
                            <button type="submit" class="danger">Nonaktifkan</button>
                        </form>
                        <?php else: ?>
                        <form action="<?php echo e(route('admin.kepsek.activate', $k->id)); ?>" method="POST" style="display:inline">
                            <?php echo csrf_field(); ?>
                            <button type="submit">Aktifkan</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr><td colspan="5" style="text-align:center">Belum ada akun Kepala Sekolah</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\asus\Downloads\bk-full-laravel\resources\views/admin/dashboard.blade.php ENDPATH**/ ?>