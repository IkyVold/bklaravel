<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotifikasiGuru extends Model
{
    protected $table = 'notifikasi_guru';

    public $timestamps = false;

    protected $fillable = [
        'guru_username',
        'konseling_id',
        'tipe',
        'judul',
        'pesan',
        'is_read',
        'created_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'created_at' => 'datetime',
    ];
}
