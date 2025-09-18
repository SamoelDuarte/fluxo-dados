<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HavanController extends Controller
{
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

            Log::error('Erro ao obter token Havan', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Exceção ao obter token Havan', [
                'message' => $e->getMessage()
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
                return response()->json([
                    'success' => true,
                    'data' => $response->json()
                ]);
            }

            Log::error('Erro na API Havan ObterOpcoesParcelamento', [
                'status' => $response->status(),
                'body' => $response->body(),
                'request_data' => $requestData
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro na API da Havan',
                'details' => $response->json()
            ], $response->status());

        } catch (\Exception $e) {
            Log::error('Exceção em obterParcelamento', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro interno do servidor'
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
                return response()->json([
                    'success' => true,
                    'data' => $response->json()
                ]);
            }

            Log::error('Erro na API Havan ContratarRenegociacao', [
                'status' => $response->status(),
                'body' => $response->body(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro na API da Havan',
                'details' => $response->json()
            ], $response->status());

        } catch (\Exception $e) {
            Log::error('Exceção em contratarRenegociacao', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro interno do servidor'
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
                return response()->json([
                    'success' => true,
                    'data' => $response->json()
                ]);
            }

            Log::error('Erro na API Havan GravarOcorrenciaExterna', [
                'status' => $response->status(),
                'body' => $response->body(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro na API da Havan',
                'details' => $response->json()
            ], $response->status());

        } catch (\Exception $e) {
            Log::error('Exceção em gravarOcorrencia', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro interno do servidor'
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
                return response()->json([
                    'success' => true,
                    'data' => $response->json()
                ]);
            }

            Log::error('Erro na API Havan ObterBoletosDocumentosEmAberto', [
                'status' => $response->status(),
                'body' => $response->body(),
                'request_data' => $requestData
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro na API da Havan',
                'details' => $response->json()
            ], $response->status());

        } catch (\Exception $e) {
            Log::error('Exceção em obterBoletos', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro interno do servidor'
            ], 500);
        }
    }
}