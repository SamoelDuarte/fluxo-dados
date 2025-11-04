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
        $codigoUsuarioCarteiraCobranca = $request->input('codigoUsuarioCarteiraCobranca');
        $codigoCarteiraCobranca = $request->input('codigoCarteiraCobranca');
        $pessoaCodigo = $request->input('pessoaCodigo');
        $dataPrimeiraParcela = $request->input('dataPrimeiraParcela');
        $valorEntrada = $request->input('valorEntrada', 0);
        $renegociaSomenteDocumentosEmAtraso = $request->input('renegociaSomenteDocumentosEmAtraso');
        $tipoSimulacao = $request->input('TipoSimulacao');
        $chave = env('HAVAN_API_PASSWORD');
        
        try {
            $token = $this->obterTokenHavan();
            
            if (!$token) {
                Log::error('[FLUXO-DADOS] Falha ao obter token para obterParcelamento');
                return response()->json([
                    'success' => false,
                    'message' => 'Erro ao obter token de autenticação'
                ], 401);
            }

            if (!is_numeric($codigoCarteiraCobranca) || intval($codigoCarteiraCobranca) <= 0) {
                return response()->json([
                    'error' => 'O parâmetro "codigoCarteiraCobranca" deve ser um inteiro válido maior que zero.'
                ], 400);
            }
            if (!is_numeric($codigoUsuarioCarteiraCobranca) || intval($codigoUsuarioCarteiraCobranca) <= 0) {
                return response()->json([
                    'error' => 'O parâmetro "codigoUsuarioCarteiraCobranca" deve ser um inteiro válido maior que zero.'
                ], 400);
            }

            $body = [
                'codigoUsuarioCarteiraCobranca' => (int) $codigoUsuarioCarteiraCobranca,
                'codigoCarteiraCobranca' => (int) $codigoCarteiraCobranca,
                'pessoaCodigo' => $pessoaCodigo,
                'dataPrimeiraParcela' => $dataPrimeiraParcela,
                'valorEntrada' => (int) $valorEntrada,
                'chave' => $chave
            ];

            // Adicionar renegociaSomenteDocumentosEmAtraso apenas se fornecido
            if ($renegociaSomenteDocumentosEmAtraso !== null) {
                $body['renegociaSomenteDocumentosEmAtraso'] = (bool) $renegociaSomenteDocumentosEmAtraso;
            }

            // Adicionar tipoSimulacao apenas se fornecido
            if ($tipoSimulacao !== null) {
                $body['TipoSimulacao'] = (int) $tipoSimulacao;
            }

            Log::info('[FLUXO-DADOS] Payload enviado para ObterOpcoesParcelamento', [
                'body' => $body,
                'user_agent' => $request->header('User-Agent'),
                'ip' => $request->ip()
            ]);

            $response = Http::withHeaders([
                'Accept' => 'text/plain',
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ])
            ->timeout(60)
            ->post('https://cobrancaexternaapi.apps.havan.com.br/api/v3/CobrancaExternaTradicional/ObterOpcoesParcelamento', $body);

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
                    
                    // Se encontrou a mais barata, usar ela; senão usar a primeira
                    $alcadaSelecionada = $alcadaMaisBarata ?? $responseData[0];
                    $responseData = [$alcadaSelecionada];
                    
                    Log::info('[FLUXO-DADOS] Múltiplas alçadas encontradas - selecionando a mais barata', [
                        'total_alcadas' => count($response->json()),
                        'alcada_selecionada' => $alcadaSelecionada['descricao'] ?? 'N/A',
                        'valor_avista_selecionado' => number_format($menorValor, 2, ',', '.'),
                        'total_parcelamentos' => is_array($alcadaSelecionada['parcelamento']) ? count($alcadaSelecionada['parcelamento']) : 0,
                    ]);
                }

                // Verificar se a resposta contém erro
                if (is_array($responseData) && isset($responseData[0]['messagem']) && strpos($responseData[0]['messagem'], 'não pertence') !== false) {
                    return response()->json([
                        'error' => 'Carteira não autorizada',
                        'message' => $responseData[0]['messagem']
                    ], 403);
                }

                if (is_array($responseData) && isset($responseData[0]['text']) && $responseData[0]['text'] === 'Nenhuma opção encontrada.') {
                    return response()->json(['mensagem' => 'nenhuma opção encontrada']);
                }
                
                return response()->json([
                    'success' => true,
                    'data' => $responseData
                ], $response->status());
            }

            // Erro na API
            $responseBody = $response->json();
            Log::error('[FLUXO-DADOS] Erro na API Havan ObterOpcoesParcelamento - Status: ' . $response->status(), [
                'body' => $responseBody,
                'request_body' => $body
            ]);

            // Verificar se a resposta contém erro sobre carteira
            if (is_array($responseBody) && isset($responseBody['messagem']) && strpos($responseBody['messagem'], 'não pertence') !== false) {
                return response()->json([
                    'error' => 'Carteira não autorizada',
                    'message' => $responseBody['messagem']
                ], $response->status());
            }
            
            if (is_array($responseBody) && isset($responseBody[0]['text']) && $responseBody[0]['text'] === 'Nenhuma opção encontrada.') {
                return response()->json(['mensagem' => 'nenhuma opção encontrada']);
            }

            return response()->json([
                'success' => false,
                'message' => 'Erro na API da Havan',
                'details' => $responseBody
            ], $response->status());

        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $response = $e->getResponse();
            $body = $response ? $response->getBody()->getContents() : null;
            $data = json_decode($body, true);

            Log::error('[FLUXO-DADOS] ClientException em obterParcelamento', [
                'message' => $e->getMessage(),
                'status' => $response ? $response->getStatusCode() : 'N/A',
                'body' => $body
            ]);

            if (is_array($data) && isset($data[0]['text']) && $data[0]['text'] === 'Nenhuma opção encontrada.') {
                return response()->json(['mensagem' => 'nenhuma opção encontrada']);
            }

            return response()->json([
                'error' => 'Erro ao consultar API externa',
                'message' => $e->getMessage(),
                'api_response' => $body
            ], $response ? $response->getStatusCode() : 500);
        } catch (\Exception $e) {
            Log::error('[FLUXO-DADOS] Exceção em obterParcelamento', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
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