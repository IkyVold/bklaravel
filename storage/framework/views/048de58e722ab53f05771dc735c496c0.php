<?php $__env->startSection('title', 'Pilih Guru BK'); ?>
<?php $__env->startSection('main_class', ''); ?>
<?php $__env->startPush('styles'); ?>
<link rel="stylesheet" href="<?php echo e(asset('css/pilihGuru.css')); ?>">
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
<div class="pilih-page">
    <div class="page-header">
        <div class="breadcrumb">
            <a href="<?php echo e(route('siswa.dashboard')); ?>">Beranda</a>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 18l6-6-6-6" />
            </svg>
            <span>Konseling</span>
        </div>
        <h1>Pilih Guru BK</h1>
        <p>
            Pilih guru bimbingan konseling yang ingin Anda hubungi. Laporan akan langsung diteruskan
            ke guru yang dipilih.
        </p>
    </div>

    <div class="info-banner">
        <div class="info-inner">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10" />
                <path d="M12 16v-4M12 8h.01" />
            </svg>
            
            Isi konsultasi Anda (deskripsi masalah, kesimpulan, dan rekomendasi) bersifat rahasia dan
            hanya dapat dilihat oleh Anda dan guru BK yang Anda pilih. Kepala Sekolah dapat melihat
            data administratif (jadwal dan status) untuk keperluan monitoring, tanpa isi konsultasi.
        </div>
    </div>

    <?php if($guruList->isEmpty()): ?>
        <p style="text-align:center;padding:2rem;color:#666">
            Belum ada Guru BK yang tersedia. Hubungi Admin.
        </p>
    <?php else: ?>
        <div class="cards-wrapper">
            <?php $__currentLoopData = $guruList; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $g): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="counselor-card">
                    <?php if(!empty($g->foto_profile)): ?>
                        <img
                            src="<?php echo e(asset('storage/'.$g->foto_profile)); ?>"
                            alt="<?php echo e($g->nama); ?>"
                            class="counselor-avatar"
                            onerror="this.style.display='none';this.nextElementSibling.style.display='flex'"
                        >
                        <div class="counselor-avatar counselor-avatar-initials" style="display:none" aria-hidden>
                            <?php echo e(strtoupper(\Illuminate\Support\Str::substr($g->nama ?? '?', 0, 2))); ?>

                        </div>
                    <?php else: ?>
                        <div class="counselor-avatar counselor-avatar-initials" aria-hidden>
                            <?php echo e(strtoupper(\Illuminate\Support\Str::substr($g->nama ?? '?', 0, 2))); ?>

                        </div>
                    <?php endif; ?>
                    <div class="counselor-info">
                        <div class="counselor-name"><?php echo e($g->nama); ?></div>
                        <div class="counselor-meta">
                            <span class="role-badge">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                                    <circle cx="9" cy="7" r="4" />
                                </svg>
                                <?php echo e($g->spesialisasi ?: 'Guru BK'); ?>

                            </span>
                            <?php if(!empty($g->npsn)): ?>
                                <span class="meta-pill">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                        <path d="M14 2v6h6" />
                                    </svg>
                                    NPSN <?php echo e($g->npsn); ?>

                                </span>
                            <?php endif; ?>
                            <?php if(!empty($g->alamat)): ?>
                                <span class="meta-pill">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
                                        <circle cx="12" cy="10" r="3" />
                                    </svg>
                                    <?php echo e($g->alamat); ?>

                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <a
                        href="<?php echo e(route('siswa.konseling.create', ['guru_id' => $g->id, 'guru' => $g->nama])); ?>"
                        class="pilih-button"
                        style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center"
                    >
                        Pilih Guru
                    </a>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    <?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\asus\Downloads\bk-full-laravel\resources\views/siswa/konseling-pilih.blade.php ENDPATH**/ ?>