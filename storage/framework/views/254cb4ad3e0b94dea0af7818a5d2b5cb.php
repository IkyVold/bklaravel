<?php $__env->startSection('title', 'Status Konseling'); ?>
<?php $__env->startSection('main_class', ''); ?>
<?php $__env->startPush('styles'); ?>
<link rel="stylesheet" href="<?php echo e(asset('css/status.css')); ?>">
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
<?php
    $namaGuru = $row->guru_bk ?? '-';
    $spesialisasi = $guru?->spesialisasi ?? '-';
    $npsn = $guru?->npsn ?? '-';
    $tanggal = $row->tanggal
        ? \Carbon\Carbon::parse($row->tanggal)->locale('id')->translatedFormat('d F Y')
        : '-';
    $jam = $row->jam ? substr((string) $row->jam, 0, 5) : '-';
    $jenis = $row->jenis ?? '-';
    $kategori = $row->kategori ?? '-';
    $deskripsi = $row->deskripsi ?: 'Tidak ada deskripsi';
    $status = $row->status ?? 'Menunggu';
    $statusKonfirmasi = $row->status_konfirmasi ?? 'Belum Dikonfirmasi';

    $statusBadgeClass = 'badge badge-process';
    $statusBadgeLabel = $status;
    if ($status === 'Selesai') {
        $statusBadgeClass = 'badge badge-validated';
        $statusBadgeLabel = 'Selesai';
    } elseif ($status === 'Dibatalkan') {
        $statusBadgeClass = 'badge badge-pending';
        $statusBadgeLabel = 'Dibatalkan';
    }

    $isTerkonfirmasi = in_array($statusKonfirmasi, ['Terkonfirmasi', 'Dikonfirmasi'], true);
    $showChatBtn = $isTerkonfirmasi && $jenis === 'Daring';
?>

