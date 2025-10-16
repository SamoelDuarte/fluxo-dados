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

class SendWhatsappTypingThenMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $to;
    public string $body;
    public ?string $overridePhoneId;
    public int $typingSeconds;

    public function __construct(string $to, string $body, ?string $overridePhoneId = null, int $typingSeconds = 3)
    {
        $this->to = $to;
        $this->body = $body;
        $this->overridePhoneId = $overridePhoneId;
        $this->typingSeconds = $typingSeconds;
    }

    public function handle()
    {
        $config = DB::table('whatsapp')->first();
        $token = $config->access_token ?? env('WHATSAPP_TOKEN');
        $phoneNumberId = $this->overridePhoneId ?? ($config->phone_number_id ?? env('WHATSAPP_PHONE_ID'));

        if (empty($token) || empty($phoneNumberId)) {
            Log::error('SendWhatsappTypingThenMessage: token ou phoneNumberId não configurado.', ['to' => $this->to]);
            return;
        }

        try {
            // typing_on
            $typingOn = [
                "messaging_product" => "whatsapp",
                "to" => $this->to,
                "type" => "typing_on"
            ];
            Http::withToken($token)
                ->post("https://graph.facebook.com/v21.0/{$phoneNumberId}/messages", $typingOn);
            Log::info('typing_on enviado', ['to' => $this->to]);

            // espera para simular digitação
            sleep(max(1, $this->typingSeconds));

            // typing_off (opcional antes de enviar a mensagem)
            $typingOff = [
                "messaging_product" => "whatsapp",
                "to" => $this->to,
                "type" => "typing_off"
            ];
            Http::withToken($token)
                ->post("https://graph.facebook.com/v21.0/{$phoneNumberId}/messages", $typingOff);
            Log::info('typing_off enviado', ['to' => $this->to]);

            // envia a mensagem final
            $data = [
                "messaging_product" => "whatsapp",
                "to" => $this->to,
                "type" => "text",
                "text" => ["body" => $this->body]
            ];

            $res = Http::withToken($token)
                ->post("https://graph.facebook.com/v21.0/{$phoneNumberId}/messages", $data);

            Log::info('Mensagem enviada via typing-job', ['to' => $this->to, 'status' => $res->status(), 'body' => $res->body()]);
        } catch (\Exception $e) {
            Log::error('SendWhatsappTypingThenMessage erro: ' . $e->getMessage());
        }
    }
}