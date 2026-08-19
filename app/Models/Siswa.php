<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Siswa extends Authenticatable
{
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

    public function notifikasi(): HasMany
    {
        return $this->hasMany(Notifikasi::class, 'siswa_id');
    }

    public function riwayatKelas(): HasMany
    {
        return $this->hasMany(RiwayatKelas::class, 'nis', 'nis');
    }

    /** Password di DB = MD5 (sistem lama) */
    public function verifyPassword(string $plain): bool
    {
        if ($this->password === md5($plain)) {
            return true;
        }
        if (is_string($this->password) && str_starts_with($this->password, '$2y$')) {
            return password_verify($plain, $this->password);
        }
        return false;
    }

    public function setPasswordAttribute($value): void
    {
        if ($value === null || $value === '') {
            return;
        }
        if (!str_starts_with((string) $value, '$2y$')) {
            $this->attributes['password'] = md5($value);
        } else {
            $this->attributes['password'] = $value;
        }
    }
}
