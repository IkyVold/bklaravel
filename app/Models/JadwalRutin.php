<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JadwalRutin extends Model
{
    protected $table = 'jadwal_rutin';

    protected $fillable = [
        'guru_id', 'hari', 'jam_mulai', 'jam_selesai',
        'jenis', 'keterangan', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'hari' => 'integer',
    ];

    public const HARI = [
        1 => 'Senin',
        2 => 'Selasa',
        3 => 'Rabu',
        4 => 'Kamis',
        5 => 'Jumat',
        6 => 'Sabtu',
        7 => 'Minggu',
    ];

    public function guru(): BelongsTo
    {
        return $this->belongsTo(GuruBk::class, 'guru_id');
    }

    public function getHariLabelAttribute(): string
    {
        return self::HARI[$this->hari] ?? (string) $this->hari;
    }

    public function getJamLabelAttribute(): string
    {
        $mulai = substr((string) $this->jam_mulai, 0, 5);
        $selesai = $this->jam_selesai ? substr((string) $this->jam_selesai, 0, 5) : null;
        return $selesai ? "{$mulai} – {$selesai}" : $mulai;
    }
}
