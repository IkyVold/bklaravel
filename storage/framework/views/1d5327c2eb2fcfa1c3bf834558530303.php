<?php $__env->startSection('title', 'Detail Konseling'); ?>
<?php $__env->startSection('main_class', ''); ?>
<?php $__env->startPush('styles'); ?>
<link rel="stylesheet" href="<?php echo e(asset('css/detailHistory.css')); ?>">
<style>
body:has(.detail-history-page) > main,
main:has(.detail-history-page) {
  max-width: none !important;
  margin: 0 !important;
  padding: 0 !important;
}
</style>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
<?php
    $guruNama = $row->guru_bk ?? ($guru->nama ?? '-');
    $status = $row->status ?? 'Proses';
    $sk = $row->status_konfirmasi ?? 'Belum Dikonfirmasi';
    if (in_array($sk, ['Tervalidasi', 'Dikonfirmasi'], true)) {
        $statusKonfirmasi = 'Terkonfirmasi';
    } else {
        $statusKonfirmasi = $sk ?: 'Belum Dikonfirmasi';
    }
    $isTerkonfirmasi = $statusKonfirmasi === 'Terkonfirmasi';

    $statusBadgeClass = 'badge-proses';
    if ($status === 'Selesai') $statusBadgeClass = 'badge-selesai';
    elseif ($status === 'Dibatalkan') $statusBadgeClass = 'badge-dibatalkan';

    $hasLaporan = !empty($row->laporan_kesimpulan) || !empty($row->laporan_rekomendasi)
        || !empty($row->laporan_status_penanganan) || !empty($row->laporan);

    $showChatBtn = (($row->jenis ?? '') === 'Daring') && $isTerkonfirmasi && $status !== 'Dibatalkan' && $status !== 'Selesai';
    $canBatalkan = $status === 'Proses' && !$isTerkonfirmasi;

    $tgl = $row->tanggal ? \Carbon\Carbon::parse($row->tanggal)->translatedFormat('d M Y') : '—';
    $jam = $row->jam ? substr((string) $row->jam, 0, 5) : '—';
    $tglKonf = $row->tanggal_konfirmasi ? \Carbon\Carbon::parse($row->tanggal_konfirmasi)->translatedFormat('d M Y') : null;
    $jamKonf = $row->jam_konfirmasi ? substr((string) $row->jam_konfirmasi, 0, 5) : null;

    $laporanStatusClass = 'badge-selesai';
    $sp = $row->laporan_status_penanganan ?? '';
    if (str_contains(strtolower($sp), 'monitoring')) $laporanStatusClass = 'badge-proses';
    elseif (str_contains(strtolower($sp), 'rujuk')) $laporanStatusClass = 'badge-dibatalkan';

    $sesiSebelumnya = $sesiSebelumnya ?? null;
    $sesiLanjutan = $sesiLanjutan ?? collect();
?>

