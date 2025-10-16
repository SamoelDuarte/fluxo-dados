<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsappMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'contact_id',
        'message_id',
        'direction',
        'content',
        'type',
        'timestamp',
        'raw',
    ];

    protected $casts = [
        'raw' => 'array',
        'timestamp' => 'datetime',
    ];

    // 🔹 Mensagem pertence a um contato
    public function contact()
    {
        return $this->belongsTo(WhatsappContact::class, 'contact_id');
    }
}
