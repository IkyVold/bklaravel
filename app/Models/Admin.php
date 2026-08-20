<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Admin extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'admin';

    protected $fillable = [
        'username', 'password', 'nama', 'is_active',
    ];

    protected $hidden = ['password'];

    protected $casts = ['is_active' => 'boolean'];

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
    }
}