<div class="detail-history-page">
    <div class="connection-status">
        <span class="status-dot connected"></span>
        <span>Terhubung ke server</span>
    </div>

    <div class="page-wrap">
        <div class="breadcrumb">
            <a href="<?php echo e(route('siswa.dashboard')); ?>">Beranda</a>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
            <a href="<?php echo e(route('siswa.konseling.index')); ?>">Riwayat</a>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
            <span>Detail Konseling</span>
        </div>

        
        <div class="header-card">
            <div class="header-card-left">
                <div class="guru-avatar"><?php echo e(strtoupper(mb_substr($guruNama, 0, 1))); ?></div>
                <div>
                    <div class="header-guru-name"><?php echo e($guruNama); ?></div>
                    <div class="header-guru-sub">Guru Bimbingan Konseling</div>
                </div>
            </div>
            <span class="badge <?php echo e($statusBadgeClass); ?>">
                <span class="badge-dot"></span>
                <?php echo e($status); ?>

            </span>
        </div>

        
        <?php if($sesiSebelumnya || $sesiLanjutan->isNotEmpty() || !empty($row->pengajuan_sebelumnya_id)): ?>
        <div class="info-card" style="border-color:#bfdbfe;background:#f8fbff">
            <div class="card-section-title">🔗 Rantai Sesi Konseling</div>

            <?php if($sesiSebelumnya): ?>
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

            <?php if($sesiLanjutan->isNotEmpty()): ?>
                <div style="font-size:12px;color:#64748b;margin-bottom:4px">Sesi lanjutan</div>
                <div style="display:grid;gap:8px">
                    <?php $__currentLoopData = $sesiLanjutan; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $child): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <a href="<?php echo e(route('siswa.konseling.show', $child->id)); ?>"
                       style="display:block;padding:10px 12px;border-radius:8px;border:1px solid #bfdbfe;background:#fff;text-decoration:none;color:inherit">
                        <strong>#<?php echo e($child->id); ?></strong>
                        · <?php echo e($child->tanggal ? \Carbon\Carbon::parse($child->tanggal)->format('Y-m-d') : '–'); ?>

                        · <?php echo e($child->kategori ?: '–'); ?>

                        · <em><?php echo e($child->status); ?></em>
                    </a>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            <?php endif; ?>

            <?php if(!empty($row->pengajuan_sebelumnya_id) && $status === 'Proses'): ?>
                <p style="margin:12px 0 0;font-size:12.5px;color:#1e40af">
                    Ini adalah <strong>sesi monitoring lanjutan</strong> dari konseling sebelumnya.
                </p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        
        <div class="info-card">
            <div class="card-section-title">📋 Informasi Jadwal</div>
            <div class="info-grid">
                <div class="info-cell">
                    <div class="info-cell-label">Tanggal</div>
                    <div class="info-cell-value"><?php echo e($tgl); ?></div>
                </div>
                <div class="info-cell">
                    <div class="info-cell-label">Jam</div>
                    <div class="info-cell-value"><?php echo e($jam); ?></div>
                </div>
                <div class="info-cell">
                    <div class="info-cell-label">Jenis</div>
                    <div class="info-cell-value"><?php echo e($row->jenis ?: '—'); ?></div>
                </div>
                <div class="info-cell">
                    <div class="info-cell-label">Kategori</div>
                    <div class="info-cell-value"><?php echo e($row->kategori ?: '—'); ?></div>
                </div>
                <div class="info-cell">
                    <div class="info-cell-label">Status Konfirmasi</div>
                    <div class="info-cell-value"><?php echo e($statusKonfirmasi); ?></div>
                </div>
                <?php if($isTerkonfirmasi && $tglKonf): ?>
                <div class="info-cell">
                    <div class="info-cell-label">Jadwal Dikonfirmasi</div>
                    <div class="info-cell-value"><?php echo e($tglKonf); ?><?php echo e($jamKonf ? ' · '.$jamKonf : ''); ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        
        <div class="desc-card">
            <div class="desc-label">Deskripsi Masalah</div>
            <p class="desc-text"><?php echo e($row->deskripsi ?: 'Tidak ada deskripsi'); ?></p>
        </div>

        
        <?php if($hasLaporan): ?>
        <div class="info-card">
            <div class="card-section-title">📝 Laporan Guru BK</div>
            <div class="laporan-list">
                <div class="laporan-item">
                    <div class="laporan-item-label">Kesimpulan Konseling</div>
                    <div class="laporan-item-value"><?php echo e($row->laporan_kesimpulan ?: 'Tidak ada kesimpulan'); ?></div>
                </div>
                <div class="laporan-item">
                    <div class="laporan-item-label">Rekomendasi / Tindak Lanjut</div>
                    <div class="laporan-item-value"><?php echo e($row->laporan_rekomendasi ?: 'Tidak ada rekomendasi'); ?></div>
                </div>
                <div class="laporan-item">
                    <div class="laporan-item-label">Status Penanganan</div>
                    <div class="laporan-item-value">
                        <span class="badge <?php echo e($laporanStatusClass); ?>">
                            <span class="badge-dot"></span>
                            <?php echo e($row->laporan_status_penanganan ?: 'Selesai'); ?>

                        </span>
                    </div>
                </div>
                <?php if(!empty($row->laporan_catatan_tambahan) && $row->laporan_catatan_tambahan !== '-'): ?>
                <div class="laporan-item">
                    <div class="laporan-item-label">Catatan Tambahan</div>
                    <div class="laporan-item-value"><?php echo e($row->laporan_catatan_tambahan); ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        
        <?php if($status === 'Dibatalkan'): ?>
        <div class="desc-card" style="border-color:#F0B8B8;background:#FDF6F6">
            <div class="desc-label" style="color:#A32D2D">Alasan Pembatalan</div>
            <p class="desc-text"><?php echo e($row->alasan_batal ?? 'Dibatalkan oleh siswa'); ?></p>
        </div>
        <?php endif; ?>

        
        <div class="action-row">
            <a href="<?php echo e(route('siswa.konseling.index')); ?>" class="btn btn-outline">
                ← Kembali ke Riwayat
            </a>
            <?php if($canBatalkan): ?>
            <button type="button" class="btn btn-batal" id="openBatalModal">
                Batalkan Pengajuan
            </button>
            <?php endif; ?>
            <?php if($showChatBtn): ?>
            <a href="<?php echo e(route('siswa.chat', $row->id)); ?>" class="btn btn-primary" style="text-decoration:none">
                Mulai Chat Online
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if($canBatalkan): ?>
<div class="batal-modal-overlay" id="batalOverlay" style="display:none">
    <div class="batal-modal">
        <h3 class="batal-modal-title">Batalkan Pengajuan?</h3>
        <p class="batal-modal-text">
            Pengajuan akan ditandai sebagai <strong>Dibatalkan</strong>. Tuliskan alasan (minimal 10 karakter).
        </p>
        <form method="POST" action="<?php echo e(route('siswa.konseling.batal', $row->id)); ?>" id="batalForm">
            <?php echo csrf_field(); ?>
            <textarea name="alasan" id="alasanBatal" class="batal-modal-textarea" rows="4"
                      placeholder="Contoh: Jadwal bentrok dengan kegiatan sekolah..." required minlength="10"></textarea>
            <div class="batal-modal-hint" id="batalHint">Minimal 10 karakter</div>
            <div class="batal-modal-actions">
                <button type="button" class="btn btn-outline" id="closeBatalModal">Tutup</button>
                <button type="submit" class="btn btn-batal">Konfirmasi Batalkan</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<?php if($canBatalkan): ?>
