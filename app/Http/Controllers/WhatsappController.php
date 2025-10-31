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
                // Primeira mensagem: cria a sessão
                $session = WhatsappSession::create([
                    'contact_id' => $contact->id,
                    'current_step_id' => null,
                    'context' => [],
                    'phone_number_id' => $phoneNumberId ?? null
                ]);
                // Atualiza para o próximo step (exemplo: verifica_cpf)
                $step = $this->atualizaStep($session, 'verifica_cpf');
                echo json_encode([
                    'status' => 'primeira_mensagem',
                    'step' => $step
                ]);
            } else {
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
        if (!$session) {
            return response()->json(['error' => 'Sessão não encontrada'], 404);
        }
        $stepObj = WhatsappFlowStep::where('name', $stepName)->first();
        if (!$stepObj) {
            return response()->json(['error' => 'Step não encontrado'], 404);
        }
        $session->current_step_id = $stepObj->id;
        $session->save();
        return response()->json([
            'success' => true,
            'step_id' => $stepObj->id,
            'step_name' => $stepObj->name,
            'prompt' => $stepObj->prompt
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
    private function teste($data)
    {

        // Verifica se veio uma mensagem
        $messageData = $data['entry'][0]['changes'][0]['value']['messages'][0] ?? null;
        $contactData = $data['entry'][0]['changes'][0]['value']['contacts'][0] ?? null;
        $metadata = $data['entry'][0]['changes'][0]['value']['metadata'] ?? null;

        if (!$messageData || !$contactData) {
            return response('Nenhuma mensagem encontrada', 200);
        }

        $wa_id = $contactData['wa_id'];
        $name = $contactData['profile']['name'] ?? 'Usuário';
        $messageText = $messageData['text']['body'] ?? '';

        // 1️⃣ Verifica se o contato já existe
        $contact = WhatsappContact::firstOrCreate(
            ['wa_id' => $wa_id],
            values: ['name' => $name]
        );

        // 2️⃣ Salva a mensagem recebida
        WhatsappMessage::create([
            'contact_id' => $contact->id,
            'message_id' => $messageData['id'] ?? null,
            'direction' => 'in',
            'content' => $messageText,
            'type' => $messageData['type'] ?? 'text',
            'timestamp' => isset($messageData['timestamp']) ? date('Y-m-d H:i:s', $messageData['timestamp']) : now(),
            'raw' => $messageData,
        ]);

        // 3️⃣ Verifica sessão do usuário
        $session = WhatsappSession::firstOrCreate(
            ['contact_id' => $contact->id],
            ['current_step_id' => null, 'context' => [], 'phone_number_id' => $metadata['phone_number_id'] ?? null]
        );
        // Log::info('$sessao : ' . $session);
        // If we have metadata with phone_number_id but the session doesn't, update it
        if (!empty($metadata['phone_number_id']) && empty($session->phone_number_id)) {
            $session->phone_number_id = $metadata['phone_number_id'];
            $session->save();
        }
        $phoneNumberId = $metadata['phone_number_id'];

        // Se não tem fluxo, inicia o Fluxo Inicial
        if ($session->current_step_id == null) {

            $step1 = $this->getStepFluxo(1);

            // se dia útil (configurável) — muda para lógica real de expediente quando necessário
            $isDiaUtil = true; // substituir por verificação de horário/feriado real
            if ($isDiaUtil) {
                $step2 = $this->getStepFluxo(3);
            } else {
                $step2 = $this->getStepFluxo(2);
            }

            if (!$step1 || !$step2)
                return;
            $session->update([
                'current_step_id' => $step2->id,
                'context' => [],
            ]);
            // Envia boas-vindas e solicitação de CPF
            $this->sendMessage($wa_id, $this->replacePlaceholders($step1->prompt, $session->context, $name), $metadata['phone_number_id']);

            $this->sendMessage($wa_id, $this->replacePlaceholders($step2->prompt, $session->context, $name), $metadata['phone_number_id']);
            return;
        }

        if ($session->currentStep->next_step_condition == "api_valida_cpf") {

            // Busca cod_cliente na tabela contato_dados usando document = $messageText
            $document = preg_replace('/\D/', '', $messageText);
            $contatoDados = \App\Models\ContatoDados::where('document', $document)->first();
            $codCliente = $contatoDados ? $contatoDados->cod_cliente : null;
            if ($codCliente) {
                $PossuiContratoAberto = $this->obterAcordosPorCliente($codCliente);
            } else {
                $PossuiContratoAberto = false;
            }
            $verificaDivida = true;
            $context = $session->context ?? [];

            //estou buscando informação
            $stepbuscndo = $this->getStepFluxo(4);
            $this->sendMessage($wa_id, $this->replacePlaceholders($stepbuscndo->prompt, $session->context, $name), $phoneNumberId);

            if ($PossuiContratoAberto || $verificaDivida) {
                //encontrei vc
                $stepbuscndo = $this->getStepFluxo(5);
                $this->sendMessage($wa_id, $this->replacePlaceholders($stepbuscndo->prompt, $session->context, $name), $phoneNumberId);

                if ($PossuiContratoAberto) {
                    $context['PossuiContratoAberto'] = true;
                    $session->context = $context;
                    $session->save();
                    $step = $this->getStepFluxo(6);
                    $options = [
                        ['id' => 'digitar_novamente', 'title' => 'Digitar Novamente'],
                        ['id' => 'atendimento', 'title' => 'Atendimento'],
                        ['id' => 'encerrar_conversa', 'title' => 'Encerrar conversa'],
                    ];
                    $this->sendMenuOptions($wa_id, $phoneNumberId, $options, $step->prompt);

                } else {

                }



            } else {

                $stepNaoVigente = $this->getStepFluxo(9);
                $options = [
                    ['id' => 'digitar_novamente', 'title' => 'Digitar Novamente'],
                    ['id' => 'atendimento', 'title' => 'Atendimento'],
                    ['id' => 'encerrar_conversa', 'title' => 'Encerrar conversa'],
                ];
                $this->sendMenuOptions($wa_id, $phoneNumberId, $options, $stepNaoVigente->prompt);
            }




            if ($PossuiContratoAberto) {
                $verificaVigente = true;



                // Persistir contexto e log do resultado
                $session->save();

                if ($verificaVigente) {
                    $stepNaoVigente = $this->getStepFluxo(6);
                    $options = [
                        // ['id' => 'negociar', 'title' => 'Negociar'],
                        ['id' => '2_via_boleto', 'title' => '2ª via de boleto'],
                        ['id' => 'enviar_comprovante', 'title' => 'Enviar comprovante'],
                        ['id' => 'atendimento', 'title' => 'Atendimento'],
                        ['id' => 'encerrar_conversa', 'title' => 'Encerrar conversa'],
                    ];
                    $this->sendMenuOptions($wa_id, $phoneNumberId, $options, $stepNaoVigente->prompt);

                    if ($stepNaoVigente) {
                        // Usa o passo que contém o prompt/condição (por exemplo step 10)
                        $session->update(['flow_id' => $stepNaoVigente->flow_id, 'current_step_id' => $stepNaoVigente->id, 'context' => $context]);
                    } else {
                        $session->update(['flow_id' => 2, 'context' => $context]);
                    }

                } else {

                    $stepVigente = $this->getStepFluxo(7);
                    $options = [
                        ['id' => 'negociar', 'title' => 'Negociar'],
                        ['id' => 'atendimento', 'title' => 'Atendimento'],
                        ['id' => 'encerrar_conversa', 'title' => 'Encerrar conversa'],
                    ];
                    $this->sendMenuOptions($wa_id, $phoneNumberId, $options, $stepVigente->prompt);
                    // Persistir sessão para indicar que estamos no fluxo de negociação
                    // Em vez de apontar para o passo genérico do menu (6), apontamos para o passo
                    // que contém o prompt e o next_step_condition específico (step 9)
                    if ($stepVigente) {
                        $session->update(['current_step_id' => $stepVigente->id, 'context' => $context]);
                    } else {
                        $session->update(['current_step_id' => $stepVigente->id, 'context' => $context]);
                    }

                }
            } else {
                $PossuiAcordoVigente = true;
                if ($PossuiAcordoVigente) {
                    $stepNaoVigente = $this->getStepFluxo(4, 4);
                    $options = [
                        ['id' => 'falar_com_atendente', 'title' => 'Falar Com Atendente'],
                        ['id' => 'encerrar_atendimento', 'title' => 'Encerrar Atendimento'],
                    ];
                    $this->sendMenuOptions($wa_id, $phoneNumberId, $options, $stepNaoVigente->prompt);
                    // Persistir sessão para indicar que estamos no fluxo de acordos
                    if ($stepNaoVigente) {
                        $session->update(['current_step_id' => $stepNaoVigente->id, 'context' => $context]);
                    } else {
                        $session->update(['current_step_id' => $stepNaoVigente->id, 'context' => $context]);
                    }
                } else {
                    //fluxo acordo 

                }

            }


        }
        if ($session->currentStep->next_step_condition == "aguarda_resposta_acordo_aberto_true") {

            switch ($messageText) {
                case 'Negociar':
                    $step = $this->getStepFluxo(10);
                    $this->sendMessage($wa_id, $this->replacePlaceholders($step->prompt, $session->context, $name), $phoneNumberId);


                    $quantidadeDeAcordos = 1;
                    if ($quantidadeDeAcordos == 0) {
                        //muda pro fluxo de acordos
                    } else if ($quantidadeDeAcordos == 1) {

                        $step = $this->getStepFluxo(11);
                        $this->sendMessage($wa_id, $this->replacePlaceholders($step->prompt, $session->context, $name), $phoneNumberId);

                        $verificaPropostaOpcoesDePagamentos = true;
                        if ($verificaPropostaOpcoesDePagamentos) {
                            $step = $this->getStepFluxo(17);
                            $options = [
                                ['id' => 'gerar_acordo', 'title' => 'Gerar Acordo'],
                                ['id' => 'mais_opcoes', 'title' => 'Mais Opções'],
                            ];
                            $this->sendMenuOptions($wa_id, $phoneNumberId, $options, $step->prompt);
                            $session->update(['current_step_id' => $step->id]);
                        } else {
                            $step = $this->getStepFluxo(11);
                            $this->sendMessage($wa_id, $this->replacePlaceholders($step->prompt, $session->context, $name), $phoneNumberId);
                        }


                    } else if ($quantidadeDeAcordos > 1) {
                        //muda pro fluxo de acordos
                    }
                    break;
                case '2_via_boleto':
                case '2ª via de boleto':
                case '2 via de boleto':
                    // Envia informação de segunda via — encaminha para fluxo de envio de código de barras
                    $flow = WhatsappFlow::where('name', 'Fluxo Envia Código de Barras')->first();
                    if ($flow) {
                        $step = WhatsappFlowStep::where('flow_id', $flow->id)->where('step_number', 1)->first();
                        if ($step) {
                            $session->update(['flow_id' => $flow->id, 'current_step_id' => $step->id]);
                            $this->sendMessage($wa_id, $this->replacePlaceholders($step->prompt, $session->context, $name), $phoneNumberId);
                        }
                    }
                    break;
                case 'enviar_comprovante':
                case 'enviar comprovante':
                    // Direciona para fluxo de comprovantes
                    $flow = WhatsappFlow::where('name', 'Fluxo Comprovantes')->first();
                    if ($flow) {
                        $step = WhatsappFlowStep::where('flow_id', $flow->id)->where('step_number', 1)->first();
                        if ($step) {
                            $session->update(['flow_id' => $flow->id, 'current_step_id' => $step->id]);
                            $this->sendMessage($wa_id, $this->replacePlaceholders($step->prompt, $session->context, $name), $phoneNumberId);
                        }
                    } else {
                        // Fallback: pede o envio do comprovante
                        $this->sendMessage($wa_id, 'Por favor, envie seu comprovante de pagamento (imagem ou PDF).', $phoneNumberId);
                        $session->update(['current_step_id' => null]);
                    }
                    break;
                case 'atendimento':
                    // Encaminha para atendimento humano (apenas mensagem por enquanto)
                    $this->sendMessage($wa_id, 'Vou encaminhar seu contato para o atendimento. Aguarde um momento, por favor.', $phoneNumberId);
                    // opcional: marcar contexto para atendimento humano
                    $ctx = $session->context ?? [];
                    $ctx['escalado_atendimento'] = true;
                    $session->update(['context' => $ctx, 'current_step_id' => null]);
                    break;
                case 'encerrar_conversa':
                case 'encerrar':
                case 'encerrar conversa':
                    // Encerra a conversa
                    $this->sendMessage($wa_id, 'Encerrando a conversa. Obrigado!', $phoneNumberId);
                    $session->update(['current_step_id' => null, 'context' => []]);
                    break;
                default:
                    // Resposta desconhecida: repete o passo atual (ou envia mensagem de ajuda)
                    $this->sendMessage($wa_id, 'Opção não reconhecida. Por favor selecione uma opção válida.', $phoneNumberId);
                    break;
            }

        }

        if ($session->currentStep->next_step_condition == "aguardar_resposta_proposta") {
            switch ($messageText) {
                case 'Gerar Acordo':
                    $gerarBoleto = true;
                    $context['gerarBoleto'] = $gerarBoleto;
                    $step = $this->getStepFluxo(23);
                    $this->sendMessage($wa_id, $this->replacePlaceholders($step->prompt, $session->context, $name), $phoneNumberId);

                    $step = $this->getStepFluxo(24);
                    $this->sendMessage($wa_id, $this->replacePlaceholders($step->prompt, $session->context, $name), $phoneNumberId);


                    $this->sendMessage($wa_id, $this->replacePlaceholders('codigo de barra', $session->context, $name), $phoneNumberId);

                    $this->sendMessage($wa_id, $this->replacePlaceholders('envia pdf', $session->context, $name), $phoneNumberId);

                    $step = $this->getStepFluxo(25);
                    $this->sendMessage($wa_id, $this->replacePlaceholders($step->prompt, $session->context, $name), $phoneNumberId);
                    $session->update(['current_step_id' => $step->id]);
                    break;
                case 'Mais Opções':
                    $step = $this->getStepFluxo(18);
                    $options = [
                        ['id' => 'alterar_vencimento', 'title' => 'Alterar Vencimento'],
                        ['id' => 'parcelar_pagamento', 'title' => 'Parcelar Pagamento'],
                        ['id' => 'falar_com_especialista', 'title' => 'Falar com Especialista'],
                        ['id' => 'encerrar_atendimento', 'title' => 'Encerrar Atendimento'],
                    ];
                    $this->sendMenuOptions($wa_id, $phoneNumberId, $options, $step->prompt);
                    $session->update(['current_step_id' => $step->id]);
                    break;

                default:
                    # code...
                    break;
            }
        }


        if ($session->currentStep->next_step_condition == "aguardar_resposta_mais_opcoes_proposta") {
            switch ($messageText) {
                case 'Alterar Vencimento':
                    $step = $this->getStepFluxo(21);
                    $this->sendMessage($wa_id, $this->replacePlaceholders($step->prompt, $session->context, $name), $phoneNumberId);

                    $step = $this->getStepFluxo(24);
                    $this->sendMessage($wa_id, $this->replacePlaceholders($step->prompt, $session->context, $name), $phoneNumberId);
                    break;
                default:
                    # code...
                    break;
            }





















        }


        // 4️⃣ Lógica baseada no fluxo (passa phone_number_id explicitamente)
        // $this->processFlow($session, $wa_id, $name, $messageText, $metadata['phone_number_id'] ?? $session->phone_number_id ?? null);

    }

    /**
     * Faz request para obter acordos por cliente
     * @param string $codigoCliente
     * @return array|false
     */
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
            'POST',
            'https://havan-request.betasolucao.com.br/api/obter-acordos-por-cliente',
            $headers,
            $body
        );
        try {
            $res = $client->sendAsync($request)->wait();
            $responseBody = $res->getBody()->getContents();
            $data = json_decode($responseBody, true);
            if (isset($data['mensagem']) && strtolower($data['mensagem']) === 'nenhumacordo encontrado') {
                return false;
            }
            return $data;
        } catch (\Exception $e) {
            \Log::error('Erro ao obter acordos por cliente: ' . $e->getMessage());
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

    // ------------------------- Handlers por fluxo -------------------------
    // Cada handler pode conter lógica específica para os passos desse fluxo
    private function handleFlowInicial($session, $wa_id, $name, $messageText, $phoneNumberId)
    {
        // Lógica específica do Fluxo Negociar
        $currentStep = WhatsappFlowStep::find($session->current_step_id);
        // Log::info('$currentStep : ' . $currentStep);

        if (!$this->validateInput($messageText, $currentStep->expected_input)) {
            $errorStep = WhatsappFlowStep::where('flow_id', WhatsappFlow::where('name', 'Fluxo Erros')->first()->id ?? 0)->where('step_number', 1)->first();
            if ($errorStep) {
                $this->sendMessage($wa_id, $this->replacePlaceholders($errorStep->prompt, $session->context, $name), $phoneNumberId);
            }
            return;
        }




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

        $response = Http::withToken($token)
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
