<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsappSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'contact_id',
        'current_step_id',
        'context',
    ];

    protected $casts = [
        'context' => 'array',
    ];

    public function contact()
    {
        return $this->belongsTo(WhatsappContact::class, 'contact_id');
    }


    public function currentStep()
    {
        return $this->belongsTo(WhatsappFlowStep::class, 'current_step_id');
    }
}
