<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Log;
use App\Models\WhatsappContact;
use App\Models\WhatsappMessage;
use App\Models\WhatsappSession;
use App\Models\WhatsappFlow;
use App\Models\WhatsappFlowStep;
use App\Models\Carteira;
use App\Models\ContatoDados;
use App\Models\Acordo;
use GuzzleHttp\Client;
use Carbon\Carbon;

class WhatsappController extends Controller
{

    public function verificaChat(Request $request)
    {
        $data = $request->all();

        // Extrai os dados recebidos em variáveis
        $name = $data['nome'] ?? null;
        $wa_id = $data['numero'] ?? null;
        $phoneNumberId = $data['phone_number_id'] ?? null;
        $messageData = $data['messageData'] ?? null;

        // Extrai dados da mensagem se existir
        $messageId = $messageData['id'] ?? null;
        $messageText = $messageData['text']['body'] ?? null;
        $messageType = $messageData['type'] ?? 'text';
        $messageTimestamp = isset($messageData['timestamp']) ? date('Y-m-d H:i:s', $messageData['timestamp']) : now();
        $contatoDados = ContatoDados::where('telefone', preg_replace('/\D/', '', $wa_id))->first();
        // Variável fixa para simular dia útil
        $isDiaUtil = true;

        // 1️⃣ Verifica se o contato já existe
        $contact = WhatsappContact::firstOrCreate(
            ['wa_id' => $wa_id],
            ['name' => $name]
        );

        // 2️⃣ Salva a mensagem recebida (se houver texto)
        if ($messageId && $messageText) {
            WhatsappMessage::create([
                'contact_id' => $contact->id,
                'message_id' => $messageId,
                'direction' => 'in',
                'content' => $messageText,
                'type' => $messageType,
                'timestamp' => $messageTimestamp,
                'raw' => $messageData,
            ]);
        }

        // 3️⃣ Verifica sessão do usuário
        $session = WhatsappSession::where('contact_id', $contact->id)->first();

        // Resposta para o n8n
        $responseN8N = [];

        if (!$isDiaUtil) {
            echo 'fora_do_dia_util';
        } else {
            if (!$session) {
                // Primeira mensagem: cria a sessão com contexto inicial
                $initialContext = [
                    'created_at' => now()->toIso8601String(),
                    'contact_name' => $name,
                    'wa_id' => $wa_id,
                    'messages_count' => 1,
                    'last_message' => $messageText,
                ];
                $session = WhatsappSession::create([
                    'contact_id' => $contact->id,
                    'phone_number_id' => $phoneNumberId ?? null,
                    'context' => $initialContext,
                ]);
                // Atualiza para o próximo step (exemplo: verifica_cpf)
                $step = $this->atualizaStep($session, 'verifica_cpf');
                echo json_encode([
                    'status' => 'primeira_mensagem',
                    'step' => '',
                    'cpf' => $this->formatarCpfMascarado($contatoDados->document ?? '')
                ]);
            } else {
                // Atualiza contexto da sessão existente
                $context = $session->context ?? [];
                $context['messages_count'] = ($context['messages_count'] ?? 0) + 1;
                $context['last_message'] = $messageText;
                $context['last_update_at'] = now()->toIso8601String();

                $session->update(['context' => $context]);

                echo json_encode([
                    'status' => 'chat_existente',
                    'step' => $session->current_step
                ]);
            }
        }

    }
    /**
     * Atualiza o step da sessão e retorna o step atualizado
     */
    public function atualizaStep($session, $proximoStep)
    {

        $session->current_step = $proximoStep;
        $session->save();
        return $proximoStep;

    }

    /**
     * Atualiza o context da sessão por wa_id
     * @param string $wa_id
     * @param array $contextData - dados a serem adicionados/atualizados no context
     */
    private function atualizarContextoSessao($wa_id, $contextData)
    {
        if (empty($wa_id)) {
            return false;
        }

        $contact = WhatsappContact::where('wa_id', $wa_id)->first();
        if (!$contact) {
            return false;
        }

        $session = WhatsappSession::where('contact_id', $contact->id)->first();
        if (!$session) {
            return false;
        }

        $context = $session->context ?? [];
        // Mescla os dados novos com o contexto existente
        $context = array_merge($context, $contextData);
        $session->update(['context' => $context]);
        return true;
    }

    /**
     * Helper privado para atualizar contexto e step (chamado internamente)
     * @param string $wa_id
     * @param array $contextData
     * @param string $currentStep
     * @return bool
     */
    private function atualizarContextoEStepSessaoInterno($wa_id, $contextData, $currentStep)
    {
        if (empty($wa_id)) {
            return false;
        }

        $contact = WhatsappContact::where('wa_id', $wa_id)->first();
        if (!$contact) {
            return false;
        }

        $session = WhatsappSession::where('contact_id', $contact->id)->first();
        if (!$session) {
            return false;
        }

        $context = $session->context ?? [];
        // Mescla os dados novos com o contexto existente
        $context = array_merge($context, $contextData);
        $session->update([
            'context' => $context,
            'current_step' => $currentStep
        ]);
        return true;
    }

    /**
     * Endpoint POST para atualizar contexto e step da sessão
     * POST /api/whatsapp/atualizar-contexto-e-step { "wa_id": "...", "contextData": {...}, "currentStep": "..." }
     */
    public function atualizarContextoEStepSessao(Request $request)
    {
        $wa_id = $request->input('wa_id');
        $contextData = $request->input('contextData', []);
        $currentStep = $request->input('currentStep');

        if (empty($wa_id)) {
            return response()->json(['error' => 'wa_id é obrigatório', 'success' => false], 400);
        }

        if (empty($currentStep)) {
            return response()->json(['error' => 'currentStep é obrigatório', 'success' => false], 400);
        }

        $contact = WhatsappContact::where('wa_id', $wa_id)->first();
        if (!$contact) {
            return response()->json(['error' => 'Contato não encontrado', 'success' => false], 404);
        }

        $session = WhatsappSession::where('contact_id', $contact->id)->first();
        if (!$session) {
            return response()->json(['error' => 'Sessão não encontrada', 'success' => false], 404);
        }

        $context = $session->context ?? [];
        // Mescla os dados novos com o contexto existente
        $context = array_merge($context, $contextData);
        $session->update([
            'context' => $context,
            'current_step' => $currentStep
        ]);

        return response()->json([
            'success' => true,
            'wa_id' => $wa_id,
            'current_step' => $currentStep,
            'context' => $context
        ]);
    }

    /**
     * Endpoint POST para obter contagem de erros
     * POST /api/whatsapp/obter-contagem-erros { "wa_id": "..." }
     */
    public function obterContagemErros(Request $request)
    {
        $wa_id = $request->input('wa_id');

        if (empty($wa_id)) {
            return response()->json(['error' => 'wa_id é obrigatório', 'success' => false], 400);
        }

        $contact = WhatsappContact::where('wa_id', $wa_id)->first();
        if (!$contact) {
            return response()->json(['error' => 'Contato não encontrado', 'success' => false], 404);
        }

        $session = WhatsappSession::where('contact_id', $contact->id)->first();
        if (!$session) {
            return response()->json(['error' => 'Sessão não encontrada', 'success' => false], 404);
        }

        $context = $session->context ?? [];
        $errorCount = $context['error_count'] ?? 0;

        return response()->json([
            'success' => true,
            'wa_id' => $wa_id,
            'error_count' => $errorCount,
            'last_error' => $context['last_error'] ?? null
        ]);
    }

