<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class HavanController extends Controller
{
    /**
     * Teste de conectividade - endpoint para verificar se o serviço está funcionando
     */
    public function testeConectividade(Request $request)
    {
        try {
            $token = $this->obterTokenHavan();
            
            return response()->json([
                'success' => true,
                'message' => 'Fluxo-dados funcionando corretamente',
                'timestamp' => now(),
                'token_obtido' => !is_null($token),
                'token_length' => $token ? strlen($token) : 0,
                'env_vars' => [
                    'HAVAN_USERNAME' => env('HAVAN_USERNAME') ? 'Configurado' : 'Não configurado',
                    'HAVAN_PASSWORD' => env('HAVAN_PASSWORD') ? 'Configurado' : 'Não configurado',
                    'HAVAN_CLIENT_ID' => env('HAVAN_CLIENT_ID') ? 'Configurado' : 'Não configurado'
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro no teste de conectividade',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    private function obterTokenHavan()
    {
        try {
            $response = Http::asForm()
                ->timeout(30)
                ->post('https://cobrancaexternaauthapi.apps.havan.com.br/token', [
                    'grant_type' => 'password',
                    'client_id' => env('HAVAN_CLIENT_ID', 'bd210e1b-dac2-49b0-a9c4-7c5e1b0b241f'),
                    'username' => env('HAVAN_USERNAME', 'THF'),
                    'password' => env('HAVAN_PASSWORD', '3cr1O35JfhQ8vBO'),
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['access_token'] ?? null;
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Obter opções de parcelamento da Havan
     * Substitui: https://cobrancaexternaapi.apps.havan.com.br/api/v3/CobrancaExternaTradicional/ObterOpcoesParcelamento
     */
    public function obterParcelamento(Request $request)
    {
        try {
            $token = $this->obterTokenHavan();
            
            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erro ao obter token de autenticação'
                ], 401);
            }

            // Adicionar a chave (password) aos dados se não estiver presente
            $requestData = $request->all();
            if (!isset($requestData['chave'])) {
                $requestData['chave'] = env('HAVAN_PASSWORD', '3cr1O35JfhQ8vBO');
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ])
            ->timeout(60)
            ->post('https://cobrancaexternaapi.apps.havan.com.br/api/v3/CobrancaExternaTradicional/ObterOpcoesParcelamento', $requestData);

            if ($response->successful()) {
                $responseData = $response->json();
                
                // Se há múltiplas alçadas, pegar sempre a mais barata (menor valor à vista)
                if (is_array($responseData) && count($responseData) > 1) {
                    $alcadaMaisBarata = null;
                    $menorValor = PHP_FLOAT_MAX;
                    
                    // Encontrar a alçada com menor valor à vista (1 parcela)
                    foreach ($responseData as $alcada) {
                        if (isset($alcada['parcelamento'][0]['valorTotal'])) {
                            $valorAvista = $alcada['parcelamento'][0]['valorTotal'];
                            if ($valorAvista < $menorValor) {
                                $menorValor = $valorAvista;
                                $alcadaMaisBarata = $alcada;
                            }
                        }
                    }
                    
                    // Se encontrou a mais barata, usar ela; senão usar a última
                    $alcadaSelecionada = $alcadaMaisBarata ?? end($responseData);
                    $responseData = [$alcadaSelecionada];
                }
                
                return response()->json([
                    'success' => true,
                    'data' => $responseData
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Erro na API da Havan',
                'details' => $response->json()
            ], $response->status());

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro interno do servidor',
                'error_details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Contratar renegociação na Havan
     * Substitui: https://cobrancaexternaapi.apps.havan.com.br/api/v3/CobrancaExternaTradicional/ContratarRenegociacao
     */
    public function contratarRenegociacao(Request $request)
    {
        try {
            $token = $this->obterTokenHavan();
            
            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erro ao obter token de autenticação'
                ], 401);
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ])
            ->timeout(60)
            ->post('https://cobrancaexternaapi.apps.havan.com.br/api/v3/CobrancaExternaTradicional/ContratarRenegociacao', $request->all());

            if ($response->successful()) {
                $responseData = $response->json();
                
                return response()->json([
                    'success' => true,
                    'data' => $responseData
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Erro na API da Havan',
                'details' => $response->json()
            ], $response->status());

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro interno do servidor',
                'error_details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Gravar ocorrência externa na Havan
     * Substitui: https://cobrancaexternaapi.apps.havan.com.br/api/v3/CobrancaExterna/GravarOcorrenciaExterna
     */
    public function gravarOcorrencia(Request $request)
    {
        try {
            $token = $this->obterTokenHavan();
            
            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erro ao obter token de autenticação'
                ], 401);
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ])
            ->timeout(60)
            ->post('https://cobrancaexternaapi.apps.havan.com.br/api/v3/CobrancaExterna/GravarOcorrenciaExterna', $request->all());

            if ($response->successful()) {
                $responseData = $response->json();
                
                return response()->json([
                    'success' => true,
                    'data' => $responseData
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Erro na API da Havan',
                'details' => $response->json()
            ], $response->status());

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro interno do servidor',
                'error_details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obter boletos/documentos em aberto na Havan
     * Substitui: https://cobrancaexternaapi.apps.havan.com.br/api/v3/CobrancaExterna/ObterBoletosDocumentosEmAberto
     */
    public function obterBoletos(Request $request)
    {
        try {
            $token = $this->obterTokenHavan();
            
            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erro ao obter token de autenticação'
                ], 401);
            }

            // Adicionar a chave (password) aos dados se não estiver presente
            $requestData = $request->all();
            if (!isset($requestData['chave'])) {
                $requestData['chave'] = env('HAVAN_PASSWORD', '3cr1O35JfhQ8vBO');
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ])
            ->timeout(60)
            ->post('https://cobrancaexternaapi.apps.havan.com.br/api/v3/CobrancaExterna/ObterBoletosDocumentosEmAberto', $requestData);

            if ($response->successful()) {
                $responseData = $response->json();
                
                return response()->json([
                    'success' => true,
                    'data' => $responseData
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Erro na API da Havan',
                'details' => $response->json()
            ], $response->status());

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro interno do servidor',
                'error_details' => $e->getMessage()
            ], 500);
        }
    }
}