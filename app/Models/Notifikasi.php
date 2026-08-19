<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Skema asli (Node backend / dump bk_system):
 * id, siswa_id, konseling_id, tipe, judul, pesan,
 * tanggal_lama, jam_lama, tanggal_baru, jam_baru, is_read, created_at
 */
class Notifikasi extends Model
{
    protected $table = 'notifikasi';

    public $timestamps = false;

    protected $fillable = [
        'siswa_id',
        'konseling_id',
        'tipe',
        'judul',
        'pesan',
        'tanggal_lama',
        'jam_lama',
        'tanggal_baru',
        'jam_baru',
        'is_read',
        'created_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'tanggal_lama' => 'date',
        'tanggal_baru' => 'date',
        'created_at' => 'datetime',
    ];

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }
}
