<?php $__env->startSection('title', 'Dashboard Kepala Sekolah'); ?>
<?php $__env->startSection('main_class', ''); ?>
<?php $__env->startPush('styles'); ?>
<link rel="stylesheet" href="<?php echo e(asset('css/kepsekDashboard.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('css/kepsek-shell.css')); ?>">
<?php $__env->stopPush(); ?>
<?php $__env->startSection('content'); ?>
<?php
    $user = session('auth_user', []);
    $nama = $user['nama'] ?? 'Kepala Sekolah';
    $sekolah = $user['sekolah'] ?? 'Sekolah';
    $periode = now()->locale('id')->translatedFormat('F Y');

    // Kategori colors (same as React)
    $kategoriColors = [
        ['key' => 'akademik', 'label' => 'Akademik', 'color' => '#004085', 'value' => $stats['akademik'] ?? 0],
        ['key' => 'sosial',   'label' => 'Sosial',   'color' => '#155724', 'value' => $stats['sosial'] ?? 0],
        ['key' => 'pribadi',  'label' => 'Pribadi',  'color' => '#856404', 'value' => $stats['pribadi'] ?? 0],
        ['key' => 'karir',    'label' => 'Karir',    'color' => '#0c5460', 'value' => $stats['karir'] ?? 0],
        ['key' => 'bullying', 'label' => 'Bullying', 'color' => '#721c24', 'value' => $stats['bullying'] ?? 0],
        ['key' => 'keluarga', 'label' => 'Keluarga', 'color' => '#6c757d', 'value' => $stats['keluarga'] ?? 0],
    ];
    $pieData = collect($kategoriColors)->filter(fn($d) => $d['value'] > 0)->sortByDesc('value')->values();
    $pieTotal = $pieData->sum('value');
    $pct = fn($v, $t) => $t > 0 ? number_format(($v / $t) * 100, 1) : '0.0';

    // Build conic-gradient
    $cumulative = 0;
    $gradientParts = [];
    foreach ($pieData as $d) {
        $start = $cumulative;
        $cumulative += ($pieTotal > 0 ? ($d['value'] / $pieTotal) * 360 : 0);
        $gradientParts[] = "{$d['color']} {$start}deg {$cumulative}deg";
    }
    $conic = count($gradientParts) ? implode(', ', $gradientParts) : '#e2e8f0 0deg 360deg';

    // Status bars
    $statusBars = [
        ['key' => 'proses',        'label' => 'Proses',        'color' => 'var(--coral-400)', 'value' => $stats['proses'] ?? 0],
        ['key' => 'selesai',       'label' => 'Selesai',       'color' => 'var(--teal-400)',  'value' => $stats['selesai'] ?? 0],
        ['key' => 'dibatalkan',    'label' => 'Dibatalkan',    'color' => 'var(--red-600)',   'value' => $stats['dibatalkan'] ?? 0],
        ['key' => 'terkonfirmasi', 'label' => 'Terkonfirmasi', 'color' => 'var(--purple-800)','value' => $stats['terkonfirmasi'] ?? 0],
    ];
    $statusMax = max(array_column($statusBars, 'value') ?: [1]) ?: 1;

    // Kategori bar max
    $katMax = max(array_column($kategoriColors, 'value') ?: [1]) ?: 1;
