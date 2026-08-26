<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Konseling extends Model
{
    use HasFactory;

    protected $table = 'konseling';

    public $timestamps = false;

    protected $fillable = [
        'siswa_id', 'guru_id', 'guru_bk', 'pengajuan_sebelumnya_id', 'alasan_batal',
        'tanggal', 'jam', 'durasi_menit', 'jenis', 'tipe_jadwal', 'jadwal_rutin_id', 'kategori',
        'deskripsi', 'kelas_siswa', 'status', 'status_konfirmasi',
        'tanggal_konfirmasi', 'jam_konfirmasi', 'laporan',
        'laporan_tanggal', 'laporan_waktu', 'laporan_dibuat_oleh',
        'laporan_kesimpulan', 'laporan_rekomendasi',
        'laporan_status_penanganan', 'laporan_catatan_tambahan',
        'laporan_created_at', 'input_manual', 'catatan_walkin', 'chat_session_id', 'created_at',
    ];

    protected $casts = [
        // Format eksplisit 'Y-m-d' PENTING: cast 'date' polos (tanpa format)
        // disimpan Eloquent sebagai "Y-m-d H:i:s" (dengan jam 00:00:00), yang
        // bisa membuat perbandingan tanggal berbasis string di tempat lain
        // gagal secara diam-diam. Lihat juga ScheduleService::hasConflict().
        'tanggal' => 'date:Y-m-d',
        'tanggal_konfirmasi' => 'date:Y-m-d',
        'laporan_tanggal' => 'date:Y-m-d',
        'laporan_created_at' => 'datetime',
        'created_at' => 'datetime',
        'input_manual' => 'boolean',
        'durasi_menit' => 'integer',
    ];

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }

    public function jadwalRutin(): BelongsTo
    {
        return $this->belongsTo(JadwalRutin::class, 'jadwal_rutin_id');
    }

    public function isRutin(): bool
    {
        return strcasecmp((string) ($this->tipe_jadwal ?? ''), 'Rutin') === 0;
    }

    public function isDaring(): bool
    {
        return strcasecmp((string) ($this->jenis ?? ''), 'Daring') === 0;
    }

    /**
     * Daftar nilai status_konfirmasi yang berarti "sudah dikonfirmasi Guru
     * BK". Historisnya web & API tidak konsisten memakai satu nilai yang
     * sama untuk arti ini ('Dikonfirmasi' di jalur API/konfirmasi baru,
     * 'Terkonfirmasi'/'Tervalidasi' di jalur web/data lama — lihat
     * Web/KonselingController@laporan yang memakai daftar yang sama).
     * Dipusatkan di sini supaya pengecekan "sudah dikonfirmasi" konsisten
     * di seluruh sistem.
     */
    public const STATUS_KONFIRMASI_TERKONFIRMASI = ['Dikonfirmasi', 'Terkonfirmasi', 'Tervalidasi'];

    public function isKonfirmasi(): bool
    {
        return in_array((string) ($this->status_konfirmasi ?? ''), self::STATUS_KONFIRMASI_TERKONFIRMASI, true);
    }

    public function untukMonitoringKepsek(): array
    {
        return [
            'id' => $this->id,
            'siswa' => $this->siswa ? [
                'id' => $this->siswa->id,
                'nis' => $this->siswa->nis,
                'nama' => $this->siswa->nama,
                'kelas' => $this->siswa->kelas,
            ] : null,
            'kelas_siswa' => $this->kelas_siswa,
            'guru_id' => $this->guru_id,
            'guru_bk' => $this->guru_bk,
            'tanggal' => $this->tanggal,
            'jam' => $this->jam,
            'durasi_menit' => $this->durasi_menit,
            'jenis' => $this->jenis,
            'tipe_jadwal' => $this->tipe_jadwal,
            'kategori' => $this->kategori,
            'status' => $this->status,
            'status_konfirmasi' => $this->status_konfirmasi,
            'tanggal_konfirmasi' => $this->tanggal_konfirmasi,
            'jam_konfirmasi' => $this->jam_konfirmasi,
            'alasan_batal' => $this->alasan_batal,
            'laporan_status_penanganan' => $this->laporan_status_penanganan,
            'laporan_dibuat_oleh' => $this->laporan_dibuat_oleh,
            'laporan_tanggal' => $this->laporan_tanggal,
            'laporan_waktu' => $this->laporan_waktu,
            'laporan_created_at' => $this->laporan_created_at,
            'ada_laporan' => !empty($this->laporan_created_at) || !empty($this->laporan_kesimpulan),
            'created_at' => $this->created_at,
        ];
    }
}
