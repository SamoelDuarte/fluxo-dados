<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
            Log::error('[FLUXO-DADOS] Erro no teste de conectividade', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

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

            Log::error('[FLUXO-DADOS] Erro ao obter token Havan', [
                'status' => $response->status(),
                'body' => $response->body(),
                'headers' => $response->headers()
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('[FLUXO-DADOS] Exceção ao obter token Havan', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
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
                Log::error('[FLUXO-DADOS] Falha ao obter token para obterParcelamento');
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
                
                // Se há múltiplas alçadas, pegar sempre a última (maior índice)
                if (is_array($responseData) && count($responseData) > 1) {
                    $ultimaAlcada = end($responseData); // Pega o último elemento do array
                    $responseData = [$ultimaAlcada]; // Retorna apenas a última alçada
                    
                    Log::info('[FLUXO-DADOS] Múltiplas alçadas encontradas - selecionando a última', [
                        'total_alcadas' => count($response->json()),
                        'alcada_selecionada' => $ultimaAlcada['descricao'] ?? 'N/A',
                        'total_parcelamentos' => is_array($ultimaAlcada['parcelamento']) ? count($ultimaAlcada['parcelamento']) : 0
                    ]);
                }
                
                Log::info('[FLUXO-DADOS] Resposta bem-sucedida da API Havan ObterOpcoesParcelamento', [
                    'status' => $response->status(),
                    'response_size' => strlen($response->body()),
                    'response_type' => gettype($responseData),
                    'is_array' => is_array($responseData),
                    'array_count' => is_array($responseData) ? count($responseData) : 'N/A',
                    'request_data' => $requestData
                ]);
                
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
            Log::error('[FLUXO-DADOS] Exceção em obterParcelamento', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

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
        Log::info('[FLUXO-DADOS] Iniciando contratarRenegociacao', [
            'request_data' => $request->all(),
            'user_agent' => $request->header('User-Agent'),
            'ip' => $request->ip()
        ]);
        
        try {
            $token = $this->obterTokenHavan();
            
            if (!$token) {
                Log::error('[FLUXO-DADOS] Falha ao obter token para contratarRenegociacao');
                return response()->json([
                    'success' => false,
                    'message' => 'Erro ao obter token de autenticação'
                ], 401);
            }

            Log::info('[FLUXO-DADOS] Enviando requisição para API Havan ContratarRenegociacao', [
                'url' => 'https://cobrancaexternaapi.apps.havan.com.br/api/v3/CobrancaExternaTradicional/ContratarRenegociacao',
                'data' => $request->all(),
                'token_length' => strlen($token)
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ])
            ->timeout(60)
            ->post('https://cobrancaexternaapi.apps.havan.com.br/api/v3/CobrancaExternaTradicional/ContratarRenegociacao', $request->all());

            if ($response->successful()) {
                $responseData = $response->json();
                Log::info('[FLUXO-DADOS] Resposta bem-sucedida da API Havan ContratarRenegociacao', [
                    'status' => $response->status(),
                    'response_size' => strlen($response->body()),
                    'response_type' => gettype($responseData)
                ]);
                
                return response()->json([
                    'success' => true,
                    'data' => $responseData
                ]);
            }

            Log::error('[FLUXO-DADOS] Erro na API Havan ContratarRenegociacao', [
                'status' => $response->status(),
                'status_text' => $response->reason(),
                'body' => $response->body(),
                'headers' => $response->headers(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro na API da Havan',
                'details' => $response->json()
            ], $response->status());

        } catch (\Exception $e) {
            Log::error('[FLUXO-DADOS] Exceção em contratarRenegociacao', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

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
        Log::info('[FLUXO-DADOS] Iniciando gravarOcorrencia', [
            'request_data' => $request->all(),
            'user_agent' => $request->header('User-Agent'),
            'ip' => $request->ip()
        ]);
        
        try {
            $token = $this->obterTokenHavan();
            
            if (!$token) {
                Log::error('[FLUXO-DADOS] Falha ao obter token para gravarOcorrencia');
                return response()->json([
                    'success' => false,
                    'message' => 'Erro ao obter token de autenticação'
                ], 401);
            }

            Log::info('[FLUXO-DADOS] Enviando requisição para API Havan GravarOcorrenciaExterna', [
                'url' => 'https://cobrancaexternaapi.apps.havan.com.br/api/v3/CobrancaExterna/GravarOcorrenciaExterna',
                'data' => $request->all(),
                'token_length' => strlen($token)
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ])
            ->timeout(60)
            ->post('https://cobrancaexternaapi.apps.havan.com.br/api/v3/CobrancaExterna/GravarOcorrenciaExterna', $request->all());

            if ($response->successful()) {
                $responseData = $response->json();
                Log::info('[FLUXO-DADOS] Resposta bem-sucedida da API Havan GravarOcorrenciaExterna', [
                    'status' => $response->status(),
                    'response_size' => strlen($response->body()),
                    'response_type' => gettype($responseData)
                ]);
                
                return response()->json([
                    'success' => true,
                    'data' => $responseData
                ]);
            }

            Log::error('[FLUXO-DADOS] Erro na API Havan GravarOcorrenciaExterna', [
                'status' => $response->status(),
                'status_text' => $response->reason(),
                'body' => $response->body(),
                'headers' => $response->headers(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro na API da Havan',
                'details' => $response->json()
            ], $response->status());

        } catch (\Exception $e) {
            Log::error('[FLUXO-DADOS] Exceção em gravarOcorrencia', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

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
        Log::info('[FLUXO-DADOS] Iniciando obterBoletos', [
            'request_data' => $request->all(),
            'user_agent' => $request->header('User-Agent'),
            'ip' => $request->ip()
        ]);
        
        try {
            $token = $this->obterTokenHavan();
            
            if (!$token) {
                Log::error('[FLUXO-DADOS] Falha ao obter token para obterBoletos');
                return response()->json([
                    'success' => false,
                    'message' => 'Erro ao obter token de autenticação'
                ], 401);
            }

            // Adicionar a chave (password) aos dados se não estiver presente
            $requestData = $request->all();
            if (!isset($requestData['chave'])) {
                $requestData['chave'] = env('HAVAN_PASSWORD', '3cr1O35JfhQ8vBO');
                Log::info('[FLUXO-DADOS] Chave adicionada automaticamente aos dados da requisição');
            }

            Log::info('[FLUXO-DADOS] Enviando requisição para API Havan ObterBoletosDocumentosEmAberto', [
                'url' => 'https://cobrancaexternaapi.apps.havan.com.br/api/v3/CobrancaExterna/ObterBoletosDocumentosEmAberto',
                'data' => $requestData,
                'token_length' => strlen($token)
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ])
            ->timeout(60)
            ->post('https://cobrancaexternaapi.apps.havan.com.br/api/v3/CobrancaExterna/ObterBoletosDocumentosEmAberto', $requestData);

            if ($response->successful()) {
                $responseData = $response->json();
                Log::info('[FLUXO-DADOS] Resposta bem-sucedida da API Havan ObterBoletosDocumentosEmAberto', [
                    'status' => $response->status(),
                    'response_size' => strlen($response->body()),
                    'response_type' => gettype($responseData),
                    'is_array' => is_array($responseData),
                    'array_count' => is_array($responseData) ? count($responseData) : 'N/A'
                ]);
                
                return response()->json([
                    'success' => true,
                    'data' => $responseData
                ]);
            }

            Log::error('[FLUXO-DADOS] Erro na API Havan ObterBoletosDocumentosEmAberto', [
                'status' => $response->status(),
                'status_text' => $response->reason(),
                'body' => $response->body(),
                'headers' => $response->headers(),
                'request_data' => $requestData
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro na API da Havan',
                'details' => $response->json()
            ], $response->status());

        } catch (\Exception $e) {
            Log::error('[FLUXO-DADOS] Exceção em obterBoletos', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro interno do servidor',
                'error_details' => $e->getMessage()
            ], 500);
        }
    }
}