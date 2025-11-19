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
        $tentativa = $this->attempts() ?? 1;
        $jobId = $this->job->getJobId() ?? 'unknown';
        
        Log::info("════════════════════════════════════════════════════════════");
        Log::info("[JOB INICIOU] ID: {$jobId} | Contato: {$this->contatoDadoId} | Tentativa: {$tentativa}/3");
        
        try {
            // Verifica se há flag de pausa
            if (file_exists(storage_path('app/queue-pause.flag'))) {
                Log::warning("[PAUSA] Flag detectada - Job cancelado");
                DB::table('contato_dados')
                    ->where('id', $this->contatoDadoId)
                    ->update(['send' => 0]);
                @unlink(storage_path('app/queue-pause.flag'));
                $this->delete();
                Log::info("[PAUSA] Job deletado");
                return;
            }
            Log::info("[PAUSA] Flag NÃO detectada - Continuando");

            // Busca o contato pelo ID
            Log::info("[BANCO] Buscando contato ID: {$this->contatoDadoId}");
            $contatoDado = (array) DB::table('contato_dados')->find($this->contatoDadoId);

            if (empty($contatoDado) || !isset($contatoDado['id'])) {
                Log::error("[ERRO] Contato NÃO ENCONTRADO no banco! ID: {$this->contatoDadoId}");
                $this->delete();
                return;
            }
            Log::info("[BANCO] ✓ Contato encontrado: {$contatoDado['nome']}");

            // Formata o número do contato
            $numeroContato = preg_replace('/[^0-9]/', '', $contatoDado['telefone'] ?? '');
            if (empty($numeroContato)) {
                Log::error("[ERRO] Número do telefone vazio!");
                DB::table('contato_dados')->where('id', $this->contatoDadoId)->update(['send' => -1]);
                $this->delete();
                return;
            }
            Log::info("[TELEFONE] {$numeroContato}");

            // Extrai primeiro nome do contato
            $nomeCompleto = $contatoDado['nome'] ?? 'Cliente';
            $primeiroNome = explode(' ', trim($nomeCompleto))[0];
            Log::info("[NOME] {$primeiroNome}");

            Log::info("[ENVIANDO] Para: {$numeroContato} | Nome: {$primeiroNome} | Template: {$this->templateName}");

            // Cria cliente Guzzle
            Log::info("[GUZZLE] Criando cliente...");
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
            Log::info("[PAYLOAD] " . json_encode($data));

            // Headers para WhatsApp Business API
            $headers = [
                'Authorization' => 'Bearer ' . substr($this->accessToken, 0, 20) . '...',
                'Content-Type' => 'application/json',
            ];
            Log::info("[HEADERS] Authorization: " . $headers['Authorization']);

            // Envia a requisição
            Log::info("[API] Enviando POST para: https://graph.facebook.com/v23.0/{$this->phoneNumberId}/messages");
            $response = $client->post(
                'https://graph.facebook.com/v23.0/' . $this->phoneNumberId . '/messages',
                [
                    'json' => $data,
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->accessToken,
                        'Content-Type' => 'application/json',
                    ],
                    'timeout' => 30,
                ]
            );

            Log::info("[RESPOSTA] Status HTTP: " . $response->getStatusCode());
            $responseBody = $response->getBody()->getContents();
            Log::info("[RESPOSTA] Body: {$responseBody}");
            $responseData = json_decode($responseBody, true);
            Log::info("[RESPOSTA] Decoded: " . json_encode($responseData));

            // Verifica se o envio foi bem-sucedido
            if (isset($responseData['messages'][0]['id'])) {
                $messageId = $responseData['messages'][0]['id'];
                Log::info("[SUCESSO] Message ID: {$messageId}");
                
                // Marca como enviado no banco (send=1)
                Log::info("[BANCO] Atualizando send=1 para contato {$this->contatoDadoId}");
                DB::table('contato_dados')
                    ->where('id', $this->contatoDadoId)
                    ->update([
                        'send' => 1,
                        'updated_at' => now()
                    ]);

                Log::info("[SUCESSO] ✅ Mensagem enviada! ID: {$messageId} | Contato: {$numeroContato}");
                
                // Remove job da fila após sucesso
                Log::info("[JOB] Deletando job da fila");
                $this->delete();
                Log::info("[JOB] ✅ Job deletado com sucesso");
                Log::info("════════════════════════════════════════════════════════════\n");
            } else {
                Log::warning("[RESPOSTA] SEM ID DE MENSAGEM: " . json_encode($responseData));
                Log::warning("[JOB] Mantendo send=2 e fazendo release(30)");
                // NÃO VOLTA PARA 0 - MANTÉM EM 2
                $this->release(30);
                Log::info("════════════════════════════════════════════════════════════\n");
            }

        } catch (RequestException $e) {
            Log::error("[ERRO HTTP] " . $e->getMessage());
            if ($e->hasResponse()) {
                Log::error("[ERRO RESPOSTA] " . $e->getResponse()->getBody()->getContents());
            }
            Log::warning("[JOB] Mantendo send=2 e fazendo release(30)");
            $this->release(30);
            Log::info("════════════════════════════════════════════════════════════\n");
            
        } catch (\Exception $e) {
            Log::error("[ERRO GERAL] " . $e->getMessage());
            Log::error("[ERRO STACK] " . $e->getTraceAsString());
            Log::warning("[JOB] Mantendo send=2 e fazendo release(30)");
            $this->release(30);
            Log::info("════════════════════════════════════════════════════════════\n");
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