<script>
(function () {
    var overlay = document.getElementById('batalOverlay');
    var openBtn = document.getElementById('openBatalModal');
    var closeBtn = document.getElementById('closeBatalModal');
    var ta = document.getElementById('alasanBatal');
    var hint = document.getElementById('batalHint');
    var form = document.getElementById('batalForm');
    if (!overlay) return;
    function open() { overlay.style.display = 'flex'; }
    function close() { overlay.style.display = 'none'; }
    if (openBtn) openBtn.addEventListener('click', open);
    if (closeBtn) closeBtn.addEventListener('click', close);
    overlay.addEventListener('click', function (e) { if (e.target === overlay) close(); });
    if (ta && hint) {
        ta.addEventListener('input', function () {
            var n = ta.value.trim().length;
            if (n === 0) { hint.textContent = 'Minimal 10 karakter'; hint.style.color = ''; }
            else if (n < 10) { hint.textContent = (10 - n) + ' karakter lagi'; hint.style.color = '#A32D2D'; }
            else { hint.textContent = n + ' karakter'; hint.style.color = '#0F6E56'; }
        });
    }
    if (form) {
        form.addEventListener('submit', function (e) {
            if ((ta.value || '').trim().length < 10) {
                e.preventDefault();
                alert('Alasan pembatalan minimal 10 karakter.');
                return false;
            }
            if (!confirm('Yakin batalkan pengajuan ini?')) {
                e.preventDefault();
                return false;
            }
        });
    }
})();
</script>
<?php endif; ?>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\asus\Downloads\bk-full-laravel\resources\views/siswa/history-detail.blade.php ENDPATH**/ ?>