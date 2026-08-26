<?php $__env->startSection('title', 'Detail Konseling'); ?>
<?php $__env->startSection('main_class', ''); ?>
<?php $__env->startPush('styles'); ?>
<link rel="stylesheet" href="<?php echo e(asset('css/guruBk.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('css/guru-shell.css')); ?>">
<style>
.guru-bk-page .modal.show{display:flex!important;align-items:center;justify-content:center;position:fixed;inset:0;z-index:2000;background:rgba(16,24,40,.55);backdrop-filter:blur(3px);padding:20px;overflow-y:auto}
.guru-bk-page .modal-content{background:#fff;border-radius:16px;width:100%;max-width:800px;max-height:calc(100vh - 40px);overflow-y:auto;box-shadow:0 24px 60px rgba(16,24,40,.2);display:flex;flex-direction:column;margin:auto}
.guru-bk-page .modal-header{padding:18px 26px;display:flex;justify-content:space-between;align-items:center;background:linear-gradient(120deg,#26215C,#3C3489);color:#fff;border-radius:16px 16px 0 0}
.guru-bk-page .modal-header h3{margin:0;font-size:17px;font-weight:700}
.guru-bk-page .close-modal{background:rgba(255,255,255,.15);border:none;color:#fff;width:34px;height:34px;border-radius:50%;cursor:pointer;font-size:22px;text-decoration:none;display:flex;align-items:center;justify-content:center}
.guru-bk-page .modal-body{padding:26px}
.guru-bk-page .modal-footer{padding:16px 26px;border-top:1px solid #e8e6e0;display:flex;flex-wrap:wrap;justify-content:flex-end;gap:10px;background:#faf9f7;border-radius:0 0 16px 16px}
.guru-bk-page .detail-row{display:flex;margin-bottom:18px;padding-bottom:18px;border-bottom:1px solid #f1efe8;gap:12px}
.guru-bk-page .detail-label{width:170px;font-weight:700;color:#5F5E5A;font-size:13px;flex-shrink:0}
.guru-bk-page .detail-value{flex:1;color:#444;line-height:1.5;font-size:13.5px}
.guru-bk-page .detail-deskripsi{background:#EEEDFE;padding:18px;border-radius:10px;border-left:4px solid #7F77DD;white-space:pre-wrap;max-height:280px;overflow-y:auto}
.guru-bk-page .detail-hero{margin-bottom:20px;padding:15px;background:linear-gradient(135deg,rgba(83,74,183,.12),rgba(60,52,137,.12));border-radius:12px}
.guru-bk-page .detail-hero-inner{display:flex;align-items:center;gap:15px}
.guru-bk-page .detail-avatar{width:50px;height:50px;border-radius:50%;background:linear-gradient(135deg,#534AB7,#7F77DD);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:18px}
.guru-bk-page .info-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.guru-bk-page .konfirmasi-box{display:grid;grid-template-columns:1fr 1fr;gap:12px;padding:14px;background:#faf9f7;border-radius:10px;border:.5px solid #e8e6e0}
.guru-bk-page .konfirmasi-box label{display:block;font-size:12px;font-weight:600;margin-bottom:4px;color:#5F5E5A}
.guru-bk-page .konfirmasi-box input,.guru-bk-page .konfirmasi-box select{width:100%;padding:8px 10px;border:.5px solid #d3d1c7;border-radius:8px;font-size:14px}
.guru-bk-page .status-pill{display:inline-flex;padding:4px 12px;border-radius:999px;font-size:12px;font-weight:600}
.guru-bk-page .status-proses{background:#FFF1E7;color:#993C1D}
.guru-bk-page .status-selesai{background:#E1F5EE;color:#0F6E56}
.guru-bk-page .status-dibatalkan{background:#FCEBEB;color:#A32D2D}
.guru-bk-page .status-belum{background:#F1EFE8;color:#5F5E5A}
.guru-bk-page .laporan-box{background:#E1F5EE;border-radius:10px;padding:18px;border-left:4px solid #1D9E75;margin-top:8px}
.guru-bk-page .btn{display:inline-flex;align-items:center;gap:6px;padding:10px 16px;border-radius:10px;border:none;cursor:pointer;font-size:13.5px;font-weight:600;text-decoration:none;font-family:inherit}
.guru-bk-page .btn-konfirmasi{background:#0F6E56;color:#fff}
.guru-bk-page .btn-batal{background:#FCEBEB;color:#A32D2D}
.guru-bk-page .btn-detail{background:#F1EFE8;color:#444}
.guru-bk-page .btn-laporan{background:#534AB7;color:#fff}
.guru-bk-page .btn-locked{background:#e8e6e0;color:#888;cursor:not-allowed}
.guru-bk-page .laporan-form label{display:block;font-size:12px;font-weight:600;color:#5F5E5A;margin-bottom:4px}
.guru-bk-page .laporan-form textarea,.guru-bk-page .laporan-form select,.guru-bk-page .laporan-form input{width:100%;padding:10px 12px;border:.5px solid #d3d1c7;border-radius:8px;font-size:14px;margin-bottom:12px;box-sizing:border-box;font-family:inherit}
.guru-bk-page .lanjutan-box{margin-top:12px;padding:14px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;display:none}
.guru-bk-page .lanjutan-box.show{display:block}
.guru-bk-page .edit-hint{background:#FFF8EB;border-left:4px solid #EF9F27;padding:10px 14px;border-radius:8px;margin-bottom:14px;font-size:12.5px;color:#854F0B}
@media(max-width:640px){.guru-bk-page .detail-row{flex-direction:column}.guru-bk-page .detail-label{width:auto}.guru-bk-page .info-grid,.guru-bk-page .konfirmasi-box{grid-template-columns:1fr}}
</style>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
<?php
    $siswa = $row->siswa;
    $namaSiswa = $siswa->nama ?? '-';
    $initial = strtoupper(mb_substr($namaSiswa, 0, 1));
    $sk = $row->status_konfirmasi ?? 'Belum Dikonfirmasi';
    if (in_array($sk, ['Tervalidasi', 'Dikonfirmasi'], true)) $skLabel = 'Terkonfirmasi';
    elseif (in_array($sk, ['Belum Dikonfirmasi'], true)) $skLabel = 'Belum Dikonfirmasi';
    else $skLabel = $sk ?: 'Belum Dikonfirmasi';

    $status = $row->status ?? 'Menunggu';
    $belumKonfirmasi = $status === 'Menunggu' || ($status === 'Proses' && $skLabel !== 'Terkonfirmasi');
    $sudahKonfirmasiBelumSelesai = $status === 'Proses' && $skLabel === 'Terkonfirmasi';
    $hasLaporan = !empty($row->laporan_kesimpulan) || !empty($row->laporan_created_at);
    $canEditLaporan = false;
    $sisaEditText = '';
    if ($hasLaporan && $row->laporan_created_at) {
        $jamBerlalu = \Carbon\Carbon::parse($row->laporan_created_at)->diffInMinutes(now()) / 60;
        $canEditLaporan = $jamBerlalu <= 72;
        $sisa = max(0, 72 - $jamBerlalu);
        if ($sisa <= 0) $sisaEditText = 'Waktu edit sudah habis (batas 72 jam)';
        elseif ($sisa < 1) $sisaEditText = 'Sisa ' . round($sisa * 60) . ' menit untuk edit';
        else $sisaEditText = 'Sisa ' . round($sisa) . ' jam untuk edit laporan';
    }

    $tglDiajukan = $row->tanggal ? \Carbon\Carbon::parse($row->tanggal)->format('Y-m-d') : '';
    $jamDiajukan = $row->jam ? substr((string)$row->jam, 0, 5) : '';
    $tglKonf = $row->tanggal_konfirmasi ? \Carbon\Carbon::parse($row->tanggal_konfirmasi)->format('Y-m-d') : ($tglDiajukan ?: now()->format('Y-m-d'));
    $jamKonf = $row->jam_konfirmasi ? substr((string)$row->jam_konfirmasi, 0, 5) : ($jamDiajukan ?: '08:00');
    $jamOptions = [];
    for ($h = 7; $h <= 17; $h++) foreach (['00','30'] as $m) $jamOptions[] = sprintf('%02d:%s', $h, $m);
    $guruNama = $row->guru_bk ?? (session('auth_user')['nama'] ?? 'Guru BK');
    $statusPenangananOptions = \App\Support\StatusPenanganan::LABELS;
    $lanjutanDefault = now()->addDays(7)->format('Y-m-d');
?>

<div class="guru-bk-page">
    <div class="modal show" role="dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3>📋 Detail Konseling &amp; Konfirmasi Jadwal</h3>
                <a href="<?php echo e(route('guru.konseling.index')); ?>" class="close-modal">&times;</a>
            </div>
            <div class="modal-body">
                <div class="detail-hero">
                    <div class="detail-hero-inner">
                        <div class="detail-avatar"><?php echo e($initial); ?></div>
                        <div>
                            <div style="font-size:20px;font-weight:700"><?php echo e($namaSiswa); ?>

                                <?php if(!empty($row->input_manual)): ?><span style="font-size:11px;background:#E1F5EE;color:#0F6E56;padding:2px 10px;border-radius:20px;margin-left:6px">✍️ Walk-in</span><?php endif; ?>
                            </div>
                            <div style="font-size:13px;color:#5F5E5A;margin-top:4px">
                                NIS: <?php echo e($siswa->nis ?? '-'); ?> · Kelas: <?php echo e($row->kelas_siswa ?? $siswa->kelas ?? '-'); ?>

                            </div>
                        </div>
                    </div>
                </div>

                <div class="detail-row">
                    <div class="detail-label">Informasi Konseling:</div>
                    <div class="detail-value">
                        <div class="info-grid">
                            <div><strong>Guru BK:</strong> <?php echo e($guruNama); ?></div>
                            <div><strong>Tanggal diajukan:</strong> <?php echo e($tglDiajukan ?: '–'); ?></div>
                            <div><strong>Jam diajukan:</strong> <?php echo e($jamDiajukan ?: '–'); ?></div>
                            <div><strong>Jenis:</strong> <?php echo e($row->jenis ?: '–'); ?></div>
                            <div><strong>Kategori:</strong> <?php echo e($row->kategori ?: '–'); ?></div>
                            <div><strong>Status:</strong>
                                <span class="status-pill <?php echo e($skLabel==='Terkonfirmasi'?'status-selesai':'status-belum'); ?>"><?php echo e($skLabel); ?></span>
                                <span class="status-pill <?php echo e($status==='Selesai'?'status-selesai':($status==='Dibatalkan'?'status-dibatalkan':'status-proses')); ?>"><?php echo e($status); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if($belumKonfirmasi || $sudahKonfirmasiBelumSelesai): ?>
                <div class="detail-row">
                    <div class="detail-label"><?php echo e($belumKonfirmasi ? 'Konfirmasi Jadwal:' : 'Ubah Jadwal:'); ?></div>
                    <div class="detail-value">
                        <form id="formKonfirmasi" method="POST" action="<?php echo e(route('guru.konseling.konfirmasi', $row->id)); ?>">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="status_konfirmasi" value="Terkonfirmasi">
                            <div class="konfirmasi-box">
                                <div>
                                    <label>Tanggal</label>
                                    <input type="date" name="tanggal_konfirmasi" value="<?php echo e($tglKonf); ?>" required>
                                </div>
                                <div>
                                    <label>Jam</label>
                                    <select name="jam_konfirmasi" required>
                                        <?php $__currentLoopData = $jamOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $j): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <option value="<?php echo e($j); ?>" <?php if($jamKonf===$j): echo 'selected'; endif; ?>><?php echo e($j); ?></option>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </select>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                <?php elseif($skLabel === 'Terkonfirmasi'): ?>
                <div class="detail-row">
                    <div class="detail-label">Jadwal dikonfirmasi:</div>
                    <div class="detail-value"><?php echo e($tglKonf); ?> · <?php echo e($jamKonf); ?></div>
                </div>
                <?php endif; ?>

                <div class="detail-row">
                    <div class="detail-label">Deskripsi Masalah:</div>
                    <div class="detail-value">
                        <div class="detail-deskripsi"><?php echo e($row->deskripsi ?: 'Tidak ada deskripsi'); ?></div>
                    </div>
                </div>

                <?php if($status === 'Dibatalkan'): ?>
                <div class="detail-row">
                    <div class="detail-label">Alasan Pembatalan:</div>
                    <div class="detail-value"><div class="detail-deskripsi" style="background:#FDF6F6;border-color:#F0B8B8"><?php echo e($row->alasan_batal ?? 'Dibatalkan'); ?></div></div>
                </div>
                <?php endif; ?>

                <?php if($hasLaporan): ?>
                <div class="laporan-box">
                    <div style="font-weight:700;color:#0F6E56;margin-bottom:10px">📋 Laporan Hasil Konseling</div>
                    <p><strong>Kesimpulan:</strong> <?php echo e($row->laporan_kesimpulan); ?></p>
                    <p><strong>Rekomendasi:</strong> <?php echo e($row->laporan_rekomendasi); ?></p>
                    <p><strong>Status penanganan:</strong> <?php echo e($row->laporan_status_penanganan); ?></p>
                    <?php if($row->laporan_catatan_tambahan && $row->laporan_catatan_tambahan !== '-'): ?>
                        <p><strong>Catatan:</strong> <?php echo e($row->laporan_catatan_tambahan); ?></p>
                    <?php endif; ?>
                    <p style="font-size:12px;color:#5F5E5A"><?php echo e($sisaEditText); ?></p>
                </div>
                <?php endif; ?>

                
                <?php if($sudahKonfirmasiBelumSelesai || ($status==='Selesai' && $canEditLaporan)): ?>
                <div id="laporanSection" style="margin-top:20px;<?php echo e($sudahKonfirmasiBelumSelesai && !$hasLaporan ? 'display:none' : ''); ?>">
                    <?php if($canEditLaporan): ?>
                        <div class="edit-hint">Mode edit — <?php echo e($sisaEditText); ?></div>
                    <?php endif; ?>
                    <form method="POST" action="<?php echo e(route('guru.konseling.laporan', $row->id)); ?>" class="laporan-form" id="formLaporan">
                        <?php echo csrf_field(); ?>
                        <label>Kesimpulan Konseling *</label>
                        <textarea name="laporan_kesimpulan" rows="3" required><?php echo e(old('laporan_kesimpulan', $row->laporan_kesimpulan)); ?></textarea>
                        <label>Rekomendasi / Tindak Lanjut *</label>
                        <textarea name="laporan_rekomendasi" rows="3" required><?php echo e(old('laporan_rekomendasi', $row->laporan_rekomendasi)); ?></textarea>
                        <label>Status Penanganan *</label>
                        <select name="laporan_status_penanganan" id="statusPenanganan" required>
                            <?php $__currentLoopData = $statusPenangananOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $val => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($val); ?>" <?php if(old('laporan_status_penanganan', $row->laporan_status_penanganan)===$val): echo 'selected'; endif; ?>><?php echo e($label); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                        <label>Catatan tambahan</label>
                        <textarea name="laporan_catatan_tambahan" rows="2"><?php echo e(old('laporan_catatan_tambahan', $row->laporan_catatan_tambahan === '-' ? '' : $row->laporan_catatan_tambahan)); ?></textarea>

                        <?php if(!$hasLaporan): ?>
                        <div class="lanjutan-box" id="lanjutanBox">
                            <label style="display:flex;gap:8px;align-items:center;cursor:pointer;color:#1e40af;font-weight:600">
                                <input type="checkbox" name="buat_lanjutan" value="1" id="chkLanjutan" checked>
                                Buat sesi lanjutan (jadwal monitoring)
                            </label>
                            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-top:10px">
                                <div>
                                    <label>Tanggal lanjutan</label>
                                    <input type="date" name="lanjutan_tanggal" value="<?php echo e($lanjutanDefault); ?>">
                                </div>
                                <div>
                                    <label>Jam</label>
                                    <select name="lanjutan_jam">
                                        <?php $__currentLoopData = $jamOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $j): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <option value="<?php echo e($j); ?>" <?php if($j==='09:00'): echo 'selected'; endif; ?>><?php echo e($j); ?></option>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </select>
                                </div>
                                <div>
                                    <label>Jenis</label>
                                    <select name="lanjutan_jenis">
                                        <option value="Luring">Luring</option>
                                        <option value="Daring" <?php if($row->jenis==='Daring'): echo 'selected'; endif; ?>>Daring</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <button type="submit" class="btn btn-laporan" id="btnSubmitLaporan" style="margin-top:8px">
                            💾 <?php echo e($hasLaporan ? 'Perbarui Laporan' : 'Simpan Laporan & Selesaikan'); ?>

                        </button>
                    </form>
                </div>
                <?php elseif($status==='Selesai' && $hasLaporan && !$canEditLaporan): ?>
                <div class="edit-hint" style="margin-top:16px">🔒 Laporan terkunci — batas edit 72 jam sudah lewat.</div>
                <?php endif; ?>
            </div>

            <div class="modal-footer">
                <?php if($belumKonfirmasi): ?>
                    <button type="submit" form="formKonfirmasi" class="btn btn-konfirmasi">✅ Konfirmasi Jadwal</button>
                    <form method="POST" action="<?php echo e(route('guru.konseling.batal', $row->id)); ?>" style="margin:0" onsubmit="return confirm('Batalkan pengajuan ini? Hanya bisa sebelum dikonfirmasi.')">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="alasan" value="Dibatalkan oleh Guru BK">
                        <button type="submit" class="btn btn-batal">❌ Batalkan</button>
                    </form>
                    <a href="<?php echo e(route('guru.konseling.index')); ?>" class="btn btn-detail">📁 Tutup</a>
                <?php elseif($sudahKonfirmasiBelumSelesai): ?>
                    <button type="submit" form="formKonfirmasi" class="btn btn-konfirmasi">🔄 Ubah Jadwal</button>
                    <button type="button" class="btn btn-laporan" id="btnShowLaporan">📝 Buat Laporan &amp; Selesaikan</button>
                    <a href="<?php echo e(route('guru.konseling.index')); ?>" class="btn btn-detail">📁 Tutup</a>
                    
                <?php elseif($status==='Selesai' && $canEditLaporan): ?>
                    <button type="button" class="btn btn-laporan" id="btnShowLaporan">✏️ Edit Laporan</button>
                    <a href="<?php echo e(route('guru.konseling.index')); ?>" class="btn btn-detail">📁 Tutup</a>
                <?php elseif($status==='Selesai'): ?>
                    <span class="btn btn-locked">🔒 Laporan Terkunci</span>
                    <a href="<?php echo e(route('guru.konseling.index')); ?>" class="btn btn-detail">📁 Tutup</a>
                <?php else: ?>
                    <a href="<?php echo e(route('guru.konseling.index')); ?>" class="btn btn-detail">📁 Tutup</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
(function(){
  var btn = document.getElementById('btnShowLaporan');
  var sec = document.getElementById('laporanSection');
  if (btn && sec) btn.addEventListener('click', function(){ sec.style.display='block'; sec.scrollIntoView({behavior:'smooth',block:'nearest'}); });

  var sel = document.getElementById('statusPenanganan');
  var box = document.getElementById('lanjutanBox');
  var chk = document.getElementById('chkLanjutan');
  var submitBtn = document.getElementById('btnSubmitLaporan');
  function syncLanjutan() {
    if (!sel || !box) return;
    var mon = sel.value === 'Monitoring';
    box.classList.toggle('show', mon);
    if (mon && chk) chk.checked = true;
    if (submitBtn && !<?php echo json_encode($hasLaporan, 15, 512) ?>) {
      submitBtn.textContent = mon
        ? '💾 Simpan Laporan + Buat Sesi Lanjutan'
        : '💾 Simpan Laporan & Selesaikan';
    }
  }
  if (sel) { sel.addEventListener('change', syncLanjutan); syncLanjutan(); }
})();
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\asus\Downloads\bk-full-laravel\resources\views/guru/konseling-detail.blade.php ENDPATH**/ ?>