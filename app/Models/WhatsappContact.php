<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsappContact extends Model
{
    use HasFactory;

    protected $fillable = [
        'wa_id',
        'name',
        'last_message',
        'last_message_at',
    ];

    protected $dates = ['last_message_at'];

    // 🔹 Um contato pode ter várias mensagens
    public function messages()
    {
        return $this->hasMany(WhatsappMessage::class, 'contact_id');
    }

    // 🔹 Um contato pode ter várias sessões de fluxo
    public function sessions()
    {
        return $this->hasMany(WhatsappSession::class, 'contact_id');
    }

    public function startFlow($flowId)
    {
        $this->session()->updateOrCreate(
            ['contact_id' => $this->id],
            ['flow_id' => $flowId, 'current_step_id' => null, 'context' => []]
        );
    }

    public function advanceStep()
    {
        if ($this->session && $this->session->currentStep) {
            $next = WhatsappFlowStep::where('flow_id', $this->session->flow_id)
                ->where('step_number', '>', $this->session->currentStep->step_number)
                ->orderBy('step_number')
                ->first();

            if ($next) {
                $this->session->update(['current_step_id' => $next->id]);
            }
        }
    }

}
