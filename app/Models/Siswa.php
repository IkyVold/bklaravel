<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

class Siswa extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'siswa';

    protected $fillable = [
        'nis', 'nama', 'kelas', 'password', 'jenis_kelamin',
        'tanggal_lahir', 'alamat', 'no_telepon', 'foto_profile',
        'failed_login_attempts', 'locked_until',
    ];

    protected $hidden = ['password'];

    protected $casts = [
        'tanggal_lahir' => 'date',
        'locked_until' => 'datetime',
        'failed_login_attempts' => 'integer',
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

    public function riwayatKelas(): HasMany
    {
        return $this->hasMany(RiwayatKelas::class, 'nis', 'nis');
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
    }
}
