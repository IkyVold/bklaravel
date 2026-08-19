<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class GuruBk extends Authenticatable
{
    protected $table = 'guru_bk';

    protected $fillable = [
        'username', 'password', 'nama', 'spesialisasi',
        'npsn', 'alamat', 'avatar', 'foto_profile', 'is_active',
    ];

    protected $hidden = ['password'];

    protected $casts = ['is_active' => 'boolean'];

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

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
