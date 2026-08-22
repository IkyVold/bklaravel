<?php $__env->startSection('title', 'Riwayat Konseling'); ?>
<?php $__env->startSection('main_class', ''); ?>
<?php $__env->startPush('styles'); ?>
<link rel="stylesheet" href="<?php echo e(asset('css/history.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('css/siswa-history-shell.css')); ?>">
<style>
/* Cegah style link global merusak kartu */
.history-page a.history-card,
.history-page a.history-card:visited,
.history-page a.history-card:hover,
.history-page a.history-card:active {
  text-decoration: none !important;
  color: inherit !important;
}
.history-page a.history-card * {
  text-decoration: none !important;
}
.history-page .info-line-val,
.history-page .info-line-key,
.history-page .history-category,
.history-page .history-date-chip {
  color: inherit;
}
.history-page .info-line-val { color: var(--text-primary, #1a1a18); font-weight: 600; }
.history-page .info-line-key { color: var(--text-muted, #5F5E5A); }
</style>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
<?php
    $total = $rows->count();
    $selesai = $rows->where('status', 'Selesai')->count();
    $proses = $rows->whereIn('status', ['Menunggu', 'Proses'])->count();
    $kategoriIcon = [
        'Akademik' => '📚', 'Sosial' => '👥', 'Pribadi' => '💭',
        'Karir' => '🎯', 'Bullying' => '🛡️', 'Keluarga' => '🏠',
    ];
?>
<div class="history-page">
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <div class="logo-icon">🛡️</div>
                <h2>StopBully</h2>
            </div>
            <p>Student Portal</p>
        </div>
        <div class="sidebar-divider"></div>
        <p class="sidebar-section-label">Menu</p>
        <ul class="sidebar-menu">
            <li>
                <a href="<?php echo e(route('siswa.profil')); ?>">
                    <span class="menu-icon">👤</span><span>Profile</span>
                </a>
            </li>
            <li>
                <a href="<?php echo e(route('siswa.konseling.index')); ?>" class="active">
                    <span class="menu-icon">📋</span><span>History</span>
                </a>
            </li>
            <li>
                <form action="<?php echo e(route('logout')); ?>" method="POST" style="margin:0">
                    <?php echo csrf_field(); ?>
                    <a href="#" onclick="event.preventDefault(); if(confirm('Logout?')) this.closest('form').submit();" style="cursor:pointer">
                        <span class="menu-icon">🚪</span><span>Logout</span>
                    </a>
                </form>
            </li>
        </ul>
    </aside>

    <main class="main-content">
        <div class="page-header">
            <div>
                <div class="breadcrumb">
                    <a href="<?php echo e(route('siswa.dashboard')); ?>">Home</a>
                    <span>/</span> History
                </div>
                <h1>Riwayat Konseling</h1>
                <p class="page-subtitle">
                    Status konseling akan diperbarui oleh Guru BK setelah konseling selesai dilaksanakan.
                </p>
            </div>
        </div>

        <?php if($total > 0): ?>
        <div class="summary-strip">
            <div class="summary-chip">
                <div class="summary-chip-dot" style="background:var(--accent,#534AB7)"></div>
                <div>
                    <div class="summary-chip-count" style="color:var(--accent,#534AB7)"><?php echo e($total); ?></div>
                    <div class="summary-chip-label">Total Sesi</div>
                </div>
            </div>
            <div class="summary-chip">
                <div class="summary-chip-dot" style="background:var(--success,#1A7A4A)"></div>
                <div>
                    <div class="summary-chip-count" style="color:var(--success,#1A7A4A)"><?php echo e($selesai); ?></div>
                    <div class="summary-chip-label">Selesai</div>
                </div>
            </div>
            <div class="summary-chip">
                <div class="summary-chip-dot" style="background:var(--warning,#C47A00)"></div>
                <div>
                    <div class="summary-chip-count" style="color:var(--warning,#C47A00)"><?php echo e($proses); ?></div>
                    <div class="summary-chip-label">Dalam Proses</div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if($total === 0): ?>
            <div class="empty-state">
                <div class="empty-icon">📋</div>
                <div class="empty-title">Belum Ada Riwayat Konseling</div>
                <div class="empty-sub">Mulai konseling pertama Anda untuk melihat riwayat di sini</div>
            </div>
        <?php else: ?>
            <div class="history-grid">
                <?php $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php
                        $hasLaporan = !empty($item->laporan_kesimpulan) || !empty($item->laporan);
                        $isTerkonfirmasi = in_array($item->status_konfirmasi ?? '', ['Terkonfirmasi', 'Tervalidasi', 'Dikonfirmasi'], true);
                        $st = $item->status ?? 'Menunggu';
                        $badgeCls = $st === 'Selesai' ? 'badge-selesai' : ($st === 'Dibatalkan' ? 'badge-dibatalkan' : 'badge-proses');
                        $kat = $item->kategori ?: '—';
                        $icon = $kategoriIcon[$kat] ?? '📚';
                        $tgl = $item->tanggal ? \Carbon\Carbon::parse($item->tanggal)->translatedFormat('d M Y') : '—';
                        $jam = $item->jam ? substr((string) $item->jam, 0, 5) : '—';
                        $warningNote = $st === 'Selesai' && !$hasLaporan;
                    ?>
                    <a href="<?php echo e(route('siswa.konseling.show', $item->id)); ?>" class="history-card">
                        <div class="history-card-header">
                            <div class="history-category">
                                <div class="category-icon"><?php echo e($icon); ?></div>
                                Konseling <?php echo e($kat); ?>

                            </div>
                            <div class="history-date-chip">📅 <?php echo e($tgl); ?></div>
                        </div>

                        <div class="history-card-body">
                            <div class="info-line">
                                <div class="info-line-icon">👨‍🏫</div>
                                <div class="info-line-key">Guru BK</div>
                                <div class="info-line-val"><?php echo e($item->guru_bk ?: '—'); ?></div>
                            </div>
                            <div class="info-line">
                                <div class="info-line-icon">📖</div>
                                <div class="info-line-key">Jenis</div>
                                <div class="info-line-val"><?php echo e($item->jenis ?: '—'); ?></div>
                            </div>
                            <div class="info-line">
                                <div class="info-line-icon">🕐</div>
                                <div class="info-line-key">Jam</div>
                                <div class="info-line-val"><?php echo e($jam); ?></div>
                            </div>
                            <div class="info-line">
                                <div class="info-line-icon">📌</div>
                                <div class="info-line-key">Status</div>
                                <div class="info-line-val">
                                    <span class="badge <?php echo e($badgeCls); ?>">
                                        <span class="badge-dot"></span><?php echo e($st); ?>

                                    </span>
                                </div>
                            </div>
                            <?php if(in_array($st, ['Menunggu', 'Proses'], true)): ?>
                            <div class="info-line">
                                <div class="info-line-icon"><?php echo e($isTerkonfirmasi ? '✅' : '⏳'); ?></div>
                                <div class="info-line-key">Konfirmasi</div>
                                <div class="info-line-val">
                                    <?php if($isTerkonfirmasi): ?>
                                        Sudah dikonfirmasi
                                    <?php else: ?>
                                        Menunggu konfirmasi jadwal
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <?php if($warningNote): ?>
                            <div class="history-warning">⚠️ Sesi selesai, laporan belum diisi Guru BK</div>
                        <?php endif; ?>

                        <div class="history-card-footer">
                            <div class="history-badges">
                                <?php if($hasLaporan): ?>
                                    <span class="badge badge-laporan"><span class="badge-dot"></span> Ada Laporan</span>
                                <?php elseif($st === 'Selesai'): ?>
                                    <span class="badge badge-nolap"><span class="badge-dot"></span> Tanpa Laporan</span>
                                <?php endif; ?>
                            </div>
                            <span class="card-arrow">Lihat detail →</span>
                        </div>
                    </a>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        <?php endif; ?>
    </main>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\asus\Downloads\bk-full-laravel\resources\views/siswa/konseling-index.blade.php ENDPATH**/ ?>