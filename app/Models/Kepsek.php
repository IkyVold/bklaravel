<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Kepsek extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $table = 'kepsek';

    protected $fillable = [
        'username', 'password', 'nama', 'npsn', 'is_active',
    ];

    protected $hidden = ['password'];

    protected $casts = ['is_active' => 'boolean', 'password_changed_at' => 'datetime', 'password_version' => 'integer'];

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function verifyPassword(string $plain): bool
    {
        if (is_string($this->password) && str_starts_with($this->password, '$2y$')) {
            return password_verify($plain, $this->password);
        }
        if ($this->password === md5($plain)) {
            $this->password = $plain;
            $this->save();
            return true;
        }
        return false;
    }

    /**
     * PERBAIKAN (revisi 26 Agustus 2026, poin 3): setiap kali password
     * benar-benar diganti (baik ganti plaintext baru maupun upgrade hash
     * lama md5->bcrypt di atas), password_changed_at ikut di-stempel di
     * sini — satu-satunya tempat sumber kebenarannya, supaya semua jalur
     * (create, reset oleh Admin lewat AkunController, upgrade legacy
     * hash saat login) otomatis konsisten tanpa perlu diingat manual di
     * tiap controller. Dipakai RoleAuth untuk mendeteksi & memutus
     * session Web lama yang dibuat sebelum password ini berubah.
     */
    public function setPasswordAttribute($value): void
    {
        if ($value === null || $value === '') {
            return;
        }
        if (!str_starts_with((string) $value, '$2y$')) {
            $this->attributes['password'] = password_hash($value, PASSWORD_BCRYPT);
        } else {
            $this->attributes['password'] = $value;
        }
        $this->attributes['password_changed_at'] = now();
        // PERBAIKAN (revisi 26 Agustus 2026, poin 3): counter yang SELALU
        // naik, dipakai RoleAuth untuk membandingkan baseline session ke
        // database. Lihat catatan lengkap di migration
        // add_password_changed_at_to_staff soal kenapa counter dipakai,
        // bukan semata-mata membandingkan timestamp (presisi detik
        // berisiko tabrakan kalau login & reset password terjadi dalam
        // detik yang sama).
        $this->attributes['password_version'] = (int) ($this->attributes['password_version'] ?? 0) + 1;
    }
}
