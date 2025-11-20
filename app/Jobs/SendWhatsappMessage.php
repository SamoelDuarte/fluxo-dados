<?php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SendWhatsappMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $to;
    public $body;
    public $overridePhoneId;

    public function __construct(string $to, string $body, ?string $overridePhoneId = null)
    {
        $this->to = $to;
        $this->body = $body;
        $this->overridePhoneId = $overridePhoneId;
    }

    public function handle()
    {
        // Verifica horário disponível (dias/horas agendadas)
        $now = \Carbon\Carbon::now('America/Sao_Paulo');
        $daysOfWeek = [
            0 => 'domingo',
            1 => 'segunda',
            2 => 'terça',
            3 => 'quarta',
            4 => 'quinta',
            5 => 'sexta',
            6 => 'sábado',
        ];
        $dayOfWeek = $daysOfWeek[$now->dayOfWeek];
        $currentTime = $now->format('H:i:s');

        Log::info("📅 Verificando agendamento: {$dayOfWeek} às {$currentTime}");

        // Verifica se está no horário agendado - SEM CACHE
        $exists = DB::table('available_slots')
            ->where('day_of_week', $dayOfWeek)
            ->where('start_time', '<=', $currentTime)
            ->where('end_time', '>=', $currentTime)
            ->exists();

        Log::info("🔍 Resultado da busca: " . ($exists ? "EM HORÁRIO" : "FORA DO HORÁRIO"));

        if (!$exists) {
            // Fora do horário agendado - reenfileira sem enviar
            Log::warning("⏳ BLOQUEADO: Fora do horário agendado. Reenfileirando para tentar em 10 minutos...", [
                'dia' => $dayOfWeek,
                'hora' => $currentTime,
                'para' => $this->to
            ]);
            $this->release(600); // Aguarda 10 minutos para tentar novamente
            return;
        }

        Log::info("✅ EM HORÁRIO: Prosseguindo com envio para {$this->to}");

        $config = DB::table('whatsapp')->first();
        $token = $config->access_token ?? env('WHATSAPP_TOKEN');
        $phoneNumberId = $this->overridePhoneId ?? ($config->phone_number_id ?? env('WHATSAPP_PHONE_ID'));

        if (empty($token) || empty($phoneNumberId)) {
            Log::error('SendWhatsappMessage: token ou phoneNumberId não configurado.', [
                'to' => $this->to,
                'phone_override' => $this->overridePhoneId
            ]);
            return;
        }

        $data = [
            "messaging_product" => "whatsapp",
            "to" => $this->to,
            "type" => "text",
            "text" => ["body" => $this->body]
        ];

        try {
            $res = Http::withToken($token)
                ->post("https://graph.facebook.com/v21.0/{$phoneNumberId}/messages", $data);

            Log::info('SendWhatsappMessage response', ['status' => $res->status(), 'body' => $res->body()]);
        } catch (\Exception $e) {
            Log::error('SendWhatsappMessage erro: ' . $e->getMessage());
        }
    }
}