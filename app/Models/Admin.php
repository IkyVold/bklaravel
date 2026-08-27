<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Admin extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $table = 'admin';

    protected $fillable = [
        'username', 'password', 'nama', 'is_active',
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
     * PERBAIKAN (revisi 26 Agustus 2026, poin 3): lihat penjelasan di
     * GuruBk::setPasswordAttribute() — pola dan alasannya identik.
     *
     * PERBAIKAN (revisi 27 Agustus 2026, poin 5 — hasil review dosen
     * penguji): versi sebelumnya di sini HANYA menstempel
     * password_changed_at, tanpa ikut menaikkan password_version —
     * berbeda dari GuruBk dan Kepsek yang sudah benar menaikkan counter
     * ini di setter masing-masing, padahal komentar di atas mengklaim
     * "pola identik". Belum ada endpoint reset password Admin, jadi
     * sejauh ini dampaknya belum langsung kelihatan; tetapi begitu
     * password Admin diganti (mis. lewat Tinker, atau saat fitur reset
     * Admin ditambahkan nanti), password_version yang tidak ikut naik
     * akan membuat RoleAuth (lihat perbandingan password_version dengan
     * baseline session pada middleware itu) TIDAK mendeteksi perubahan
     * tsb, sehingga session Web Admin lama tidak otomatis diputus —
     * padahal mekanisme ini sudah berfungsi benar untuk Guru BK dan
     * Kepsek. Baris di bawah menyamakan setter Admin dengan keduanya.
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
        $this->attributes['password_version'] = (int) ($this->attributes['password_version'] ?? 0) + 1;
    }
}