    /**
     * Endpoint POST para adicionar erro à sessão
     * POST /api/whatsapp/adicionar-erro { "wa_id": "...", "mensagemErro": "...", "step": "..." }
     */
    public function adicionarErroSessao(Request $request)
    {
        $wa_id = $request->input('wa_id');
        $mensagemErro = $request->input('mensagemErro');
        $step = $request->input('step');

        if (empty($wa_id)) {
            return response()->json(['error' => 'wa_id é obrigatório', 'success' => false], 400);
        }

        if (empty($mensagemErro)) {
            return response()->json(['error' => 'mensagemErro é obrigatório', 'success' => false], 400);
        }

        if (empty($step)) {
            return response()->json(['error' => 'step é obrigatório', 'success' => false], 400);
        }

        $contact = WhatsappContact::where('wa_id', $wa_id)->first();
        if (!$contact) {
            return response()->json(['error' => 'Contato não encontrado', 'success' => false], 404);
        }

        $session = WhatsappSession::where('contact_id', $contact->id)->first();
        if (!$session) {
            return response()->json(['error' => 'Sessão não encontrada', 'success' => false], 404);
        }

        $context = $session->context ?? [];

        // Incrementa contagem de erros
        $context['error_count'] = ($context['error_count'] ?? 0) + 1;

        // Armazena último erro
        $context['last_error'] = [
            'message' => $mensagemErro,
            'timestamp' => now()->toIso8601String(),
            'step' => $step
        ];

        // Mantém histórico dos últimos 5 erros
        if (!isset($context['errors'])) {
            $context['errors'] = [];
        }
        $context['errors'][] = $context['last_error'];
        $context['errors'] = array_slice($context['errors'], -5);

        // Atualiza a sessão
        $session->update(['context' => $context]);

        // Atualiza também no contato (histórico lifetime)
        $contact->update([
            'last_message' => 'Erro: ' . $mensagemErro,
            'last_message_at' => now()
        ]);

        \Log::warning('[FLUXO-DADOS] Erro registrado na sessão', [
            'wa_id' => $wa_id,
            'error_message' => $mensagemErro,
            'step' => $step,
            'error_count' => $context['error_count']
        ]);

        return response()->json([
            'success' => true,
            'wa_id' => $wa_id,
            'error_count' => $context['error_count'],
            'last_error' => $context['last_error'],
            'escalated' => $context['error_count'] >= 3
        ]);
    }

    /**
     * Rota para atualizar o step da sessão via n8n
     * Exemplo de chamada: POST /api/whatsapp/atualiza-step
     * Body: { "wa_id": "...", "step": "verifica_cpf" }
     */
    public function atualizaStepWebhook(Request $request)
    {
        $wa_id = $request->input('wa_id');
        $stepName = $request->input('step');
        $contact = WhatsappContact::where('wa_id', $wa_id)->first();
        if (!$contact) {
            return response()->json(['error' => 'Contato não encontrado'], 404);
        }
        $session = WhatsappSession::where('contact_id', $contact->id)->first();

        $session->current_step = $stepName;
        $session->save();
        return response()->json([
            'success' => true,
            'step_name' => $stepName
        ]);
    }

    public function webhook(Request $request)
    {

        // === Verificação inicial do Webhook (GET) ===
        if ($request->isMethod('get')) {
            $verify_token = 'qwdqw123234'; // mesmo token do Meta
            $mode = $request->query('hub_mode');
            $token = $request->query('hub_verify_token');
            $challenge = $request->query('hub_challenge');

            if ($mode === 'subscribe' && $token === $verify_token) {
                return response($challenge, 200);
            } else {
                return response('Token inválido', 403);
            }
        }

        // === Recebimento de mensagens (POST) ===
        if ($request->isMethod('post')) {
            $data = $request->all();


            $this->teste($data);


            return response('EVENT_RECEIVED', 200);
        }

        return response('Método não suportado', 405);
    }

    public function gerarTokenNeocobe()
    {
        $client = new \GuzzleHttp\Client(['verify' => false]);
        $headers = [
            'Content-Type' => 'application/json'
        ];
        $body = json_encode([
            'Login' => env('NEOCOBE_LOGIN'),
            'Password' => env('NEOCOBE_PASSWORD'),
            'ApiKey' => env('NEOCOBE_APIKEY')
        ]);
        $url = env('NEOCOBE_TOKEN_URL');
        $request = new \GuzzleHttp\Psr7\Request('POST', $url, $headers, $body);
        $res = $client->sendAsync($request)->wait();
        $tokenData = json_decode($res->getBody(), true);
        return $tokenData['access_token'] ?? null;
    }

