<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsappFlowStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'prompt',
        'expected_input',
        'next_step_condition',
    ];


     public function sessions()
    {
        return $this->hasMany(WhatsappSession::class, 'current_step_id');
    }
   
}
