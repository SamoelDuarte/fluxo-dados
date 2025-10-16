<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsappFlow extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    // 🔹 Um fluxo tem várias etapas
    public function steps()
    {
        return $this->hasMany(WhatsappFlowStep::class, 'flow_id');
    }

    // 🔹 Um fluxo pode ter várias sessões ativas
    public function sessions()
    {
        return $this->hasMany(WhatsappSession::class, 'flow_id');
    }
}