    public function verificaDividaOuAcordo(Request $request)
    {
        $wa_id = $request->input('wa_id');
        $idGrupo = "1582";

        if (empty($wa_id)) {
            return response()->make('false', 200, ['Content-Type' => 'text/plain']);
        }

        // Busca o contato em contatoDados onde telefone == wa_id
        $contatoDados = ContatoDados::where('telefone', preg_replace('/\D/', '', $wa_id))->first();

        if (!$contatoDados || empty($contatoDados->document)) {
            return response()->make('false', 200, ['Content-Type' => 'text/plain']);
        }

        // Pega o document do contatoDados e limpa
        $cpfCnpj = preg_replace('/\D/', '', $contatoDados->document);

        if (empty($cpfCnpj) || empty($idGrupo)) {
            return response()->make('false', 200, ['Content-Type' => 'text/plain']);
        }

        $token = $this->gerarTokenNeocobe();
        if (!$token) {
            return response()->make('false', 200, ['Content-Type' => 'text/plain']);
        }

        $client = new \GuzzleHttp\Client(config: ['verify' => false]);
        $headers = [
            'apiKey' => env('NEOCOBE_APIKEY'),
            'Authorization' => 'Bearer ' . $token
        ];
        $url = 'https://datacob.thiagofarias.adv.br/api/negociacao/v1/consultar-divida-ativa-negociacao?cpfCnpj=' . $cpfCnpj . '&idGrupo=' . $idGrupo;
        $requestApi = new \GuzzleHttp\Psr7\Request('GET', $url, $headers);

        try {
            $res = $client->sendAsync($requestApi)->wait();
            $body = $res->getBody()->getContents();
            $dividaData = json_decode($body, true);

            // Se Success true, verifica acordos
            if (is_array($dividaData) && isset($dividaData['Success']) && $dividaData['Success'] === true) {
                $codigoCliente = $dividaData['NegociacaoDto'][0]['NrContrato'] ?? null;

                if ($codigoCliente) {
                    $acordoData = $this->obterAcordosPorCliente($codigoCliente);

                    if (is_array($acordoData) && isset($acordoData['mensagem']) && strtolower($acordoData['mensagem']) === 'nenhumacordo encontrado') {
                        // Tem dívida, mas não tem acordo
                        $this->atualizarContextoEStepSessaoInterno($wa_id, [
                            'divida_verificada' => true,
                            'tipo_resultado' => 'divida',
                            'divida_data' => $dividaData,
                            'codigo_cliente' => $codigoCliente,
                            'cpf_cnpj' => $cpfCnpj,
                            'verificacao_divida_at' => now()->toIso8601String(),
                        ], 'divida_verificada');
                        return response()->json(['tipo' => 'divida', 'divida' => $dividaData]);
                    } elseif (is_array($acordoData) && isset($acordoData[0]['codigoAcordo'])) {
                        // Tem acordo
                        $this->atualizarContextoEStepSessaoInterno($wa_id, [
                            'divida_verificada' => true,
                            'tipo_resultado' => 'acordo',
                            'acordo_data' => $acordoData,
                            'codigo_cliente' => $codigoCliente,
                            'cpf_cnpj' => $cpfCnpj,
                            'quantidade_acordos' => count($acordoData),
                            'verificacao_divida_at' => now()->toIso8601String(),
                        ], 'acordo_verificado');
                        return response()->json(['tipo' => 'acordo', 'acordo' => $acordoData]);
                    } else {
                        // Não encontrou acordo, retorna dívida
                        $this->atualizarContextoEStepSessaoInterno($wa_id, [
                            'divida_verificada' => true,
                            'tipo_resultado' => 'divida',
                            'divida_data' => $dividaData,
                            'codigo_cliente' => $codigoCliente,
                            'cpf_cnpj' => $cpfCnpj,
                            'verificacao_divida_at' => now()->toIso8601String(),
                        ], 'divida_verificada');
                        return response()->json(['tipo' => 'divida', 'divida' => $dividaData]);
                    }
                } else {
                    // Não tem códigoCliente, retorna dívida
                    $this->atualizarContextoEStepSessaoInterno($wa_id, [
                        'divida_verificada' => true,
                        'tipo_resultado' => 'divida',
                        'divida_data' => $dividaData,
                        'cpf_cnpj' => $cpfCnpj,
                        'verificacao_divida_at' => now()->toIso8601String(),
                    ], 'divida_verificada');
                    return response()->json(['tipo' => 'divida', 'divida' => $dividaData]);
                }
            } else {
                // Não tem dívida
                $this->atualizarContextoEStepSessaoInterno($wa_id, [
                    'divida_verificada' => true,
                    'tipo_resultado' => 'sem_divida',
                    'divida_data' => $dividaData,
                    'cpf_cnpj' => $cpfCnpj,
                    'verificacao_divida_at' => now()->toIso8601String(),
                ], 'sem_divida');
                return response()->json(['tipo' => 'sem_divida', 'divida' => $dividaData]);
            }
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $body = $e->getResponse()->getBody()->getContents();
            return response($body, 200)->header('Content-Type', 'application/json');
        }
    }

    /**
     * Consulta dados cadastrais na Neocobe
     */
    public function consultaDadosCadastrais($cpfCnpj, $token)
    {
        $cpfCnpj = preg_replace('/\D/', '', $cpfCnpj);
        $client = new \GuzzleHttp\Client(['verify' => false]);
        $headers = [
            'apiKey' => env('NEOCOBE_APIKEY'),
            'Authorization' => 'Bearer ' . $token
        ];
        $url = env('NEOCOBE_DADOS_URL', 'https://datacob.thiagofarias.adv.br/api/dados-cadastrais/v1?cpfCnpj=') . $cpfCnpj;
        $request = new \GuzzleHttp\Psr7\Request('GET', $url, $headers);
        try {
            $res = $client->sendAsync($request)->wait();
            $body = $res->getBody();
            $data = json_decode($body, true);
            return $data;
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            // Retorna apenas o texto da resposta (body)
            return $e->getResponse()->getBody()->getContents();
        }
    }

    /**
     * Verifica contratos por wa_id
     * Busca o CPF/CNPJ do contexto da sessão e retorna os contratos na tabela contato_dados
     * POST /api/whatsapp/verifica-contratos { wa_id: "..." }
     */
    public function verificaContratos(Request $request)
    {
        $wa_id = $request->input('wa_id');

        if (empty($wa_id)) {
            return response()->json(['error' => 'wa_id é obrigatório', 'success' => false], 400);
        }

        // Busca o contato em contatoDados onde telefone == wa_id
        $contatoDados = ContatoDados::where('telefone', preg_replace('/\D/', '', $wa_id))->first();

        if (!$contatoDados || empty($contatoDados->document)) {
            return response()->json([
                'error' => 'Contato não encontrado em contatoDados',
                'success' => false
            ], 404);
        }

        // Pega o document do contatoDados e limpa
        $cpfCnpjLimpo = preg_replace('/\D/', '', $contatoDados->document);

        // Busca os contratos na tabela contato_dados
        $contratos = ContatoDados::where('document', $cpfCnpjLimpo)
            ->orWhere('document', $contatoDados->document)
            ->get();

        $quantidade = $contratos->count();

        // Busca o contato WhatsApp e atualiza o contexto com os dados encontrados
        $contact = WhatsappContact::where('wa_id', $wa_id)->first();
        if ($contact) {
            $session = WhatsappSession::where('contact_id', $contact->id)->first();
            if ($session) {
                $context = $session->context ?? [];
                $context['contratos_verificados'] = true;
                $context['quantidade_contratos'] = $quantidade;
                $context['verificacao_contratos_at'] = now()->toIso8601String();
                $context['cpf_cnpj'] = $cpfCnpjLimpo;
                $session->update(['context' => $context]);
            }
        }

        return response()->json([
            'success' => true,
            'quantidade' => $quantidade,
            'contratos' => $contratos,
            'cpf_cnpj' => $cpfCnpjLimpo
        ]);
    }

