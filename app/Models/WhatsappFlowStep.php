<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsappFlowStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'flow_id',
        'step_number',
        'prompt',
        'expected_input',
        'next_step_condition',
    ];

    public function flow()
    {
        return $this->belongsTo(WhatsappFlow::class, 'flow_id');
    }
}
