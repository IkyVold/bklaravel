<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiwayatKelas extends Model
{
    protected $table = 'riwayat_kelas';

    // PERBAIKAN (revisi 27 Agustus 2026, poin 3): 'nis' dihapus dari
    // fillable — kolom fisiknya sudah dihapus dari tabel (lihat migration
    // add_siswa_id_to_riwayat_kelas). Relasi ke siswa sekarang lewat
    // 'siswa_id' (foreign key sesungguhnya), bukan string NIS.
    protected $fillable = ['siswa_id', 'tahun_ajaran', 'kelas', 'status'];

    // PERBAIKAN: 'nis' tetap ikut disertakan di representasi JSON (lewat
    // accessor getNisAttribute() di bawah) supaya kontrak response API
    // yang sudah ada (Api\RiwayatKelasController) tidak berubah bagi
    // klien yang mengharapkan field 'nis' di tiap baris — walau sumber
    // datanya sekarang diturunkan dari relasi siswa(), bukan kolom fisik.
    protected $appends = ['nis'];

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }

    /**
     * NIS siswa pemilik baris ini, diturunkan lewat relasi siswa_id —
     * BUKAN kolom fisik. Sumber kebenaran NIS tetap satu-satunya:
     * siswa.nis. Kalau NIS siswa berubah suatu hari, nilai ini otomatis
     * ikut berubah tanpa perlu migrasi data riwayat_kelas apa pun.
     *
     * Controller yang sudah eager-load relasi 'siswa' (mis. lewat
     * with('siswa:id,nis') atau setRelation() manual setelah membuat
     * baris baru) tidak akan memicu query tambahan di sini — relasi yang
     * sudah dimuat dipakai ulang seperti biasa oleh Eloquent.
     */
    public function getNisAttribute(): ?string
    {
        return $this->siswa?->nis;
    }
}