?>
<div class="kepsek-page">
    <?php echo $__env->make('partials.kepsek-header', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <div class="container">
        <?php echo $__env->make('partials.kepsek-sidebar', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <div class="main-content">
            <div class="content-header">
                <h2>📊 Dashboard Monitoring</h2>
                <p>Selamat datang, <?php echo e($nama); ?>. Berikut ringkasan layanan konseling di <?php echo e($sekolah); ?></p>
            </div>

            
            <div class="summary-box">
                <div class="summary-title"><span>📅</span> Periode: <?php echo e($periode); ?></div>
                <div class="summary-grid">
                    <div class="summary-item">
                        <div class="summary-label">Total Konseling</div>
                        <div class="summary-value"><?php echo e($stats['total']); ?></div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Siswa Aktif</div>
                        <div class="summary-value"><?php echo e($stats['siswaAktif']); ?></div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Guru BK Aktif</div>
                        <div class="summary-value"><?php echo e($stats['guruAktif']); ?></div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Konseling Selesai</div>
                        <div class="summary-value"><?php echo e($stats['selesai']); ?></div>
                    </div>
                </div>
            </div>

            
            <div class="stats-grid">
                <div class="stat-card stat-total">
                    <div class="stat-icon">📋</div>
                    <div class="stat-info">
                        <h3>Total Konseling</h3>
                        <div class="stat-value"><?php echo e($stats['total']); ?></div>
                    </div>
                </div>
                <div class="stat-card stat-akademik">
                    <div class="stat-icon">📚</div>
                    <div class="stat-info">
                        <h3>Akademik</h3>
                        <div class="stat-value"><?php echo e($stats['akademik']); ?></div>
                    </div>
                </div>
                <div class="stat-card stat-sosial">
                    <div class="stat-icon">👥</div>
                    <div class="stat-info">
                        <h3>Sosial</h3>
                        <div class="stat-value"><?php echo e($stats['sosial']); ?></div>
                    </div>
                </div>
                <div class="stat-card stat-pribadi">
                    <div class="stat-icon">💭</div>
                    <div class="stat-info">
                        <h3>Pribadi</h3>
                        <div class="stat-value"><?php echo e($stats['pribadi']); ?></div>
                    </div>
                </div>
                <div class="stat-card stat-bullying">
                    <div class="stat-icon">🛡️</div>
                    <div class="stat-info">
                        <h3>Bullying</h3>
                        <div class="stat-value"><?php echo e($stats['bullying']); ?></div>
                    </div>
                </div>
            </div>

            
            <div class="charts-grid">
                <div class="chart-card">
                    <div class="chart-title"><span>📊</span> Distribusi Kategori Konseling</div>
                    <div class="chart-container">
                        <?php if($pieTotal === 0): ?>
                            <div style="text-align:center;color:var(--gray-600)">Belum ada data</div>
                        <?php else: ?>
                            <div style="display:flex;flex-direction:column;align-items:center;gap:20px;width:100%">
                                <div style="position:relative;width:180px;height:180px;border-radius:50%;background:conic-gradient(<?php echo e($conic); ?>)">
                                    <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:white;width:100px;height:100px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-direction:column">
                                        <div style="font-size:24px;font-weight:700"><?php echo e($pieTotal); ?></div>
                                        <div style="font-size:11px;color:var(--gray-600)">Total Kasus</div>
                                    </div>
                                </div>
                                <div style="display:flex;flex-direction:column;gap:10px;width:100%">
                                    <?php $__currentLoopData = $pieData; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <?php $persen = $pct($item['value'], $pieTotal); ?>
                                        <div style="display:flex;align-items:center;gap:10px;font-size:13px">
                                            <div style="width:10px;height:10px;background:<?php echo e($item['color']); ?>;border-radius:2px;flex-shrink:0"></div>
                                            <div style="width:72px;flex-shrink:0;font-weight:600"><?php echo e($item['label']); ?></div>
                                            <div style="flex:1;background:var(--gray-100);height:10px;border-radius:6px;overflow:hidden">
                                                <div style="width:<?php echo e($persen); ?>%;background:<?php echo e($item['color']); ?>;height:10px"></div>
                                            </div>
                                            <div style="width:78px;text-align:right;flex-shrink:0;color:var(--gray-600)">
                                                <?php echo e($item['value']); ?> (<?php echo e($persen); ?>%)
                                            </div>
                                        </div>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="chart-card">
                    <div class="chart-title"><span>📈</span> Status Konseling</div>
                    <div class="chart-container">
                        <div style="display:flex;flex-direction:column;gap:12px;width:100%">
                            <?php $__currentLoopData = $statusBars; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $bar): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <div>
                                    <strong><?php echo e($bar['label']); ?></strong>
                                    <span style="float:right"><?php echo e($bar['value']); ?></span>
                                    <div style="background:var(--gray-100);height:8px;border-radius:4px;margin-top:4px">
                                        <div style="width:<?php echo e(($bar['value'] / $statusMax) * 100); ?>%;background:<?php echo e($bar['color']); ?>;height:8px;border-radius:4px"></div>
                                    </div>
                                </div>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </div>
                    </div>
                </div>
            </div>

            
            <div class="charts-grid" style="grid-template-columns:1fr">
                <div class="chart-card">
                    <div class="chart-title"><span>📊</span> Diagram Batang — Jumlah Kasus per Kategori</div>
                    <div class="chart-container">
                        <?php if($pieTotal === 0): ?>
                            <div style="text-align:center;color:var(--gray-600)">Belum ada data</div>
                        <?php else: ?>
                            <div style="display:flex;align-items:flex-end;justify-content:space-around;gap:12px;height:220px;padding:10px 0 30px;border-bottom:1px solid var(--gray-100)">
                                <?php $__currentLoopData = $kategoriColors; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <?php
                                        $h = $katMax > 0 ? max(4, ($k['value'] / $katMax) * 180) : 4;
                                    ?>
                                    <div style="display:flex;flex-direction:column;align-items:center;flex:1;max-width:90px">
                                        <div style="font-size:13px;font-weight:700;margin-bottom:6px;color:var(--gray-700)"><?php echo e($k['value']); ?></div>
                                        <div style="width:100%;max-width:48px;height:<?php echo e($h); ?>px;background:<?php echo e($k['color']); ?>;border-radius:6px 6px 0 0;transition:height .3s"></div>
                                        <div style="font-size:11px;margin-top:8px;text-align:center;color:var(--gray-600);line-height:1.2"><?php echo e($k['label']); ?></div>
                                    </div>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            
            <div class="table-container">
                <div class="table-header">
                    <h3>📊 Rekap Jumlah &amp; Persentase per Kategori Masalah</h3>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Kategori</th>
                            <th>Jumlah Kasus</th>
                            <th>Persentase</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($pieTotal === 0): ?>
                            <tr>
                                <td colspan="3" style="text-align:center;color:var(--gray-600);padding:24px">Belum ada data konseling</td>
                            </tr>
                        <?php else: ?>
                            <?php $__currentLoopData = $kategoriColors; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <tr>
                                    <td>
                                        <span style="display:inline-flex;align-items:center;gap:8px">
                                            <span style="width:10px;height:10px;border-radius:2px;background:<?php echo e($item['color']); ?>;display:inline-block"></span>
                                            <?php echo e($item['label']); ?>

                                        </span>
                                    </td>
                                    <td><?php echo e($item['value']); ?></td>
                                    <td><?php echo e($pct($item['value'], $pieTotal)); ?>%</td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            <tr style="font-weight:700;border-top:2px solid var(--gray-100)">
                                <td>Total</td>
                                <td><?php echo e($pieTotal); ?></td>
                                <td>100%</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            
            <h3 style="margin:20px 0 15px;display:flex;align-items:center;gap:10px">
                <span>👨‍🏫</span> Aktivitas Guru BK
            </h3>
            <div class="guru-grid">
                <?php $__empty_1 = true; $__currentLoopData = $guruCards; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $g): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <div class="guru-card">
                        <div class="guru-avatar"><?php echo e(mb_substr($g['nama'], 0, 1)); ?></div>
                        <div class="guru-info">
                            <div class="guru-name"><?php echo e($g['nama']); ?></div>
                            <div class="guru-stats">
                                Total: <span class="guru-value"><?php echo e($g['total']); ?></span> |
                                Selesai: <span class="guru-value"><?php echo e($g['selesai']); ?></span> |
                                Laporan: <span class="guru-value"><?php echo e($g['laporan']); ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <div style="grid-column:1/-1;text-align:center;padding:40px;color:var(--gray-600)">
                        Belum ada aktivitas konseling
                    </div>
                <?php endif; ?>
            </div>

            
            <div class="table-container" style="margin-top:24px">
                <div class="table-header">
                    <h3>📋 5 Konseling Terbaru</h3>
                    <a href="<?php echo e(route('kepsek.konseling')); ?>" class="export-btn btn-excel" style="text-decoration:none">
                        <span>📋</span> Lihat semua
                    </a>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Siswa</th>
                            <th>Guru BK</th>
                            <th>Kategori</th>
                            <th>Status</th>
                            <th>Laporan</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__empty_1 = true; $__currentLoopData = $recent; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <?php
                                $statusClass = 'status-proses';
                                if ($r->status === 'Selesai') $statusClass = 'status-selesai';
                                elseif ($r->status === 'Dibatalkan') $statusClass = 'status-dibatalkan';
                                $hasLaporan = !empty($r->laporan_kesimpulan) || !empty($r->laporan_created_at);
                            ?>
                            <tr>
                                <td>
                                    <?php echo e($r->tanggal ? \Carbon\Carbon::parse($r->tanggal)->format('d/m/Y') : '-'); ?>

                                    <?php echo e($r->jam ? substr((string)$r->jam, 0, 5) : ''); ?>

                                </td>
                                <td><?php echo e(optional($r->siswa)->nama ?? '-'); ?></td>
                                <td><span class="guru-badge"><?php echo e($r->guru_bk); ?></span></td>
                                <td><?php echo e($r->kategori); ?></td>
                                <td><span class="status-badge <?php echo e($statusClass); ?>"><?php echo e($r->status); ?></span></td>
                                <td>
                                    <?php if($hasLaporan): ?>
                                        <span class="status-badge status-selesai">✅ Ada</span>
                                    <?php else: ?>
                                        <span class="status-badge status-proses">❌ Belum</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?php echo e(route('kepsek.konseling.show', $r->id)); ?>" class="export-btn btn-excel" style="padding:5px 10px;text-decoration:none">
                                        <span>🔍</span> Detail
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr>
                                <td colspan="7" style="text-align:center;padding:40px;color:var(--gray-600)">Belum ada data konseling</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\asus\Downloads\bk-full-laravel\resources\views/kepsek/dashboard.blade.php ENDPATH**/ ?>