<div class="status-page">
    <div class="page">
        <div class="page-header">
            <div class="page-badge"><span class="dot"></span> Live Tracking</div>
            <h1 class="page-title">Status Konseling</h1>
            <p class="page-subtitle">Jadwal kemungkinan berubah terkait konfirmasi guru BK</p>
            <div>
                <span class="conn-pill">
                    <span class="conn-indicator connected"></span>
                    <span>Terhubung ke server</span>
                </span>
            </div>
        </div>

        <?php if(($sesiSebelumnya ?? null) || (($sesiLanjutan ?? collect())->isNotEmpty()) || !empty($row->pengajuan_sebelumnya_id)): ?>
        <div class="card" style="border-color:#bfdbfe;background:#f8fbff;margin-bottom:16px">
            <div class="card-header" style="background:transparent;border-bottom:1px solid #dbeafe">
                <span class="card-header-label">🔗 Rantai Sesi Konseling</span>
            </div>
            <div style="padding:14px 16px">
                <?php if($sesiSebelumnya ?? null): ?>
                    <div style="margin-bottom:12px">
                        <div style="font-size:12px;color:#64748b;margin-bottom:4px">Sesi sebelumnya</div>
                        <a href="<?php echo e(route('siswa.konseling.show', $sesiSebelumnya->id)); ?>"
                           style="display:block;padding:10px 12px;border-radius:8px;border:1px solid #bfdbfe;background:#fff;text-decoration:none;color:inherit">
                            <strong>#<?php echo e($sesiSebelumnya->id); ?></strong>
                            · <?php echo e($sesiSebelumnya->tanggal ? \Carbon\Carbon::parse($sesiSebelumnya->tanggal)->format('Y-m-d') : '–'); ?>

                            · <?php echo e($sesiSebelumnya->kategori ?: '–'); ?>

                            · <em><?php echo e($sesiSebelumnya->status); ?></em>
                        </a>
                    </div>
                <?php elseif(!empty($row->pengajuan_sebelumnya_id)): ?>
                    <div style="margin-bottom:12px;font-size:13px">
                        Lanjutan dari sesi
                        <a href="<?php echo e(route('siswa.konseling.show', $row->pengajuan_sebelumnya_id)); ?>" style="color:#1d4ed8">#<?php echo e($row->pengajuan_sebelumnya_id); ?></a>
                    </div>
                <?php endif; ?>
                <?php if(($sesiLanjutan ?? collect())->isNotEmpty()): ?>
                    <div style="font-size:12px;color:#64748b;margin-bottom:4px">Sesi lanjutan</div>
                    <div style="display:grid;gap:8px">
                        <?php $__currentLoopData = $sesiLanjutan; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $child): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <a href="<?php echo e(route('siswa.status', $child->id)); ?>"
                           style="display:block;padding:10px 12px;border-radius:8px;border:1px solid #bfdbfe;background:#fff;text-decoration:none;color:inherit">
                            <strong>#<?php echo e($child->id); ?></strong>
                            · <?php echo e($child->tanggal ? \Carbon\Carbon::parse($child->tanggal)->format('Y-m-d') : '–'); ?>

                            <?php echo e($child->jam ? substr((string)$child->jam,0,5) : ''); ?>

                            · <?php echo e($child->kategori ?: '–'); ?>

                            · <em><?php echo e($child->status); ?></em>
                        </a>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                <?php endif; ?>
                <?php if(!empty($row->pengajuan_sebelumnya_id)): ?>
                    <p style="margin:12px 0 0;font-size:12.5px;color:#1e40af">
                        Ini adalah <strong>sesi monitoring lanjutan</strong>. Jadwal ditetapkan oleh Guru BK setelah laporan “Perlu Monitoring Lanjutan”.
                    </p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <span class="card-header-label">Informasi Guru &amp; Jadwal</span>
                <span class="<?php echo e($statusBadgeClass); ?>">
                    <svg width="8" height="8" viewBox="0 0 8 8" fill="currentColor"><circle cx="4" cy="4" r="3" /></svg>
                    <?php echo e($statusBadgeLabel); ?>

                </span>
            </div>
            <div class="info-grid">
                <div class="info-row">
                    <span class="info-label">Nama Guru</span>
                    <span class="info-value"><?php echo e($namaGuru); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Spesialis Bidang</span>
                    <span class="info-value"><?php echo e($spesialisasi); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">NPSN</span>
                    <span class="info-value"><?php echo e($npsn); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Tanggal</span>
                    <span class="info-value"><?php echo e($tanggal); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Jam</span>
                    <span class="info-value"><?php echo e($jam); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Jenis Konseling</span>
                    <span class="info-value">
                        <?php if($jenis === 'Daring'): ?>
                            <span class="badge badge-daring">Daring</span>
                        <?php else: ?>
                            <?php echo e($jenis); ?>

                        <?php endif; ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Kategori</span>
                    <span class="info-value"><?php echo e($kategori); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Status Konfirmasi</span>
                    <span class="info-value">
                        <?php if($isTerkonfirmasi): ?>
                            <span class="badge badge-validated">✓ Terkonfirmasi</span>
                        <?php else: ?>
                            <span class="badge badge-pending">Belum Dikonfirmasi</span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="desc-card">
            <div class="desc-label">Deskripsi Masalah</div>
            <p class="desc-text"><?php echo e($deskripsi); ?></p>
        </div>

        <div class="actions">
            <a href="<?php echo e(route('siswa.konseling.index')); ?>" class="btn btn-primary" style="text-decoration:none">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="16" height="16">
                    <polyline points="20 6 9 17 4 12" />
                </svg>
                Selesai
            </a>
            <form method="POST" action="<?php echo e(route('siswa.konseling.batal', $row->id)); ?>" style="display:inline" class="js-konsul-ulang-form"
                  onsubmit="return (function(f){
                      var alasan = prompt('Alasan pembatalan (minimal 10 karakter):', 'Ingin mengajukan ulang konsultasi');
                      if (alasan === null) return false;
                      alasan = alasan.trim();
                      if (alasan.length < 10) { alert('Alasan pembatalan minimal 10 karakter.'); return false; }
                      f.querySelector('input[name=alasan]').value = alasan;
                      return confirm('Apakah Anda yakin ingin membatalkan pengajuan ini dan mengajukan ulang?');
                  })(this)">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="alasan" value="">
                <input type="hidden" name="ajukan_ulang" value="1">
                <button type="submit" class="btn btn-secondary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                        <polyline points="1 4 1 10 7 10" />
                        <path d="M3.51 15a9 9 0 1 0 .49-3.51" />
                    </svg>
                    Konsul Ulang
                </button>
            </form>
            <?php if($showChatBtn): ?>
                <a href="<?php echo e(route('siswa.chat', $row->id)); ?>" class="btn btn-chat-online" style="text-decoration:none" title="Chat online (fitur chat penuh menyusul)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
                    </svg>
                    Mulai Chat Online
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\asus\Downloads\bk-full-laravel\resources\views/siswa/status.blade.php ENDPATH**/ ?>