    public function obterDocumentosAbertos(Request $request)
    {
        $wa_id = $request->input('wa_id');

        if (empty($wa_id)) {
            return response()->json(['error' => 'wa_id é obrigatório', 'success' => false], 400);
        }

        // Busca o contato em contatoDados onde telefone == wa_id
        $contatoDados = ContatoDados::where('telefone', preg_replace('/\D/', '', $wa_id))->first();

        if (!$contatoDados || empty($contatoDados->document)) {
            return response()->json([
                'error' => 'Contato não encontrado em contatoDados',
                'success' => false
            ], 404);
        }

        // Pega o document do contatoDados e limpa
        $cpfCnpjLimpo = preg_replace('/\D/', '', $contatoDados->document);

        // Busca os contratos na tabela contato_dados
        $contratos = ContatoDados::where('document', $cpfCnpjLimpo)
            ->orWhere('document', $contatoDados->document)
            ->get();

        if ($contratos->isEmpty()) {
            return response()->json([
                'error' => 'Nenhum contrato encontrado para este cliente',
                'success' => false,
                'cpf_cnpj_buscado' => $cpfCnpjLimpo
            ], 404);
        }

        // Obtém opções de parcelamento para cada contrato
        $parcelamentosResultados = [];
        $erros = [];
        $valorAVista = 0; // Valor da primeira parcela (à vista)

        foreach ($contratos as $contrato) {
            $codigoCarteira = $contrato->carteira;
            $pessoaCodigo = $contrato->cod_cliente;

            $parcelamentos = $this->obterOpcoesParcelamentoHavan(
                $codigoCarteira,
                $pessoaCodigo
            );

            if ($parcelamentos) {
                $parcelamentosResultados[] = [
                    'contrato' => $contrato->toArray(),
                    'parcelamentos' => $parcelamentos,
                    'codigo_carteira' => $codigoCarteira,
                    'pessoa_codigo' => $pessoaCodigo
                ];
                
                // Extrai o valor à vista (primeira parcela) do primeiro parcelamento
                \Log::info('DEBUG obterDocumentosAbertos - Verificando parcelamentos', [
                    'parcelamentos_keys' => array_keys($parcelamentos),
                    'parcelamentos_data_existe' => isset($parcelamentos['data']),
                    'parcelamentos_completo' => json_encode($parcelamentos)
                ]);

                if (empty($valorAVista) && isset($parcelamentos['data'][0]['parcelamento'][0]['valorTotal'])) {
                    $valorAVista = $parcelamentos['data'][0]['parcelamento'][0]['valorTotal'];
                    \Log::info('DEBUG obterDocumentosAbertos - Valor à vista encontrado', [
                        'valorAVista' => $valorAVista
                    ]);
                } else {
                    \Log::warning('DEBUG obterDocumentosAbertos - Valor à vista NÃO encontrado', [
                        'valorAVista_atual' => $valorAVista,
                        'data_existe' => isset($parcelamentos['data']),
                        'data_0_existe' => isset($parcelamentos['data'][0]) ?? false,
                        'parcelamento_existe' => isset($parcelamentos['data'][0]['parcelamento']) ?? false,
                        'valorTotal_existe' => isset($parcelamentos['data'][0]['parcelamento'][0]['valorTotal']) ?? false
                    ]);
                }
            } else {
                $erros[] = [
                    'contrato_id' => $contrato->id,
                    'codigo_carteira' => $codigoCarteira,
                    'erro' => 'Falha ao obter opções de parcelamento da API'
                ];
                \Log::error('DEBUG obterDocumentosAbertos - Parcelamentos vazios', [
                    'contrato_id' => $contrato->id,
                    'codigo_carteira' => $codigoCarteira
                ]);
            }
        }

        \Log::info('DEBUG obterDocumentosAbertos - Após loop de contratos', [
            'valorAVista_final' => $valorAVista,
            'parcelamentosResultados_count' => count($parcelamentosResultados)
        ]);

        // Busca o contato WhatsApp e atualiza o contexto da sessão
        $contact = WhatsappContact::where('wa_id', $wa_id)->first();
        if ($contact) {
            $session = WhatsappSession::where('contact_id', $contact->id)->first();
            if ($session) {
                $context = $session->context ?? [];
                
                \Log::info('DEBUG ANTES DE SALVAR - Context state', [
                    'valor_antes' => $context['valor-atual-da-divida-a-vista'] ?? 'NAO_EXISTE',
                    'valorAVista_variavel' => $valorAVista
                ]);
                
                $context['parcelamentos_verificados'] = true;
                $context['parcelamentos_resultados_count'] = count($parcelamentosResultados);
                $context['verificacao_parcelamentos_at'] = now()->toIso8601String();
                $context['valor-atual-da-divida-a-vista'] = $valorAVista; // Salva no contexto
                $context['cpf_cnpj'] = $cpfCnpjLimpo; // Salva no contexto
                $context['nome'] = $contrato->nome; // Salva no contexto
                
                \Log::info('DEBUG DEPOIS DE ATRIBUIR - Context array', [
                    'valor_no_array' => $context['valor-atual-da-divida-a-vista'],
                    'array_completo' => json_encode($context)
                ]);
                
                if (!empty($erros)) {
                    $context['parcelamentos_erros'] = $erros;
                }
                
                $session->update(['context' => $context]);
                
                \Log::info('DEBUG DEPOIS DE SALVAR - Verificando sessão', [
                    'session_id' => $session->id,
                    'context_salvo' => json_encode($session->context),
                    'valor_no_db' => $session->context['valor-atual-da-divida-a-vista'] ?? 'NAO_EXISTE'
                ]);
            }
        }

        // Calcula data de vencimento com lógica de dias úteis
        $dataVencimento = $this->calcularDataVencimentoComDiasUteis();

        return response()->json([
            'success' => true,
            'cpf_cnpj' => $cpfCnpjLimpo,
            'quantidade_contratos' => $contratos->count(),
            'parcelamentos_encontrados' => count($parcelamentosResultados),
            'parcelamentos' => $parcelamentosResultados,
            'erros' => $erros,
            'data_vencimento' => $dataVencimento
        ], 200);
    }

    /**
     * Calcula data de vencimento com 5 dias úteis a partir de hoje
     * Se cair no fim de semana (sábado 4 ou domingo 5), adiciona dias até segunda
     */
    private function calcularDataVencimentoComDiasUteis()
    {
        $data = now();
        $diasAdicionados = 0;
        $diasUteis = 0;

        // Adiciona dias até completar 5 dias úteis
        while ($diasUteis < 5) {
            $data = $data->addDay();
            $diasAdicionados++;
            
            // Verifica se é dia útil (segunda a sexta = 1 a 5)
            if ($data->dayOfWeek >= 1 && $data->dayOfWeek <= 5) {
                $diasUteis++;
            }
        }

        // Verifica se caiu em sábado (6) ou domingo (0)
        if ($data->dayOfWeek == 6) { // Sábado
            $data = $data->addDays(2); // Pula para segunda
        } elseif ($data->dayOfWeek == 0) { // Domingo
            $data = $data->addDay(); // Pula para segunda
        }

        return $data->format('d/m/Y');
    }

    private function obterOpcoesParcelamentoHavan($codigoCarteira, $pessoaCodigo)
    {
        // Determina codigoUsuarioCarteiraCobranca baseado no codigoCarteira
        $codigoCarteira = (int) $codigoCarteira;
        switch ($codigoCarteira) {
            case 870:
            case 871:
            case 872:
            case 873:
            case 874:
                $codigoUsuarioCarteiraCobranca = 24;
                break;
            case 875:
                $codigoUsuarioCarteiraCobranca = 30;
                break;
            default:
                $codigoUsuarioCarteiraCobranca = 24; // valor padrão
        }

        $client = new \GuzzleHttp\Client();
        $headers = [
            'Content-Type' => 'application/json',
        ];
        
        // Calcula data da primeira parcela com 5 dias úteis a partir de hoje
        $dataPrimeiraParcela = $this->calcularDataVencimentoComDiasUteis();
        // Converte de d/m/Y para Y-m-d
        $dataPrimeiraParcelaFormatada = \DateTime::createFromFormat('d/m/Y', $dataPrimeiraParcela)->format('Y-m-d');
        
        $body = json_encode([
            'codigoUsuarioCarteiraCobranca' => $codigoUsuarioCarteiraCobranca,
            'codigoCarteiraCobranca' => $codigoCarteira,
            'pessoaCodigo' => (string) $pessoaCodigo,
            'dataPrimeiraParcela' => $dataPrimeiraParcelaFormatada
        ]);

        $request = new \GuzzleHttp\Psr7\Request(
            'GET',
            'https://havan-request.betasolucao.com.br/api/obter-opcoes-parcelamento',
            $headers,
            $body
        );

        try {
            $res = $client->sendAsync($request)->wait();
            $responseBody = $res->getBody()->getContents();
            $data = json_decode($responseBody, true);

            // Verifica se a resposta indica que não há opções
            if (is_array($data) && isset($data['mensagem'])) {
                \Log::warning('Resposta Havan obter-opcoes-parcelamento: ' . $data['mensagem']);
                return false;
            }

            return $data;
        } catch (\Exception $e) {
            \Log::error('Erro ao obter opções de parcelamento Havan: ' . $e->getMessage());
            return false;
        }
    }

