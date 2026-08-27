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
    }
}
