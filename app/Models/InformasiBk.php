<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InformasiBk extends Model
{
    protected $table = 'informasi_bk';

    protected $fillable = ['judul', 'kategori', 'isi', 'guru_bk', 'guru_id'];
}
