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

    public function handle()
    {
        try {
           
           

            Log::info("✅ PROSSEGUINDO: JOB SERÁ PROCESSADO AGORA");

            // Verifica se há flag de pausa
            if (file_exists(storage_path('app/queue-pause.flag'))) {
                Log::warning("🛑 FLAG DE PAUSA DETECTADA - CANCELANDO JOB");
                // Revolver para send=0 para tentar depois
                DB::table('contato_dados')
                    ->where('id', $this->contatoDadoId)
                    ->update(['send' => 0]);
                // Deleta o arquivo de pausa
                $pausaFile = storage_path('app/queue-pause.flag');
                if (file_exists($pausaFile)) {
                    unlink($pausaFile);
                }
                $this->delete();
                Log::info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
                return;
            }

            // Busca o contato pelo ID
            $contatoDado = (array) DB::table('contato_dados')->find($this->contatoDadoId);

            if (empty($contatoDado) || !isset($contatoDado['id'])) {
                Log::error("❌ Contato não encontrado: ID {$this->contatoDadoId}");
                Log::info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
                $this->delete();
                return;
            }

            // Formata o número do contato
            $numeroContato = preg_replace('/[^0-9]/', '', $contatoDado['telefone'] ?? '');
            if (empty($numeroContato)) {
                Log::error("❌ Número do telefone vazio!");
                DB::table('contato_dados')->where('id', $this->contatoDadoId)->update(['send' => -1]);
                Log::info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
                $this->delete();
                return;
            }

            // Extrai primeiro nome do contato
            $nomeCompleto = $contatoDado['nome'] ?? 'Cliente';
            $primeiroNome = explode(' ', trim($nomeCompleto))[0];

            Log::info("📱 Enviando para: {$numeroContato} ({$primeiroNome})");
            Log::info("📧 Template: {$this->templateName}");

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

                Log::info("✅ SUCESSO: Mensagem enviada!");
                Log::info("   - Contato: {$numeroContato}");
                Log::info("   - Message ID: {$responseData['messages'][0]['id']}");
                Log::info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
                
                // Remove job da fila após sucesso
                $this->delete();
            } else {
                Log::warning("⚠️ Resposta sem ID de mensagem");
                Log::warning("   Resposta: " . json_encode($responseData));
                Log::info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
                // Mantém send=2 e faz release
                $this->release(30);
            }

        } catch (RequestException $e) {
            Log::error("❌ ERRO HTTP ao enviar mensagem");
            Log::error("   Mensagem: " . $e->getMessage());
            Log::error("   Resposta: " . ($e->hasResponse() ? $e->getResponse()->getBody() : 'N/A'));
            Log::info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->release(30);
            
        } catch (\Exception $e) {
            Log::error("❌ ERRO GERAL: " . $e->getMessage());
            Log::error("   Arquivo: " . $e->getFile() . " | Linha: " . $e->getLine());
            Log::info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
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
