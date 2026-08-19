<?php $__env->startSection('title', 'Dashboard Guru BK'); ?>
<?php $__env->startSection('main_class', ''); ?>
<?php $__env->startPush('styles'); ?>
<link rel="stylesheet" href="<?php echo e(asset('css/guruBk.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('css/guru-shell.css')); ?>">
<style>
.guru-bk-page .stats-row {
  display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px;
}
@media (max-width: 1000px) { .guru-bk-page .stats-row { grid-template-columns: repeat(2, 1fr); } }
.guru-bk-page .stat-card {
  background: #fff; border: 1px solid #e8e6e0; border-radius: 14px; padding: 18px 16px;
  display: flex; align-items: center; gap: 14px; box-shadow: 0 1px 3px rgba(0,0,0,.04);
  transition: transform .15s, box-shadow .15s;
}
.guru-bk-page .stat-card:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0,0,0,.06); }
.guru-bk-page .stat-icon {
  width: 48px; height: 48px; border-radius: 12px;
  display: flex; align-items: center; justify-content: center; font-size: 22px; flex-shrink: 0;
}
.guru-bk-page .stat-icon.blue { background: #EEEDFE; }
.guru-bk-page .stat-icon.amber { background: #FFF1E7; }
.guru-bk-page .stat-icon.green { background: #E1F5EE; }
.guru-bk-page .stat-icon.teal { background: #E1F5EE; }
.guru-bk-page .stat-label { font-size: 12.5px; color: #5F5E5A; font-weight: 500; }
.guru-bk-page .stat-value { font-size: 26px; font-weight: 700; color: #1a1a18; margin-top: 2px; line-height: 1.1; }

.guru-bk-page .panel {
  background: #fff; border: 1px solid #e8e6e0; border-radius: 16px; overflow: hidden;
  box-shadow: 0 1px 3px rgba(0,0,0,.04);
}
.guru-bk-page .panel-head {
  display: flex; justify-content: space-between; align-items: center;
  padding: 16px 20px; border-bottom: 1px solid #f1efe8; flex-wrap: wrap; gap: 10px;
}
.guru-bk-page .panel-head h3 { margin: 0; font-size: 15.5px; font-weight: 700; color: #1a1a18; }
.guru-bk-page .panel-filters input {
  padding: 8px 14px; border: 1px solid #d3d1c7; border-radius: 20px; font-size: 13px; min-width: 200px;
}

.guru-bk-page .table-wrap { overflow-x: auto; }
.guru-bk-page table { width: 100%; border-collapse: collapse; font-size: 13px; }
.guru-bk-page th {
  padding: 12px 14px; text-align: left; font-weight: 600; color: #5F5E5A;
  background: #faf9f7; border-bottom: 1px solid #e8e6e0; white-space: nowrap; font-size: 11.5px;
  letter-spacing: .02em; text-transform: uppercase;
}
.guru-bk-page td {
  padding: 14px; border-bottom: 1px solid #f1efe8; vertical-align: middle; color: #444;
}
.guru-bk-page tbody tr:hover { background: #faf9f7; }

.guru-bk-page .siswa-cell { display: flex; align-items: center; gap: 10px; }
.guru-bk-page .siswa-av {
  width: 32px; height: 32px; border-radius: 50%;
  background: linear-gradient(135deg, #534AB7, #7F77DD); color: #fff;
  display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; flex-shrink: 0;
}
.guru-bk-page .badge-kelas {
  display: inline-block; padding: 3px 10px; border-radius: 12px;
  background: #EEEDFE; color: #3C3489; font-size: 12px; font-weight: 600;
}
.guru-bk-page .badge-walkin {
  display: inline-block; margin-top: 3px; padding: 2px 8px; border-radius: 20px;
  background: #E1F5EE; color: #0F6E56; font-size: 10px; font-weight: 700;
}
.guru-bk-page .badge-lanjutan {
  font-size: 11px; color: #1d4ed8; margin-top: 2px;
}
.guru-bk-page .badge-jenis {
  display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 600;
}
.guru-bk-page .badge-daring { background: #EEEDFE; color: #3C3489; }
.guru-bk-page .badge-luring { background: #E1F5EE; color: #0F6E56; }

.guru-bk-page .status-badge {
  display: inline-block; padding: 5px 12px; border-radius: 30px;
  font-size: 11.5px; font-weight: 700; letter-spacing: .01em; white-space: nowrap;
}
.guru-bk-page .status-proses { background: #FFF1E7; color: #993C1D; }
.guru-bk-page .status-selesai { background: #E1F5EE; color: #0F6E56; }
.guru-bk-page .status-dibatalkan { background: #FCEBEB; color: #A32D2D; }
.guru-bk-page .status-belum { background: #F1EFE8; color: #5F5E5A; }

.guru-bk-page .aksi-wrap { display: flex; flex-wrap: wrap; gap: 4px; min-width: 140px; }
.guru-bk-page .btn-aksi {
  display: inline-flex; align-items: center; gap: 4px;
  padding: 5px 10px; border-radius: 8px; font-size: 11.5px; font-weight: 600;
  text-decoration: none; border: none; cursor: pointer; font-family: inherit;
}
.guru-bk-page .btn-aksi-detail { background: #E6F1FB; color: #185FA5; }
.guru-bk-page .btn-aksi-konfirmasi { background: #EEEDFE; color: #3C3489; }
.guru-bk-page .btn-aksi-laporan { background: #E1F5EE; color: #0F6E56; }
.guru-bk-page .btn-aksi-chat { background: #EEEDFE; color: #534AB7; }
.guru-bk-page .btn-aksi-batal { background: #FCEBEB; color: #A32D2D; }
.guru-bk-page .deskripsi-cell {
  max-width: 220px; font-size: 12.5px; color: #5F5E5A; line-height: 1.4;
}

.guru-bk-page .empty-state { text-align: center; padding: 60px 20px; color: #5F5E5A; }
.guru-bk-page .empty-state .empty-icon { font-size: 64px; opacity: .45; margin-bottom: 16px; }
.guru-bk-page .empty-hint {
  margin-top: 16px; padding: 12px 18px; background: #EEEDFE; border-radius: 12px;
  display: inline-block; color: #3C3489; font-size: 13.5px;
}
.guru-bk-page .walkin-fab {
  position: fixed; bottom: 24px; right: 24px; z-index: 100;
  background: #534AB7; color: #fff; border: none; border-radius: 14px;
  padding: 14px 20px; font-weight: 700; font-size: 14px; cursor: pointer;
  box-shadow: 0 8px 24px rgba(83,74,183,.35); font-family: inherit;
}
.guru-bk-page .walkin-fab:hover { background: #3C3489; }
</style>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
<?php
    $user = session('auth_user', []);
    $namaGuru = $user['nama'] ?? 'Guru BK';
    $stats = $stats ?? ['all'=>0,'proses'=>0,'terkonfirmasi'=>0,'selesai'=>0,'dibatalkan'=>0];
    $prosesCount = $prosesCount ?? 0;
    $currentFilter = $currentFilter ?? 'all';
    $q = $q ?? '';
    $filterTitles = [
        'all' => 'Semua Konseling',
        'proses' => 'Menunggu Konfirmasi',
        'terkonfirmasi' => 'Sudah Dikonfirmasi',
        'selesai' => 'Selesai',
        'dibatalkan' => 'Dibatalkan',
    ];
?>
<div class="guru-bk-page">
    <?php echo $__env->make('partials.guru-header', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <div class="container">
        <?php echo $__env->make('partials.guru-sidebar', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <div class="guru-main">
            <div class="content-header">
                <h2>📋 Monitoring &amp; Konfirmasi Konseling</h2>
                <p>Kelola, konfirmasi jadwal, dan pantau semua permintaan konseling dari siswa untuk <strong><?php echo e($namaGuru); ?></strong></p>
            </div>

            <div class="content-tabs tab-nav">
                <a href="<?php echo e(route('guru.konseling.index', ['filter' => $currentFilter])); ?>" class="tab-btn active">📋 Konseling</a>
                <a href="<?php echo e(route('guru.siswa.index')); ?>" class="tab-btn">👥 Daftar Siswa</a>
            </div>

            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-icon blue">📋</div>
                    <div>
                        <div class="stat-label">Total Konseling</div>
                        <div class="stat-value"><?php echo e($stats['all']); ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon amber">⏳</div>
                    <div>
                        <div class="stat-label">Menunggu Konfirmasi</div>
                        <div class="stat-value"><?php echo e($stats['proses']); ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green">✅</div>
                    <div>
                        <div class="stat-label">Sudah Dikonfirmasi</div>
                        <div class="stat-value"><?php echo e($stats['terkonfirmasi']); ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon teal">✨</div>
                    <div>
                        <div class="stat-label">Selesai</div>
                        <div class="stat-value"><?php echo e($stats['selesai']); ?></div>
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="panel-head">
                    <h3><?php echo e($filterTitles[$currentFilter] ?? 'Konseling'); ?></h3>
                    <form method="GET" action="<?php echo e(route('guru.konseling.index')); ?>" class="panel-filters">
                        <input type="hidden" name="filter" value="<?php echo e($currentFilter); ?>">
                        <input type="search" name="q" value="<?php echo e($q); ?>" placeholder="🔍 Cari nama siswa...">
                    </form>

                        <a href="<?php echo e(route('guru.cetak', ['filter' => $currentFilter ?? 'all', 'q' => $q ?? ''])); ?>"
                           target="_blank"
                           class="btn-cetak"
                           style="display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:8px;background:#334155;color:#fff;text-decoration:none;font-weight:600;font-size:13px;margin-left:8px">
                            🖨️ Cetak Laporan PDF
                        </a>
                </div>
                <div class="table-wrap">
                    <?php if($rows->isEmpty()): ?>
                        <div class="empty-state">
                            <div class="empty-icon">📋</div>
                            <div style="font-size:20px;font-weight:700;color:#1a1a18;margin-bottom:8px">Tidak ada data konseling</div>
                            <div style="font-size:14px">Tidak ada data pada filter ini</div>
                            <div class="empty-hint">⏳ Data muncul ketika siswa memilih Anda sebagai Guru BK</div>
                        </div>
                    <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Siswa</th>
                                <th>NIS</th>
                                <th>Kelas</th>
                                <th>Tanggal Diajukan</th>
                                <th>Jam</th>
                                <th>Tgl Konfirmasi</th>
                                <th>Jam Konfirmasi</th>
                                <th>Jenis</th>
                                <th>Kategori</th>
                                <th>Deskripsi Masalah</th>
                                <th>Status Konfirmasi</th>
                                <th>Status</th>
                                <th>Laporan</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <?php
                                    $siswa = $row->siswa;
                                    $namaSiswa = $siswa->nama ?? '-';
                                    $sk = $row->status_konfirmasi ?? 'Belum Dikonfirmasi';
                                    if (in_array($sk, ['Tervalidasi','Dikonfirmasi','Terkonfirmasi'], true)) {
                                        $skLabel = 'Terkonfirmasi';
                                    } elseif (in_array($sk, ['Belum Divalidasi'], true)) {
                                        $skLabel = 'Belum Dikonfirmasi';
                                    } else {
                                        $skLabel = $sk ?: 'Belum Dikonfirmasi';
                                    }
                                    $status = $row->status ?? 'Proses';
                                    $belum = $skLabel !== 'Terkonfirmasi' && $status === 'Proses';
                                    $sudahProses = $skLabel === 'Terkonfirmasi' && $status === 'Proses';
                                    $isOnline = ($row->jenis === 'Daring') && ($skLabel === 'Terkonfirmasi') && ($status !== 'Dibatalkan');
                                    $hasLaporan = !empty($row->laporan_kesimpulan) || !empty($row->laporan_created_at);
                                    $av = strtoupper(mb_substr($namaSiswa, 0, 1));
                                ?>
                                <tr>
                                    <td style="font-weight:600"><?php echo e($i + 1); ?></td>
                                    <td>
                                        <div class="siswa-cell">
                                            <div class="siswa-av"><?php echo e($av); ?></div>
                                            <div>
                                                <strong><?php echo e($namaSiswa); ?></strong>
                                                <?php if(!empty($row->pengajuan_sebelumnya_id)): ?>
                                                    <div class="badge-lanjutan">🔗 Lanjutan #<?php echo e($row->pengajuan_sebelumnya_id); ?></div>
                                                <?php endif; ?>
                                                <?php if(!empty($row->input_manual)): ?>
                                                    <br><span class="badge-walkin">✍️ Walk-in</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="font-family:monospace;font-weight:600"><?php echo e($siswa->nis ?? '-'); ?></td>
                                    <td><span class="badge-kelas"><?php echo e($row->kelas_siswa ?? $siswa->kelas ?? '-'); ?></span></td>
                                    <td><?php echo e($row->tanggal ? \Carbon\Carbon::parse($row->tanggal)->locale('id')->translatedFormat('d M Y') : '–'); ?></td>
                                    <td><?php echo e($row->jam ? substr((string)$row->jam, 0, 5) : '–'); ?></td>
                                    <td><?php echo e($row->tanggal_konfirmasi ? \Carbon\Carbon::parse($row->tanggal_konfirmasi)->locale('id')->translatedFormat('d M Y') : '–'); ?></td>
                                    <td><?php echo e($row->jam_konfirmasi ? substr((string)$row->jam_konfirmasi, 0, 5) : '–'); ?></td>
                                    <td>
                                        <span class="badge-jenis <?php echo e($row->jenis === 'Daring' ? 'badge-daring' : 'badge-luring'); ?>">
                                            <?php echo e($row->jenis === 'Daring' ? '🌐 Daring' : '🏫 ' . ($row->jenis ?: 'Luring')); ?>

                                        </span>
                                    </td>
                                    <td><?php echo e($row->kategori ?: '–'); ?></td>
                                    <td class="deskripsi-cell" title="<?php echo e($row->deskripsi); ?>"><?php echo e(\Illuminate\Support\Str::limit($row->deskripsi ?: 'Tidak ada deskripsi', 80)); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo e($skLabel === 'Terkonfirmasi' ? 'status-selesai' : 'status-belum'); ?>">
                                            <?php echo e($skLabel); ?>

                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo e($status === 'Selesai' ? 'status-selesai' : ($status === 'Dibatalkan' ? 'status-dibatalkan' : 'status-proses')); ?>">
                                            <?php echo e($status); ?>

                                        </span>
                                    </td>
                                    <td>
                                        <?php if($hasLaporan): ?>
                                            <span class="status-badge status-selesai">📋 Ada</span>
                                        <?php else: ?>
                                            <span style="color:#b4b2a9;font-size:12px">–</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="aksi-wrap">
                                            <a href="<?php echo e(route('guru.konseling.show', $row->id)); ?>" class="btn-aksi btn-aksi-detail">📁 Detail</a>
                                            <?php if($belum): ?>
                                                <a href="<?php echo e(route('guru.konseling.show', $row->id)); ?>" class="btn-aksi btn-aksi-konfirmasi">✅ Konfirmasi</a>
                                            <?php endif; ?>
                                            <?php if($sudahProses): ?>
                                                <a href="<?php echo e(route('guru.konseling.show', $row->id)); ?>#laporan" class="btn-aksi btn-aksi-laporan">📝 Laporan</a>
                                            <?php endif; ?>
                                            <?php if($isOnline): ?>
                                                <a href="<?php echo e(route('guru.chat', $row->id)); ?>" class="btn-aksi btn-aksi-chat">💬 Chat</a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <button type="button" class="walkin-fab" id="btnOpenWalkin">+ Walk-in</button>
</div>


<div class="guru-modal-overlay" id="modalWalkin" style="display:none;position:fixed;inset:0;z-index:4000;background:rgba(16,24,40,.55);align-items:center;justify-content:center;padding:16px">
  <div style="background:#fff;border-radius:16px;width:100%;max-width:560px;max-height:90vh;overflow-y:auto;box-shadow:0 24px 60px rgba(16,24,40,.25)">
    <div style="background:linear-gradient(120deg,#26215C,#3C3489);color:#fff;padding:16px 20px;border-radius:16px 16px 0 0;display:flex;justify-content:space-between;align-items:center">
      <h3 style="margin:0;font-size:16px">✍️ Catat Konseling Walk-in</h3>
      <button type="button" id="closeWalkin" style="background:rgba(255,255,255,.15);border:none;color:#fff;width:32px;height:32px;border-radius:50%;cursor:pointer;font-size:18px">&times;</button>
    </div>
    <form method="POST" action="<?php echo e(route('guru.konseling.walkin.store')); ?>" style="padding:20px" id="formWalkin">
      <?php echo csrf_field(); ?>
      <label style="display:block;font-size:12px;font-weight:700;color:#5F5E5A;margin-bottom:4px">NIS Siswa *</label>
      <input type="text" name="nis" id="walkinNis" required pattern="[0-9]+" placeholder="Masukkan NIS" style="width:100%;padding:10px 12px;border:.5px solid #d3d1c7;border-radius:8px;margin-bottom:10px;box-sizing:border-box">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px">
        <div>
          <label style="display:block;font-size:12px;font-weight:700;color:#5F5E5A;margin-bottom:4px">Nama</label>
          <input type="text" id="walkinNama" readonly style="width:100%;padding:10px 12px;border:.5px solid #d3d1c7;border-radius:8px;background:#f5f5f0;box-sizing:border-box">
        </div>
        <div>
          <label style="display:block;font-size:12px;font-weight:700;color:#5F5E5A;margin-bottom:4px">Kelas</label>
          <input type="text" id="walkinKelas" readonly style="width:100%;padding:10px 12px;border:.5px solid #d3d1c7;border-radius:8px;background:#f5f5f0;box-sizing:border-box">
        </div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <div>
          <label style="display:block;font-size:12px;font-weight:700;color:#5F5E5A;margin-bottom:4px">Tanggal *</label>
          <input type="date" name="tanggal" value="<?php echo e(date('Y-m-d')); ?>" required style="width:100%;padding:10px 12px;border:.5px solid #d3d1c7;border-radius:8px;margin-bottom:10px;box-sizing:border-box">
        </div>
        <div>
          <label style="display:block;font-size:12px;font-weight:700;color:#5F5E5A;margin-bottom:4px">Jam *</label>
          <select name="jam" required style="width:100%;padding:10px 12px;border:.5px solid #d3d1c7;border-radius:8px;margin-bottom:10px;box-sizing:border-box">
            <?php for($h=7;$h<=17;$h++): ?>
              <?php $__currentLoopData = ['00','30']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $m): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php $j = sprintf('%02d:%s',$h,$m); ?>
                <option value="<?php echo e($j); ?>" <?php if($j==='09:00'): echo 'selected'; endif; ?>><?php echo e($j); ?></option>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            <?php endfor; ?>
          </select>
        </div>
      </div>
      <label style="display:block;font-size:12px;font-weight:700;color:#5F5E5A;margin-bottom:4px">Jenis *</label>
      <select name="jenis" required style="width:100%;padding:10px 12px;border:.5px solid #d3d1c7;border-radius:8px;margin-bottom:10px;box-sizing:border-box">
        <option value="Tatap Muka">🏫 Tatap Muka</option>
        <option value="Daring">🌐 Daring</option>
      </select>
      <label style="display:block;font-size:12px;font-weight:700;color:#5F5E5A;margin-bottom:4px">Kategori Masalah *</label>
      <select name="kategori" required style="width:100%;padding:10px 12px;border:.5px solid #d3d1c7;border-radius:8px;margin-bottom:10px;box-sizing:border-box">
        <option value="">Pilih kategori</option>
        <?php $__currentLoopData = ['Akademik','Sosial','Pribadi','Karir','Bullying','Keluarga','Lainnya']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <option value="<?php echo e($k); ?>"><?php echo e($k); ?></option>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </select>
      <label style="display:block;font-size:12px;font-weight:700;color:#5F5E5A;margin-bottom:4px">Deskripsi / Kronologi *</label>
      <textarea name="deskripsi" rows="4" required style="width:100%;padding:10px 12px;border:.5px solid #d3d1c7;border-radius:8px;margin-bottom:10px;box-sizing:border-box;font-family:inherit"></textarea>
      <label style="display:block;font-size:12px;font-weight:700;color:#5F5E5A;margin-bottom:4px">Catatan tambahan</label>
      <textarea name="catatan_walkin" rows="2" style="width:100%;padding:10px 12px;border:.5px solid #d3d1c7;border-radius:8px;margin-bottom:10px;box-sizing:border-box;font-family:inherit"></textarea>
      <label style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600;margin-bottom:14px;cursor:pointer">
        <input type="checkbox" name="langsung_laporan" value="1"> Lanjutkan langsung ke form Laporan setelah simpan
      </label>
      <button type="submit" style="width:100%;padding:12px;border:none;border-radius:10px;background:#16a34a;color:#fff;font-weight:700;cursor:pointer">💾 Simpan Walk-in</button>
    </form>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
(function(){
  var modal = document.getElementById('modalWalkin');
  var openBtn = document.getElementById('btnOpenWalkin');
  var closeBtn = document.getElementById('closeWalkin');
  function open(){ if(modal){ modal.style.display='flex'; } }
  function close(){ if(modal){ modal.style.display='none'; } }
  if(openBtn) openBtn.addEventListener('click', open);
  if(closeBtn) closeBtn.addEventListener('click', close);
  if(modal) modal.addEventListener('click', function(e){ if(e.target===modal) close(); });
  <?php if(request('open')==='walkin'): ?> open(); <?php endif; ?>
  var nis = document.getElementById('walkinNis');
  if(nis){
    nis.addEventListener('blur', function(){
      var v = nis.value.trim();
      if(!v){ document.getElementById('walkinNama').value=''; document.getElementById('walkinKelas').value=''; return; }
      document.getElementById('walkinNama').value = 'Mencari...';
      fetch(<?php echo json_encode(url('/guru/siswa/lookup'), 15, 512) ?> + '/' + encodeURIComponent(v), {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
      }).then(function(r){ return r.json().then(function(j){ return {ok:r.ok,j:j}; }); })
        .then(function(res){
          if(res.ok && res.j.success){
            document.getElementById('walkinNama').value = res.j.nama || '';
            document.getElementById('walkinKelas').value = res.j.kelas || '';
          } else {
            document.getElementById('walkinNama').value = '';
            document.getElementById('walkinKelas').value = '';
            alert('Siswa dengan NIS "'+v+'" tidak ditemukan.');
          }
        }).catch(function(){
          document.getElementById('walkinNama').value = '';
          document.getElementById('walkinKelas').value = '';
        });
    });
  }
})();
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\asus\Downloads\bk-full-laravel\resources\views/guru/konseling-index.blade.php ENDPATH**/ ?>