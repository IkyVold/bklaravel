<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu-satunya skema tabel `notifikasi` — sesuai migration
 * 2026_01_01_000001_create_bk_tables.php dan dipakai konsisten oleh Web
 * maupun API:
 *   id, penerima_id, penerima_role, judul, pesan, tipe, data (json),
 *   dibaca, created_at
 *
 * `penerima_id` bersifat generik (bukan foreign key ke satu tabel saja)
 * karena penerima notifikasi bisa siswa (NIS) maupun staff (username),
 * dibedakan lewat `penerima_role`. Skema lama Node/dump (siswa_id,
 * konseling_id, is_read) TIDAK dipakai lagi — jangan tambahkan kolom itu
 * kembali di controller mana pun.
 */
class Notifikasi extends Model
{
    protected $table = 'notifikasi';

    public $timestamps = false;

    protected $fillable = [
        'penerima_id',
        'penerima_role',
        'judul',
        'pesan',
        'tipe',
        'data',
        'dibaca',
        'created_at',
    ];

    protected $casts = [
        'data' => 'array',
        'dibaca' => 'boolean',
        'created_at' => 'datetime',
    ];

    /**
     * konseling_id disimpan di dalam kolom `data` (json), bukan kolom
     * tersendiri. Accessor ini supaya kode pemanggil (controller & view)
     * tetap bisa memakai $notifikasi->konseling_id seperti sebelumnya
     * tanpa perlu tahu detail penyimpanannya.
     */
    public function getKonselingIdAttribute(): ?int
    {
        $id = $this->data['konseling_id'] ?? null;
        return $id !== null ? (int) $id : null;
    }

    public function scopeUntukPenerima(Builder $query, string $penerimaId, string $penerimaRole): Builder
    {
        return $query->where('penerima_id', $penerimaId)->where('penerima_role', $penerimaRole);
    }

    public function scopeBelumDibaca(Builder $query): Builder
    {
        return $query->where('dibaca', false);
    }

    /**
     * Helper agar seluruh pemanggil (web & API) membentuk payload dengan
     * struktur yang sama persis, termasuk cara konseling_id dititipkan di
     * kolom `data`.
     */
    public static function buatUntuk(
        string $penerimaId,
        string $penerimaRole,
        string $judul,
        string $pesan,
        ?string $tipe = null,
        ?int $konselingId = null,
    ): self {
        return self::create([
            'penerima_id' => $penerimaId,
            'penerima_role' => $penerimaRole,
            'judul' => $judul,
            'pesan' => $pesan,
            'tipe' => $tipe,
            'data' => $konselingId !== null ? ['konseling_id' => $konselingId] : null,
            'dibaca' => false,
            'created_at' => now(),
        ]);
    }
}