    private function obterDocumentosHavan($codigoCliente, $codigoCarteira)
    {
        $client = new \GuzzleHttp\Client();
        $headers = [
            'Content-Type' => 'application/json',
        ];
        $body = json_encode([
            'codigoCliente' => (string) $codigoCliente,
            'codigoCarteiraCobranca' => (string) $codigoCarteira
        ]);

        $request = new \GuzzleHttp\Psr7\Request(
            'GET',
            'https://havan-request.betasolucao.com.br/api/obter-documentos-aberto',
            $headers,
            $body
        );

        try {
            $res = $client->sendAsync($request)->wait();
            $responseBody = $res->getBody()->getContents();
            $data = json_decode($responseBody, true);

            // Verifica se a resposta indica que não há documentos
            if (isset($data['mensagem']) && stripos($data['mensagem'], 'nenhum') !== false) {
                return false;
            }

            return $data;
        } catch (\Exception $e) {
            \Log::error('Erro ao obter documentos Havan: ' . $e->getMessage());
            return false;
        }
    }

    public function verificaCliente(Request $request)
    {
        $cpfCnpjDigitado = $request->input('cpfCnpj');
        $wa_id = $request->input('wa_id');

        if (empty($wa_id)) {
            return response()->make('false', 200, ['Content-Type' => 'text/plain']);
        }

        // Busca o contato em contatoDados onde telefone == wa_id
        $contatoDados = ContatoDados::where('telefone', preg_replace('/\D/', '', $wa_id))->first();

        if (!$contatoDados || empty($contatoDados->document)) {
            return response()->make('false', 200, ['Content-Type' => 'text/plain']);
        }

        // Limpa o CPF digitado (remove - e .)
        $cpfCnpjLimpo = preg_replace('/[-.]/', '', $cpfCnpjDigitado);
        $cpfCnpjLimpo = preg_replace('/\D/', '', $cpfCnpjLimpo); // Remove qualquer outro caractere especial

        // Pega o document do contato e extrai os 3 últimos dígitos
        $documentoArmazenado = preg_replace('/\D/', '', $contatoDados->document);
        $ultimosTresDigitos = substr($documentoArmazenado, -3);

        // Pega os 3 últimos dígitos do CPF digitado
        $ultimosTresDigitadosDigitados = substr($cpfCnpjLimpo, -3);

        // Compara os 3 últimos dígitos
        if ($ultimosTresDigitadosDigitados !== $ultimosTresDigitos) {
            \Log::warning('CPF digitado não corresponde ao cadastro', [
                'wa_id' => $wa_id,
                'ultimos_3_digitados' => $ultimosTresDigitadosDigitados,
                'ultimos_3_armazenados' => $ultimosTresDigitos
            ]);
            return response()->make('false', 200, ['Content-Type' => 'text/plain']);
        }

        // CPF validado! Atualiza o contexto da sessão
        $contact = WhatsappContact::where('wa_id', $wa_id)->first();
        if ($contact) {
            $session = WhatsappSession::where('contact_id', $contact->id)->first();
            if ($session) {
                $context = $session->context ?? [];
                $context['cliente_verificado'] = true;
                $context['cpf_cnpj'] = $cpfCnpjLimpo;
                $context['documento_banco'] = $documentoArmazenado;
                $context['verificacao_at'] = now()->toIso8601String();
                $session->update(['context' => $context]);
            }
        }

        // Retorna sucesso
        return response()->json([
            'success' => true,
            'cliente_verificado' => true,
            'documento' => $documentoArmazenado,
            'nome' => $contatoDados->nome ?? ''
        ]);
    }

