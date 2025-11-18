<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SendWhatsappMessageQueue implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Configurações de tentativas e timeout
    public $tries = 3; // Tenta 3 vezes se falhar
    public $timeout = 30; // Timeout de 30 segundos por job
    public $backoff = [10, 30, 60]; // Aguarda 10s, 30s, 60s entre tentativas

    protected $contatoDadoId;
    protected $campanhaId;
    protected $phoneNumberId;
    protected $accessToken;
    protected $templateName;

    /**
     * Construtor do Job
     */
    public function __construct($contatoDadoId, $campanhaId, $phoneNumberId, $accessToken, $templateName)
    {
        $this->contatoDadoId = $contatoDadoId;
        $this->campanhaId = $campanhaId;
        $this->phoneNumberId = $phoneNumberId;
        $this->accessToken = trim($accessToken);
        $this->templateName = $templateName;
    }

    /**
     * Handle do Job - Executado pela fila
     */
    public function handle()
    {
        try {
            // Busca o contato pelo ID
            $contatoDado = DB::table('contato_dados')->find($this->contatoDadoId);

            if (!$contatoDado) {
                Log::error("❌ Contato não encontrado: ID {$this->contatoDadoId}");
                return;
            }

            // Formata o número do contato
            $numeroContato = preg_replace('/[^0-9]/', '', $contatoDado->telefone);

            // Extrai primeiro nome do contato
            $nomeCompleto = $contatoDado->nome ?? 'Cliente';
            $primeiroNome = explode(' ', trim($nomeCompleto))[0];

            Log::info("📤 [Job Queue] Enviando para: {$numeroContato} ({$primeiroNome})");

            // Cria cliente Guzzle
            $client = new Client();

            // Monta o payload da mensagem
            $data = [
                'messaging_product' => 'whatsapp',
                'to' => $numeroContato,
                'type' => 'template',
                'template' => [
                    'name' => $this->templateName,
                    'language' => [
                        'code' => 'pt_BR'
                    ],
                    'components' => [
                        [
                            'type' => 'body',
                            'parameters' => [
                                [
                                    'type' => 'text',
                                    'text' => $primeiroNome
                                ]
                            ]
                        ]
                    ]
                ]
            ];

            // Headers para WhatsApp Business API
            $headers = [
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json',
            ];

            // Envia a requisição
            $response = $client->post(
                'https://graph.facebook.com/v23.0/' . $this->phoneNumberId . '/messages',
                [
                    'json' => $data,
                    'headers' => $headers,
                    'timeout' => 30,
                ]
            );

            $responseBody = $response->getBody()->getContents();
            $responseData = json_decode($responseBody, true);

            // Verifica se o envio foi bem-sucedido
            if (isset($responseData['messages'][0]['id'])) {
                // Marca como enviado no banco (send=1)
                DB::table('contato_dados')
                    ->where('id', $this->contatoDadoId)
                    ->update([
                        'send' => 1,
                        'updated_at' => now()
                    ]);

                Log::info("✅ Mensagem enviada com sucesso! ID: {$responseData['messages'][0]['id']} | Contato: {$numeroContato}");
            } else {
                Log::warning("⚠️ Resposta sem ID de mensagem: " . json_encode($responseData));
                // Retorna para send=0 para tentar novamente
                DB::table('contato_dados')
                    ->where('id', $this->contatoDadoId)
                    ->update(['send' => 0]);
                // Tenta novamente após timeout
                $this->release(30);
            }

        } catch (RequestException $e) {
            Log::error("❌ Erro RequestException: " . $e->getMessage());
            // Tenta novamente
            $this->release(30);
        } catch (\Exception $e) {
            Log::error("❌ Erro ao processar Job: " . $e->getMessage());
            Log::error("Stack: " . $e->getTraceAsString());
            // Tenta novamente
            $this->release(30);
        }
    }

    /**
     * Executado quando o job falha definitivamente (após todas as tentativas)
     */
    public function failed(\Throwable $exception)
    {
        Log::error("❌ Job FALHOU definitivamente após {$this->tries} tentativas");
        Log::error("Contato ID: {$this->contatoDadoId}");
        Log::error("Erro: " . $exception->getMessage());

        // Marcar como erro permanente (send=-1)
        DB::table('contato_dados')
            ->where('id', $this->contatoDadoId)
            ->update([
                'send' => -1, // -1 = erro permanente (falhou 3 vezes)
                'updated_at' => now()
            ]);
    }
}
