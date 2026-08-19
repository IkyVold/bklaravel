<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model
{
    protected $table = 'chat_messages';

    public $timestamps = false;

    protected $fillable = [
        'session_id', 'sender_id', 'sender_name', 'sender_type', 'message', 'created_at',
    ];

    protected $casts = ['created_at' => 'datetime'];
}