    private function obterAcordosPorCliente($codigoCliente)
    {
        $client = new \GuzzleHttp\Client();
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'meuTokenSecreto123', // Substitua pelo token correto
        ];
        $body = json_encode([
            'codigoCliente' => $codigoCliente
        ]);
        $request = new \GuzzleHttp\Psr7\Request(
            'GET',
            'https://havan-request.betasolucao.com.br/api/obter-acordos-por-cliente',
            $headers,
            $body
        );
        try {
            $res = $client->sendAsync($request)->wait();
            $responseBody = $res->getBody()->getContents();
            $data = json_decode($responseBody, true);
            // dd($data);
            if (isset($data['mensagem']) && strtolower($data['mensagem']) === 'nenhumacordo encontrado') {
                return false;
            }
            return $data;
        } catch (\Exception $e) {
            dd($e->getMessage());
            \Log::error(message: 'Erro ao obter acordos por cliente: ' . $e->getMessage());
            return false;
        }
    }

    private function replacePlaceholders($text, $context, $name)
    {
        // Convert escaped newline sequences stored as literal backslash-n into real newlines
        if (is_string($text)) {
            $text = str_replace(["\\r\\n", "\\n", "\\r"], ["\r\n", "\n", "\r"], $text);
        }
        $replacements = [
            '{{NomeBanco}}' => 'Banco Exemplo',
            '{{primeironome}}' => explode(' ', $name)[0] ?? 'Cliente',
            '{{qtdContratos}}' => $context['qtdContratos'] ?? '0',
            '{{valorTotal}}' => $context['valorTotal'] ?? 'R$ 0,00',
            '{{data}}' => $context['data'] ?? date('d/m/Y'),
            '{{qtdAcordos}}' => $context['qtdAcordos'] ?? '0',
            '{{codigoBarras}}' => $context['codigoBarras'] ?? '123456789012345678901234567890123456789012345678',
            '{{dataVencimento}}' => $context['dataVencimento'] ?? date('d/m/Y'),
        ];
        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }


    private function getStepFluxo($id)
    {
        Log::info('getStepFluxo debug', [
            'negFlow' => $id,
            'step_number' => $id,
        ]);
        return WhatsappFlowStep::where('id', $id)->first();
    }



    private function validateInput($input, $expected)
    {
        if (!$expected)
            return true;
        switch ($expected) {
            case 'cpf':
                // Temporariamente desabilitada validação de CPF/CNPJ — aceitar qualquer entrada
                return true;
            case 'numero':
                return is_numeric(value: $input);
            case 'sim_nao':
                return in_array(strtolower($input), ['sim', 'não', 'nao', 's', 'n']);
            case 'botao':
                // Assume que é id de botão
                return true;
            case 'texto':
                return strlen($input) > 0;
            default:
                return true;
        }
    }

    private function isValidCpf(string $cpf): bool
    {
        if (strlen($cpf) != 11)
            return false;
        if (preg_match('/^(?:0{11}|1{11}|2{11}|3{11}|4{11}|5{11}|6{11}|7{11}|8{11}|9{11})$/', $cpf))
            return false;
        $sum = 0;
        for ($i = 0, $j = 10; $i < 9; $i++, $j--)
            $sum += (int) $cpf[$i] * $j;
        $rest = $sum % 11;
        $digit1 = ($rest < 2) ? 0 : 11 - $rest;
        if ((int) $cpf[9] !== $digit1)
            return false;
        $sum = 0;
        for ($i = 0, $j = 11; $i < 10; $i++, $j--)
            $sum += (int) $cpf[$i] * $j;
        $rest = $sum % 11;
        $digit2 = ($rest < 2) ? 0 : 11 - $rest;
        return (int) $cpf[10] === $digit2;
    }
    private function isValidCnpj(string $cnpj): bool
    {
        if (strlen($cnpj) != 14)
            return false;
        if (preg_match('/^(?:0{14}|1{14}|2{14}|3{14}|4{14}|5{14}|6{14}|7{14}|8{14}|9{14})$/', $cnpj))
            return false;
        $t = substr($cnpj, 0, 12);
        $calculardigito = function ($t) {
            $c = 0;
            $j = 5;
            for ($i = 0; $i < strlen($t); $i++) {
                $c += (int) $t[$i] * $j;
                $j = ($j == 2) ? 9 : $j - 1;
            }
            $r = $c % 11;
            return ($r < 2) ? 0 : 11 - $r;
        };
        $d1 = $calculardigito($t);
        $t .= $d1;
        $d2 = $calculardigito($t);
        return ($cnpj[12] == $d1 && $cnpj[13] == $d2);
    }
    private function isValidCpfCnpj(string $document): bool
    {
        $len = strlen($document);
        if ($len == 11) {
            return $this->isValidCpf($document);
        } elseif ($len == 14) {
            return $this->isValidCnpj($document);
        }
        return false;
    }

    /**
     * Formata e mascara o CPF mostrando apenas os 8 primeiros dígitos
     * Exemplo: 02919023918 → 029.190.23X-XX
     * @param string $cpf - CPF com ou sem formatação
     * @return string - CPF formatado e mascarado
     */
    private function formatarCpfMascarado($cpf)
    {
        if (empty($cpf)) {
            return '';
        }

        // Remove caracteres especiais
        $cpf = preg_replace('/\D/', '', $cpf);

        // Se não tiver 11 dígitos, retorna vazio
        if (strlen($cpf) != 11) {
            return '';
        }

        // Pega os 8 primeiros dígitos visíveis e mascara os 3 últimos
        $primeirosOito = substr($cpf, 0, 8); // Primeiros 8 dígitos
        $mascarado = $primeirosOito . 'XXX'; // 8 primeiros + 3 X

        // Formata no padrão CPF: XXX.XXX.XXX-XX
        return substr($mascarado, 0, 3) . '.' . substr($mascarado, 3, 3) . '.' . substr($mascarado, 6, 2) . 'X-XX';
    }
    // Função para enviar mensagem via WhatsApp Cloud API
    private function sendMessage($to, $body, $overridePhoneNumberId = null)
    {
        // Tenta obter token e phoneNumberId salvos no banco (configurados pelo fluxo de auth)
        $config = DB::table('whatsapp')->first();
        $token = $config->access_token ?? env('WHATSAPP_TOKEN');
        // Prioriza override (session phone_number_id), depois banco, depois env
        $phoneNumberId = $overridePhoneNumberId ?? ($config->phone_number_id ?? env('WHATSAPP_PHONE_ID'));

        if (empty($token) || empty($phoneNumberId)) {
            Log::error('sendMessage: token ou phoneNumberId não configurado.');
            return false;
        }

        $data = [
            "messaging_product" => "whatsapp",
            "to" => $to,
            "type" => "text",
            "text" => ["body" => $body]
        ];

        $response = Http::withToken(token: $token)
            ->post("https://graph.facebook.com/v18.0/{$phoneNumberId}/messages", $data);

        return true;
    }
    public function sendMenuOptions(string $wa_id, ?string $phone_number_id, array $options, string $title = null)
    {
        // token / phoneNumberId (mesma lógica do sendMessage)
        $config = DB::table('whatsapp')->first();
        $token = $config->access_token ?? env('WHATSAPP_TOKEN');
        $phoneNumberId = $phone_number_id ?? ($config->phone_number_id ?? env('WHATSAPP_PHONE_ID'));

        if (empty($token) || empty($phoneNumberId)) {
            Log::error('sendMenuOptions: token ou phoneNumberId não configurado.');
            return false;
        }

        // Normaliza options: array de ['id'=>'x','title'=>'Y']
        $normalized = array_map(function ($o) {
            if (is_array($o))
                return $o;
            return ['id' => (string) $o, 'title' => (string) $o];
        }, $options);

        // Always send an interactive list payload. Buttons branch removed per request.

        // Se tiver mais de 3 opções, usa interactive type=list (mais flexível)
        $rows = [];
        foreach ($normalized as $idx => $opt) {
            $rows[] = [
                'id' => $opt['id'],
                'title' => $opt['title'],
                'description' => ''
            ];
        }

        $originalTitle = is_string($title) ? trim($title) : '';

        // If there's a title/prompt text, send it first as a normal text message
        // so the interactive list is sent after (user requested this behavior).
        if (!empty($originalTitle)) {
            $this->sendMessage($wa_id, $originalTitle, $phoneNumberId);
        }


        // Section title stricter limit
        $sectionTitle = 'Opções';
        if (!empty($originalTitle)) {
            // try to derive a short section title from the original (trim to 24)
            $candidate = preg_replace('/\s+@\w+/', '', $originalTitle); // remove simple @placeholders
            $candidate = trim($candidate);
            if (!empty($candidate)) {
                $sectionTitle = mb_substr($candidate, 0, 24);
            }
        }
        if (mb_strlen($sectionTitle) > 24) {
            $sectionTitle = mb_substr($sectionTitle, 0, 21) . '...';
        }

        // Ensure each row title fits within 24 chars
        foreach ($rows as &$r) {
            if (!empty($r['title'])) {
                $r['title'] = trim($r['title']);
                if (mb_strlen($r['title']) > 24) {
                    $r['title'] = mb_substr($r['title'], 0, 21) . '...';
                }
            }
        }
        unset($r);

        $section = [
            'title' => $sectionTitle,
            'rows' => $rows
        ];

        $body = [
            'messaging_product' => 'whatsapp',
            'to' => $wa_id,
            'type' => 'interactive',
            'interactive' => [
                'type' => 'list',
                'header' => [
                    'type' => 'text',
                    'text' => ''
                ],
                'body' => [
                    'text' => 'Escolha uma opção abaixo:'
                ],
                'action' => [
                    'button' => 'Ver opções',
                    'sections' => [$section]
                ]
            ]
        ];

        // Log::info(message: 'sendMenuOptions body: ' . json_encode($body));
        $url = "https://graph.facebook.com/v18.0/{$phoneNumberId}/messages";
        $res = Http::withToken(token: $token)->post($url, $body);
        Log::info('sendMenuOptions response: ' . $res->body());
        return true;
    }
    public function showForm()
    {
        $data = DB::table('whatsapp')->first();
        return view('whatsapp.connect', compact('data'));
    }

    /**
     * Armazenar acordo via WhatsApp
     * POST /api/whatsapp/store-acordo
     * 
     * Exemplo de requisição:
     * {
     *   "wa_id": "551186123660",
     *   "texto": "Acordo de negociação de dívida no Cartão Havan. Valor da dívida: {{valor_divida}}"
     * }
     * 
     * Placeholders disponíveis no texto:
     * {{valor_divida}} - Valor total da dívida (VlDivida)
     * {{cpf_cnpj}} - CPF/CNPJ do cliente
     * {{nome_cliente}} - Nome do cliente
     * {{atraso_dias}} - Dias de atraso
     * {{data_vencimento}} - Data de vencimento
     */
    public function storeAcordo(Request $request)
    {
        try {
            $wa_id = $request->input('wa_id');

            // Valida wa_id obrigatório
            if (empty($wa_id)) {
                return response()->json([
                    'success' => false,
                    'error' => 'wa_id é obrigatório'
                ], 400);
            }

            // Busca o contato WhatsApp pelo wa_id
            $whatsappContact = WhatsappContact::where('wa_id', $wa_id)->first();
            if (!$whatsappContact) {
                Log::warning(message: 'Contato WhatsApp não encontrado para wa_id: ' . $wa_id);
                return response()->json([
                    'success' => false,
                    'error' => 'Contato WhatsApp não encontrado'
                ], 404);
            }

            // Busca os dados do contato em contato_dados pelo telefone ou nome
            $contatoDados = ContatoDados::where('telefone', 'LIKE', '%' . $whatsappContact->wa_id)->first();

            if (!$contatoDados) {
                Log::warning('Dados do contato não encontrados para wa_id: ' . $wa_id);
                return response()->json([
                    'success' => false,
                    'error' => 'Dados do contato não encontrados'
                ], 404);
            }

            // Remove caracteres especiais do documento
            $documentoLimpo = preg_replace('/\D/', '', $contatoDados->document);

            // Verifica se já existe acordo com este documento
            $acordoExistente = Acordo::where('documento', $documentoLimpo)->first();
            if ($acordoExistente) {
                Log::warning('Tentativa de criar acordo com documento duplicado: ' . $documentoLimpo);
                return response()->json([
                    'success' => false,
                    'error' => 'Já existe um acordo cadastrado para este documento',
                    'acordo_existente' => $acordoExistente
                ], 409);
            }

            // Obtém contexto da sessão para extrair dados
            $session = WhatsappSession::where('contact_id', $whatsappContact->id)->first();
            $context = $session ? ($session->context ?? []) : [];

            // Extrai dados do contexto para substituir placeholders
            $valorDivida = 0;
            $atrasoDias = 0;
            $dataVencimento = '';
            $nomeCliente = $contatoDados->nome;

            // Tenta extrair valor da dívida do contexto (já calculado no obterDocumentosAbertos)
            $valorDivida = $context['valor-atual-da-divida-a-vista'] ?? 0;

            // Tenta extrair data de vencimento e atraso da divida_data
            if (!empty($context['divida_data']) && is_array($context['divida_data'])) {
                $dividaData = $context['divida_data'];
                if (!empty($dividaData['NegociacaoDto']) && is_array($dividaData['NegociacaoDto'])) {
                    $negociacao = $dividaData['NegociacaoDto'][0] ?? [];

                    // Tenta extrair data de vencimento e atraso
                    if (!empty($negociacao['Parcelas']) && is_array($negociacao['Parcelas'])) {
                        $parcela = $negociacao['Parcelas'][0] ?? [];
                        $atrasoDias = $parcela['Atraso'] ?? 0;
                        $dataVencimento = $parcela['DtVencimento'] ?? '';
                    }
                }
            }

            // Monta texto automaticamente com dados do contexto
            $textoFormatado = "acordo a vista: R$ " . number_format($valorDivida, 2, ',', '.') . "\n";


            // Prepara dados validados para criar o acordo
            $validated = [
                'documento' => $documentoLimpo,
                'nome' => $nomeCliente,
                'telefone' => $contatoDados->telefone,
                'phone_number_id' => $request->input('phone_number_id'),
                'status' => 'pendente',
                'texto' => $textoFormatado
            ];

            // Cria o novo acordo
            $acordo = Acordo::create($validated);

            Log::info('✓ Acordo criado com sucesso via WhatsApp: ID ' . $acordo->id . ' - ' . $acordo->nome . ' (' . $acordo->documento . ') - Valor: R$ ' . number_format($valorDivida, 2, ',', '.'));

            // Atualiza contexto da sessão do WhatsApp
            if ($session) {
                $context['acordo_criado'] = true;
                $context['acordo_id'] = $acordo->id;
                $context['acordo_status'] = $acordo->status;
                $context['acordo_data'] = now()->toIso8601String();
                $context['acordo_valor'] = $valorDivida;
                $session->update(['context' => $context]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Acordo criado com sucesso',
                'data' => $acordo,
                'id' => $acordo->id,
                'valor_divida' => $valorDivida,
                'atraso_dias' => $atrasoDias
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Validação falhou ao criar acordo: ' . json_encode($e->errors()));
            return response()->json([
                'success' => false,
                'error' => 'Erro de validação',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('✗ Erro ao criar acordo via WhatsApp: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao criar acordo',
                'message' => $e->getMessage()
            ], 400);
        }
    }
    public function storeAcordoParcelado(Request $request)
    {
        try {
            $wa_id = $request->input('wa_id');

            // Valida wa_id obrigatório
            if (empty($wa_id)) {
                return response()->json([
                    'success' => false,
                    'error' => 'wa_id é obrigatório'
                ], 400);
            }

            // Busca o contato WhatsApp pelo wa_id
            $whatsappContact = WhatsappContact::where('wa_id', $wa_id)->first();
            if (!$whatsappContact) {
                Log::warning(message: 'Contato WhatsApp não encontrado para wa_id: ' . $wa_id);
                return response()->json([
                    'success' => false,
                    'error' => 'Contato WhatsApp não encontrado'
                ], 404);
            }

            // Busca os dados do contato em contato_dados pelo telefone ou nome
            $contatoDados = ContatoDados::where('telefone', 'LIKE', '%' . $whatsappContact->wa_id)->first();

            if (!$contatoDados) {
                Log::warning('Dados do contato não encontrados para wa_id: ' . $wa_id);
                return response()->json([
                    'success' => false,
                    'error' => 'Dados do contato não encontrados'
                ], 404);
            }

            // Remove caracteres especiais do documento
            $documentoLimpo = preg_replace('/\D/', '', $contatoDados->document);

            // Verifica se já existe acordo com este documento
            $acordoExistente = Acordo::where('documento', $documentoLimpo)->first();
            if ($acordoExistente) {
                Log::warning('Tentativa de criar acordo com documento duplicado: ' . $documentoLimpo);
                return response()->json([
                    'success' => false,
                    'error' => 'Já existe um acordo cadastrado para este documento',
                    'acordo_existente' => $acordoExistente
                ], 409);
            }

            
            $nomeCliente = $contatoDados->nome;


            // Monta texto automaticamente com dados do contexto
            $textoFormatado = $request->input('texto');


            // Prepara dados validados para criar o acordo
            $validated = [
                'documento' => $documentoLimpo,
                'nome' => $nomeCliente,
                'telefone' => $contatoDados->telefone,
                'phone_number_id' => $request->input('phone_number_id'),
                'status' => 'pendente',
                'texto' => $textoFormatado
            ];

            // Cria o novo acordo
            $acordo = Acordo::create($validated);

        

            return response()->json([
                'success' => true,
                'message' => 'Acordo criado com sucesso',
                'data' => $acordo,
                'id' => $acordo->id
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Validação falhou ao criar acordo: ' . json_encode($e->errors()));
            return response()->json([
                'success' => false,
                'error' => 'Erro de validação',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('✗ Erro ao criar acordo via WhatsApp: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Erro ao criar acordo',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function authFacebook()
    {
        $data = DB::table('whatsapp')->first();
        if (empty($data->app_id) || empty($data->redirect_uri)) {
            return redirect()->back()->with('error', 'App ID ou Redirect URI não configurados.');
        }
        $fbAuthUrl = 'https://www.facebook.com/v21.0/dialog/oauth?client_id=' . urlencode($data->app_id)
            . '&redirect_uri=' . urlencode($data->redirect_uri)
            . '&response_type=code'
            . '&scope=pages_show_list,instagram_basic,instagram_manage_comments,pages_read_engagement';
        return redirect()->away($fbAuthUrl);
    }

    public function save(Request $request)
    {
        $validated = $request->validate([
            'app_id' => 'nullable|string',
            'app_secret' => 'nullable|string',
            'redirect_uri' => 'nullable|string',
            'access_token' => 'nullable|string',
            'phone_number_id' => 'nullable|string',
            // 'refresh_token' => 'nullable|string',
            // 'token_expires_at' => 'nullable|date',
            'is_connected' => 'boolean',
        ]);
        DB::table('whatsapp')->updateOrInsert(['id' => 1], $validated);
        return redirect()->back()->with('success', 'Dados salvos com sucesso!');
    }
    public function callback(Request $request)
    {
        $code = $request->input('code');
        if (!$code) {
            return redirect()->route('whatsapp.connect')->with('error', 'Código de autorização não recebido.');
        }
        $data = DB::table('whatsapp')->first();
        if (empty($data->app_id) || empty($data->app_secret) || empty($data->redirect_uri)) {
            return redirect()->route('whatsapp.connect')->with('error', 'Configuração incompleta.');
        }

        // Troca o código pelo access_token
        $tokenUrl = 'https://graph.facebook.com/v18.0/oauth/access_token';
        $params = [
            'client_id' => $data->app_id,
            'redirect_uri' => $data->redirect_uri,
            'client_secret' => $data->app_secret,
            'code' => $code,
        ];

        $client = new \GuzzleHttp\Client();
        try {
            $response = $client->get($tokenUrl, ['query' => $params]);
            $body = json_decode($response->getBody(), true);

            $accessToken = $body['access_token'] ?? null;
            if ($accessToken) {
                // Troca imediatamente por token de longa duração
                $exchangeUrl = 'https://graph.facebook.com/v18.0/oauth/access_token';
                $exchangeParams = [
                    'grant_type' => 'fb_exchange_token',
                    'client_id' => $data->app_id,
                    'client_secret' => $data->app_secret,
                    'fb_exchange_token' => $accessToken,
                ];
                $exchangeResponse = $client->get($exchangeUrl, ['query' => $exchangeParams]);
                $exchangeBody = json_decode($exchangeResponse->getBody(), true);
                $longAccessToken = $exchangeBody['access_token'] ?? $accessToken;
                $expiresAt = isset($exchangeBody['expires_in']) ? now()->addSeconds($exchangeBody['expires_in']) : null;
                DB::table('whatsapp')->updateOrInsert(['id' => 1], [
                    'access_token' => $longAccessToken,
                    'token_expires_at' => $expiresAt,
                    'is_connected' => true,
                ]);
                return redirect()->route('whatsapp.connect')->with('success', 'Conectado com sucesso!');
            } else {
                return redirect()->route('whatsapp.connect')->with('error', 'Não foi possível obter o token.');
            }
        } catch (\Exception $e) {
            return redirect()->route('whatsapp.connect')->with('error', 'Erro ao conectar: ' . $e->getMessage());
        }
    }
    public function exchangeToken()
    {
        $data = DB::table('whatsapp')->first();
        if (empty($data->access_token) || empty($data->app_id) || empty($data->app_secret)) {
            return redirect()->route('whatsapp.connect')->with('error', 'Token ou credenciais não configurados.');
        }
        $tokenUrl = 'https://graph.facebook.com/v18.0/oauth/access_token';
        $params = [
            'grant_type' => 'fb_exchange_token',
            'client_id' => $data->app_id,
            'client_secret' => $data->app_secret,
            'fb_exchange_token' => $data->access_token,
        ];
        $client = new \GuzzleHttp\Client();
        try {
            $response = $client->get($tokenUrl, ['query' => $params]);
            $body = json_decode($response->getBody(), true);
            $accessToken = $body['access_token'] ?? null;
            $expiresAt = isset($body['expires_in']) ? now()->addSeconds($body['expires_in']) : null;
            if ($accessToken) {
                DB::table('whatsapp')->updateOrInsert(['id' => 1], [
                    'access_token' => $accessToken,
                    'token_expires_at' => $expiresAt,
                    'is_connected' => true,
                ]);
                return redirect()->route('whatsapp.connect')->with('success', 'Token renovado com sucesso!');
            } else {
                return redirect()->route('whatsapp.connect')->with('error', 'Não foi possível renovar o token.');
            }
        } catch (\Exception $e) {
            return redirect()->route('whatsapp.connect')->with('error', 'Erro ao renovar token: ' . $e->getMessage());
        }
    }

    function gerarToken()
    {
        // Inicializa a sessão cURL
        $curl = curl_init();

        // Configurações da requisição cURL
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://cobrancaexternaauthapi.apps.havan.com.br/token', // URL do endpoint de autenticação
            CURLOPT_RETURNTRANSFER => true, // Retorna a resposta como string
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST', // Tipo da requisição
            CURLOPT_POSTFIELDS => 'grant_type=password&client_id=bd210e1b-dac2-49b0-a9c4-7c5e1b0b241f&username=THF&password=3cr1O35JfhQ8vBO', // Parâmetros do POST
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/x-www-form-urlencoded' // Tipo de conteúdo
            ),
        ));

        // Executa a requisição e captura a resposta
        $response = curl_exec($curl);

        // Verifica se ocorreu erro na requisição cURL
        if ($response === false) {
            echo 'Erro cURL: ' . curl_error($curl);
            return null;
        }

        // Fecha a sessão cURL
        curl_close($curl);

        // Converte a resposta JSON para um array PHP
        $responseData = json_decode($response, true);
        // Verifica se a resposta contém o token
        if (isset($responseData['access_token'])) {
            return $responseData['access_token'];
        } else {
            echo 'Erro ao obter o token: ' . $responseData;
            return null;
        }
    }
}
