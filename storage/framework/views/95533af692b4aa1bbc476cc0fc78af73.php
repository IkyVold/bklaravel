<?php $__env->startSection('title', 'Semua Konseling — Kepsek'); ?>
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
                <h2>📋 Semua Konseling</h2>
                <p>Data seluruh pengajuan konseling di sekolah (read-only)</p>
            </div>
            <div class="panel">
                <form method="GET" class="filters">
                    <select name="filter">
                        <option value="all" <?php if(($filter??'all')==='all'): echo 'selected'; endif; ?>>Semua status</option>
                        <option value="proses" <?php if(($filter??'')==='proses'): echo 'selected'; endif; ?>>Proses</option>
                        <option value="selesai" <?php if(($filter??'')==='selesai'): echo 'selected'; endif; ?>>Selesai</option>
                        <option value="dibatalkan" <?php if(($filter??'')==='dibatalkan'): echo 'selected'; endif; ?>>Dibatalkan</option>
                    </select>
                    <select name="kategori">
                        <option value="">Semua kategori</option>
                        <?php $__currentLoopData = ['Akademik','Sosial','Pribadi','Karir','Bullying','Keluarga','Lainnya']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($k); ?>" <?php if(($kategori??'')===$k): echo 'selected'; endif; ?>><?php echo e($k); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                    <input type="search" name="q" value="<?php echo e($q ?? ''); ?>" placeholder="Cari siswa / guru...">
                    <button type="submit" class="btn-sm" style="border:none;cursor:pointer">Filter</button>
                </form>
                <div style="overflow-x:auto">
                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Siswa</th>
                                <th>NIS</th>
                                <th>Kelas</th>
                                <th>Guru BK</th>
                                <th>Tanggal</th>
                                <th>Jenis</th>
                                <th>Kategori</th>
                                <th>Konfirmasi</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $__empty_1 = true; $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <?php
                                $sk = $r->status_konfirmasi ?? 'Belum Dikonfirmasi';
                                if (in_array($sk, ['Tervalidasi','Dikonfirmasi'], true)) $sk = 'Terkonfirmasi';
                            ?>
                            <tr>
                                <td><?php echo e($i + 1); ?></td>
                                <td><strong><?php echo e(optional($r->siswa)->nama ?? '-'); ?></strong></td>
                                <td style="font-family:monospace"><?php echo e(optional($r->siswa)->nis ?? '-'); ?></td>
                                <td><?php echo e($r->kelas_siswa ?? optional($r->siswa)->kelas ?? '-'); ?></td>
                                <td><?php echo e($r->guru_bk); ?></td>
                                <td><?php echo e($r->tanggal ? \Carbon\Carbon::parse($r->tanggal)->format('d/m/Y') : '–'); ?> <?php echo e($r->jam ? substr((string)$r->jam,0,5) : ''); ?></td>
                                <td><?php echo e($r->jenis); ?></td>
                                <td><?php echo e($r->kategori); ?></td>
                                <td><span class="status-badge <?php echo e($sk==='Terkonfirmasi'?'status-selesai':'status-belum'); ?>"><?php echo e($sk); ?></span></td>
                                <td><span class="status-badge <?php echo e($r->status==='Selesai'?'status-selesai':($r->status==='Dibatalkan'?'status-dibatalkan':'status-proses')); ?>"><?php echo e($r->status); ?></span></td>
                                <td><a href="<?php echo e(route('kepsek.konseling.show', $r->id)); ?>" class="btn-sm">Detail</a></td>
                            </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr><td colspan="11" style="text-align:center;padding:40px;color:#718096">Tidak ada data</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\asus\Downloads\bk-full-laravel\resources\views/kepsek/konseling.blade.php ENDPATH**/ ?>