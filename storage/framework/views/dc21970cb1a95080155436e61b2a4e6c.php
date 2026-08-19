<?php $__env->startSection('title', 'Jadwal Rutin'); ?>
<?php $__env->startSection('main_class', ''); ?>
<?php $__env->startPush('styles'); ?>
<link rel="stylesheet" href="<?php echo e(asset('css/guruBk.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('css/guru-shell.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('css/jadwal-rutin.css')); ?>">
<?php $__env->stopPush(); ?>
<?php $__env->startSection('content'); ?>
<?php $activeTab = 'jadwal-rutin'; ?>
<div class="guru-bk-page">
    <?php echo $__env->make('partials.guru-header', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <div class="container">
        <?php echo $__env->make('partials.guru-sidebar', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <div class="main-content">
            <div class="content-header">
                <h2>📅 Jadwal Konseling Rutin</h2>
                <p>Atur slot tetap (hari &amp; jam) agar siswa dapat memilih konsultasi <strong>rutin</strong>. Pengajuan di luar slot = <strong>nonrutin</strong>.</p>
            </div>

            <div class="panel" style="padding:20px;margin-bottom:20px;max-width:720px">
                <h3 style="margin-top:0;font-size:16px">+ Tambah Slot Rutin</h3>
                <form method="POST" action="<?php echo e(route('guru.jadwal-rutin.store')); ?>" class="jr-form">
                    <?php echo csrf_field(); ?>
                    <div class="jr-grid">
                        <div>
                            <label>Hari</label>
                            <select name="hari" required>
                                <?php $__currentLoopData = $hariList; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $num => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($num); ?>"><?php echo e($label); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                        </div>
                        <div>
                            <label>Jam mulai</label>
                            <input type="time" name="jam_mulai" required value="09:00">
                        </div>
                        <div>
                            <label>Jam selesai (opsional)</label>
                            <input type="time" name="jam_selesai">
                        </div>
                        <div>
                            <label>Jenis</label>
                            <select name="jenis" required>
                                <option value="Luring">Luring</option>
                                <option value="Daring">Daring</option>
                            </select>
                        </div>
                        <div style="grid-column:1/-1">
                            <label>Keterangan (opsional)</label>
                            <input type="text" name="keterangan" placeholder="Contoh: Ruang BK / untuk kelas X" maxlength="150">
                        </div>
                    </div>
                    <button type="submit" class="btn-cetak btn-cetak-green" style="margin-top:12px">Simpan Slot</button>
                </form>
            </div>

            <div class="panel" style="padding:0;overflow:hidden">
                <table class="jr-table">
                    <thead>
                        <tr>
                            <th>Hari</th>
                            <th>Jam</th>
                            <th>Jenis</th>
                            <th>Keterangan</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__empty_1 = true; $__currentLoopData = $slots; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr class="<?php echo e($s->is_active ? '' : 'inactive'); ?>">
                            <td><strong><?php echo e($s->hari_label); ?></strong></td>
                            <td><?php echo e($s->jam_label); ?></td>
                            <td><?php echo e($s->jenis); ?></td>
                            <td><?php echo e($s->keterangan ?: '–'); ?></td>
                            <td>
                                <span class="badge-tipe <?php echo e($s->is_active ? 'badge-rutin' : 'badge-nonrutin'); ?>">
                                    <?php echo e($s->is_active ? 'Aktif' : 'Nonaktif'); ?>

                                </span>
                            </td>
                            <td style="white-space:nowrap">
                                <form action="<?php echo e(route('guru.jadwal-rutin.toggle', $s->id)); ?>" method="POST" style="display:inline">
                                    <?php echo csrf_field(); ?>
                                    <button type="submit" class="jr-btn"><?php echo e($s->is_active ? 'Nonaktifkan' : 'Aktifkan'); ?></button>
                                </form>
                                <form action="<?php echo e(route('guru.jadwal-rutin.destroy', $s->id)); ?>" method="POST" style="display:inline"
                                      onsubmit="return confirm('Hapus slot ini?')">
                                    <?php echo csrf_field(); ?>
                                    <?php echo method_field('DELETE'); ?>
                                    <button type="submit" class="jr-btn danger">Hapus</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr>
                            <td colspan="6" style="text-align:center;padding:28px;color:#888">
                                Belum ada slot rutin. Tambahkan di form atas agar siswa bisa memilih konsultasi rutin.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\asus\Downloads\bk-full-laravel\resources\views/guru/jadwal-rutin.blade.php ENDPATH**/ ?>