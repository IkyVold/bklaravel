<?php $__env->startSection('title', 'Rekap Guru BK — Kepsek'); ?>
<?php $__env->startSection('main_class', ''); ?>
<?php $__env->startPush('styles'); ?>
<link rel="stylesheet" href="<?php echo e(asset('css/kepsekDashboard.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('css/kepsek-shell.css')); ?>">
<?php $__env->stopPush(); ?>
<?php $__env->startSection('content'); ?>
<div class="kepsek-page">
    <?php echo $__env->make('partials.kepsek-header', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <div class="container">
        <?php echo $__env->make('partials.kepsek-sidebar', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <div class="main-content">
            <div class="content-header">
                <h2>👨‍🏫 Rekap Guru BK</h2>
                <p>Ringkasan beban dan hasil layanan per Guru BK</p>
            </div>
            <div class="panel">
                <div style="overflow-x:auto">
                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Guru BK</th>
                                <th>Total</th>
                                <th>Akademik</th>
                                <th>Sosial</th>
                                <th>Pribadi</th>
                                <th>Bullying</th>
                                <th>Proses</th>
                                <th>Selesai</th>
                                <th>Dibatalkan</th>
                                <th>Laporan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $__empty_1 = true; $__currentLoopData = $rekap; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <tr>
                                <td><?php echo e($i + 1); ?></td>
                                <td><strong><?php echo e($item['guru']->nama); ?></strong></td>
                                <td><strong><?php echo e($item['total']); ?></strong></td>
                                <td><?php echo e($item['akademik']); ?></td>
                                <td><?php echo e($item['sosial']); ?></td>
                                <td><?php echo e($item['pribadi']); ?></td>
                                <td><?php echo e($item['bullying']); ?></td>
                                <td><span class="status-badge status-proses"><?php echo e($item['proses']); ?></span></td>
                                <td><span class="status-badge status-selesai"><?php echo e($item['selesai']); ?></span></td>
                                <td><span class="status-badge status-dibatalkan"><?php echo e($item['dibatalkan']); ?></span></td>
                                <td><span class="status-badge status-belum"><?php echo e($item['laporan']); ?></span></td>
                            </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr><td colspan="11" style="text-align:center;padding:40px;color:#718096">Belum ada data Guru BK</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\asus\Downloads\bk-full-laravel\resources\views/kepsek/rekap-guru.blade.php ENDPATH**/ ?>