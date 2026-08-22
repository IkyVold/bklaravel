<?php $__env->startSection('title', $row ? 'Edit Informasi' : 'Tambah Informasi'); ?>
<?php $__env->startSection('main_class', ''); ?>
<?php $__env->startPush('styles'); ?>
<link rel="stylesheet" href="<?php echo e(asset('css/guruBk.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('css/guru-shell.css')); ?>">
<?php $__env->stopPush(); ?>
<?php $__env->startSection('content'); ?>
<?php
    $activeTab = 'informasi'; $currentFilter = 'all'; $prosesCount = 0;
    $kategoriList = ['Beasiswa','Pendaftaran Perguruan Tinggi','Bimbingan Karir','Informasi Sekolah','Informasi BK','Umum'];
?>
<div class="guru-bk-page">
    <?php echo $__env->make('partials.guru-header', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <div class="container">
        <?php echo $__env->make('partials.guru-sidebar', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <div class="guru-main">
            <div class="content-header">
                <h2><?php echo e($row ? '✏️ Edit Informasi' : '➕ Tambah Informasi'); ?></h2>
                <p><a href="<?php echo e(route('guru.informasi')); ?>">← Kembali</a></p>
            </div>
            <div class="panel" style="max-width:560px;padding:24px">
                <form method="POST" action="<?php echo e($row ? route('guru.informasi.update', $row->id) : route('guru.informasi.store')); ?>">
                    <?php echo csrf_field(); ?>
                    <?php if($row): ?> <?php echo method_field('PUT'); ?> <?php endif; ?>
                    <div class="form-group">
                        <label>Judul</label>
                        <input type="text" name="judul" class="form-control" value="<?php echo e(old('judul', $row->judul ?? '')); ?>" required placeholder="Contoh: Beasiswa Bidikmisi 2026">
                    </div>
                    <div class="form-group">
                        <label>Kategori</label>
                        <select name="kategori" class="form-control" required>
                            <?php $__currentLoopData = $kategoriList; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($k); ?>" <?php if(old('kategori', $row->kategori ?? 'FAQ')===$k): echo 'selected'; endif; ?>><?php echo e($k); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Isi Informasi</label>
                        <textarea name="isi" class="form-control" rows="8" required placeholder="Tulis detail lengkap — syarat, jadwal, kuota, link, dsb. Chatbot FAQ memakai isi ini."><?php echo e(old('isi', $row->isi ?? '')); ?></textarea>
                    </div>
                    <button type="submit" class="btn-cetak btn-cetak-green">💾 Simpan Informasi</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\asus\Downloads\bk-full-laravel\resources\views/guru/informasi-form.blade.php ENDPATH**/ ?>