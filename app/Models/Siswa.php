<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

class Siswa extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $table = 'siswa';

    protected $fillable = [
        'nis', 'nisn', 'nama', 'kelas', 'password', 'jenis_kelamin',
        'tanggal_lahir', 'alamat', 'no_telepon', 'foto_profile',
        'failed_login_attempts', 'locked_until', 'must_change_password',
    ];

    protected $hidden = ['password'];

    protected $casts = [
        'tanggal_lahir' => 'date',
        'locked_until' => 'datetime',
        'failed_login_attempts' => 'integer',
        'must_change_password' => 'boolean',
        'password_changed_at' => 'datetime',
        'password_version' => 'integer',
    ];

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function konseling(): HasMany
    {
        return $this->hasMany(Konseling::class, 'siswa_id');
    }

    /**
     * Notifikasi milik siswa ini. Tabel `notifikasi` tidak punya foreign
     * key siswa_id — penerima diidentifikasi lewat penerima_id (NIS) +
     * penerima_role = 'siswa', sama seperti yang dipakai Api\NotifikasiController.
     */
    public function notifikasi()
    {
        return Notifikasi::untukPenerima((string) $this->nis, 'siswa');
    }

    /**
     * PERBAIKAN (revisi 27 Agustus 2026, poin 3): relasi ini sebelumnya
     * memakai 'nis' sebagai kolom penghubung di kedua sisi ('nis' pada
     * riwayat_kelas, 'nis' pada siswa) — relasi lewat STRING, bukan
     * lewat foreign key sesungguhnya. Sekarang riwayat_kelas punya
     * siswa_id (lihat migration add_siswa_id_to_riwayat_kelas), jadi
     * relasi ini dipindah ke foreign key yang sesungguhnya — riwayat
     * kelas tetap terhubung ke siswa yang sama walau NIS-nya berubah.
     */
    public function riwayatKelas(): HasMany
    {
        return $this->hasMany(RiwayatKelas::class, 'siswa_id', 'id');
    }

    /**
     * Verifikasi password: dukung bcrypt (baru) + MD5 (legacy).
     * Jika MD5 cocok, otomatis upgrade ke bcrypt.
     */
    public function verifyPassword(string $plain): bool
    {
        if (is_string($this->password) && str_starts_with($this->password, '$2y$')) {
            return password_verify($plain, $this->password);
        }
        if ($this->password === md5($plain)) {
            // Upgrade silent ke bcrypt
            $this->password = $plain; // trigger setPasswordAttribute
            $this->save();
            return true;
        }
        return false;
    }

    /**
     * PERBAIKAN (revisi 27 Agustus 2026, poin 2): lihat penjelasan
     * lengkap di migration add_password_version_to_siswa dan di
     * GuruBk::setPasswordAttribute() (pola identik). password_version
     * dinaikkan setiap kali baris ini benar-benar dijalankan dengan
     * value baru — baik saat Admin mereset password siswa, siswa
     * mengganti password sendiri, maupun saat upgrade hash lama
     * md5->bcrypt di verifyPassword() (yang juga memanggil setter ini
     * lewat $this->password = $plain). Dipakai RoleAuth untuk memutus
     * session Web siswa yang sudah tidak sinkron dengan password
     * terbaru di database.
     */
    public function setPasswordAttribute($value): void
    {
        if ($value === null || $value === '') {
            return;
        }
        // Selalu simpan bcrypt untuk password baru
        if (!str_starts_with((string) $value, '$2y$')) {
            $this->attributes['password'] = password_hash($value, PASSWORD_BCRYPT);
        } else {
            $this->attributes['password'] = $value;
        }
        $this->attributes['password_changed_at'] = now();
        $this->attributes['password_version'] = (int) ($this->attributes['password_version'] ?? 0) + 1;
    }
}