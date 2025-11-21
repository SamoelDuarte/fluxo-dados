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
use App\Models\Campanha;
use Carbon\Carbon;

class SendWhatsappMessageQueue implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 30;
    public $backoff = [10, 30, 60];

    protected $contatoDadoId;
    protected $campanhaId;
    protected $phoneNumberId;
    protected $accessToken;
    protected $templateName;

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
            $now = Carbon::now('America/Sao_Paulo');

            $daysOfWeek = [
                0 => 'domingo',
                1 => 'segunda',
                2 => 'terca',
                3 => 'quarta',
                4 => 'quinta',
                5 => 'sexta',
                6 => 'sabado',
            ];
            $day = $daysOfWeek[$now->dayOfWeek];
            $time = $now->format('H:i:s');

            // Valida se campanha ainda esta ativa
            $campanha = Campanha::find($this->campanhaId);
            if (!$campanha || $campanha->status != 'playing') {
                Log::warning('Campanha pausada. Cancelando contato ' . $this->contatoDadoId);
                DB::table('contato_dados')->where('id', $this->contatoDadoId)->update(['send' => 0]);
                $this->delete();
                return;
            }

            // Valida horario permitido ANTES de enviar
            $allowed = DB::table('available_slots')
                ->where('day_of_week', $day)
                ->where('start_time', '<=', $time)
                ->where('end_time', '>=', $time)
                ->exists();

            if (!$allowed) {
                Log::info('Contato ' . $this->contatoDadoId . ' fora do horario. Reagendando.');
                DB::table('contato_dados')->where('id', $this->contatoDadoId)->update(['send' => 0]);
                $this->delete();
                return;
            }

            // Buscar contato
            $contatoDado = DB::table('contato_dados')->find($this->contatoDadoId);
            if (!$contatoDado) {
                Log::error('Contato nao encontrado: ' . $this->contatoDadoId);
                $this->delete();
                return;
            }

            // Validar telefone
            $numeroContato = preg_replace('/[^0-9]/', '', $contatoDado->telefone ?? '');
            if (empty($numeroContato)) {
                Log::error('Telefone vazio: ' . $this->contatoDadoId);
                DB::table('contato_dados')->where('id', $this->contatoDadoId)->update(['send' => -1]);
                $this->delete();
                return;
            }

            // Preparar mensagem
            $nomeCompleto = $contatoDado->nome ?? 'Cliente';
            $primeiroNome = explode(' ', trim($nomeCompleto))[0];

            $data = [
                'messaging_product' => 'whatsapp',
                'to' => $numeroContato,
                'type' => 'template',
                'template' => [
                    'name' => $this->templateName,
                    'language' => ['code' => 'pt_BR'],
                    'components' => [
                        [
                            'type' => 'body',
                            'parameters' => [
                                ['type' => 'text', 'text' => $primeiroNome]
                            ]
                        ]
                    ]
                ]
            ];

            // Enviar
            $client = new Client();
            $response = $client->post(
                'https://graph.facebook.com/v23.0/' . $this->phoneNumberId . '/messages',
                [
                    'json' => $data,
                    'headers' => ['Authorization' => 'Bearer ' . $this->accessToken],
                    'timeout' => 30,
                ]
            );

            $responseData = json_decode($response->getBody()->getContents(), true);

            if (isset($responseData['messages'][0]['id'])) {
                $horaSistema = Carbon::now('America/Sao_Paulo')->format('d/m/Y H:i:s');
                $horaAgendamento = $contatoDado->created_at ? Carbon::parse($contatoDado->created_at)->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i:s') : 'N/A';
                
                DB::table('contato_dados')
                    ->where('id', $this->contatoDadoId)
                    ->update(['send' => 1, 'updated_at' => now()]);
                
                Log::info('Enviado: ' . $numeroContato . ' | Agendado em: ' . $horaAgendamento . ' | Sistema agora: ' . $horaSistema . ' | Campanha: ' . $this->campanhaId);
                $this->delete();
            } else {
                Log::warning('Resposta invalida para ' . $numeroContato);
                $this->release(30);
            }

        } catch (RequestException $e) {
            Log::error('Erro HTTP: ' . $e->getMessage());
            $this->release(30);
        } catch (\Exception $e) {
            Log::error('Erro: ' . $e->getMessage());
            $this->release(30);
        }
    }

    public function failed(\Throwable $exception)
    {
        Log::error('Job falhou. Contato: ' . $this->contatoDadoId);
        DB::table('contato_dados')
            ->where('id', $this->contatoDadoId)
            ->update(['send' => -1]);
    }
}
