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

            // Verifica se está no horário agendado
            $exists = DB::table('available_slots')
                ->where('day_of_week', $dayOfWeek)
                ->where('start_time', '<=', $currentTime)
                ->where('end_time', '>=', $currentTime)
                ->exists();

            if (!$exists) {
                // Fora do horário agendado - mantém send=2 e aguarda o próximo horário
                Log::info("⏳ Fora do horário agendado. Reenfileirando para tentar depois...");
                
                // Recoloca na fila SEM ALTERAR send para continuar aguardando
                $this->release(20); // Aguarda 20 segundos para tentar novamente
                return;
            }

            // Verifica se há flag de pausa
            if (file_exists(storage_path('app/queue-pause.flag'))) {
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
                return;
            }

            // Busca o contato pelo ID
            $contatoDado = (array) DB::table('contato_dados')->find($this->contatoDadoId);

            if (empty($contatoDado) || !isset($contatoDado['id'])) {
                Log::error("❌ Contato não encontrado: ID {$this->contatoDadoId}");
                $this->delete();
                return;
            }

            // Formata o número do contato
            $numeroContato = preg_replace('/[^0-9]/', '', $contatoDado['telefone'] ?? '');
            if (empty($numeroContato)) {
                Log::error("❌ Número do telefone vazio!");
                DB::table('contato_dados')->where('id', $this->contatoDadoId)->update(['send' => -1]);
                $this->delete();
                return;
            }

            // Extrai primeiro nome do contato
            $nomeCompleto = $contatoDado['nome'] ?? 'Cliente';
            $primeiroNome = explode(' ', trim($nomeCompleto))[0];

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

                Log::info("✅ Mensagem enviada! Contato: {$numeroContato}");
                
                // Remove job da fila após sucesso
                $this->delete();
            } else {
                Log::warning("⚠️ Resposta sem ID de mensagem");
                // Mantém send=2 e faz release
                $this->release(30);
            }

        } catch (RequestException $e) {
            Log::error("❌ Erro HTTP: " . $e->getMessage());
            $this->release(30);
            
        } catch (\Exception $e) {
            Log::error("❌ Erro: " . $e->getMessage());
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
