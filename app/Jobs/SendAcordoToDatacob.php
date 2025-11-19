<?php

namespace App\Jobs;

use App\Models\Acordo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class SendAcordoToDatacob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $acordoId;

    public function __construct($acordoId)
    {
        $this->acordoId = $acordoId;
    }

    private function getToken()
    {
        try {
            $client = new Client();
            $response = $client->post(
                'https://neocob-microservice.herokuapp.com/authenticate',
                [
                    'headers' => [
                        'Content-Type' => 'application/json'
                    ],
                    'json' => [
                        'login' => env('datacod_LOGIN'),
                        'password' => env('datacod_PASSWORD'),
                        'apiKey' => env('datacod_APIKEY')
                    ]
                ]
            );

            $body = json_decode($response->getBody(), true);
            return $body['token'] ?? null;
        } catch (\Exception $e) {
            \Log::error("❌ Erro ao obter token Datacob: " . $e->getMessage());
            return null;
        }
    }

    public function handle()
    {
        try {
            $acordo = Acordo::findOrFail($this->acordoId);

            if (!$acordo->contatoDado || !$acordo->contatoDado->id_contrato) {
                return;
            }

            $token = $this->getToken();
            if (!$token) {
                \Log::error("❌ Não foi possível obter token para acordo {$this->acordoId}");
                if ($this->attempts() < 3) {
                    $this->release(60);
                }
                return;
            }

            $idContrato = (int)$acordo->contatoDado->id_contrato;
            $qtdeParcelas = 1;
            $valorParcela = 0.00;
            $dataPagtoEntrada = $acordo->created_at->format('Y-m-d');
            $dataVencimento = $acordo->created_at->format('Y-m-d');

            if (preg_match('/(\d+)x\s+de\s+R\$\s+([\d.,]+)/', $acordo->texto, $matches)) {
                $qtdeParcelas = (int)$matches[1];
                $valorParcela = (float)str_replace(',', '.', $matches[2]);
            } elseif (preg_match('/acordo\s+a\s+vista:\s+R\$\s+([\d.,]+)/', $acordo->texto, $matches)) {
                $qtdeParcelas = 1;
                $valorParcela = (float)str_replace(',', '.', $matches[1]);
            }

            // Extrai data de vencimento do padrão: "Venc: dd/mm/yyyy"
            if (preg_match('/Venc:\s+(\d{2})\/(\d{2})\/(\d{4})/', $acordo->texto, $matches)) {
                $dataVencimento = $matches[3] . '-' . $matches[2] . '-' . $matches[1];
            }

            $payload = [
                'IdContrato' => $idContrato,
                'ValorEntrada' => 0.00,
                'QtdeParcelas' => $qtdeParcelas,
                'DataPagtoEntrada' => $dataVencimento,
                'DataNegociacao' => $dataPagtoEntrada,
                'ValorParcela' => $valorParcela,
            ];

            dd($payload);

            $client = new Client();
            $response = $client->post(
                'http://datacob.thiagofarias.adv.br/api/negociacao/v1/confirmar-acordo',
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $token,
                        'apiKey' => 'PYBW+7AndDA=',
                        'Content-Type' => 'application/json',
                    ],
                    'json' => $payload,
                ]
            );

            $statusCode = $response->getStatusCode();
            if ($statusCode === 200 || $statusCode === 201) {
                $acordo->update(['status' => 'enviado']);
            }

        } catch (RequestException $e) {
            \Log::error("❌ Erro ao enviar acordo {$this->acordoId}: " . $e->getMessage());
            if ($this->attempts() < 3) {
                $this->release(60);
            }
        } catch (\Exception $e) {
            \Log::error("❌ Erro geral ao enviar acordo {$this->acordoId}: " . $e->getMessage());
        }
    }
}
