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
        'tanggal', 'jam', 'jenis', 'tipe_jadwal', 'jadwal_rutin_id', 'kategori',
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
}
