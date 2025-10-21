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
            ['name' => $name]
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
            $PossuiContratoAberto = true;
            //verifica aki se iver vefica nahavanse tem algu em aberto

            // When at least one contract exists, return Flow 1 step 3 (the 'buscando' step)
            $stepbuscndo = $this->getStepFluxo(4);
            $this->sendMessage($wa_id, $this->replacePlaceholders($stepbuscndo->prompt, $session->context, $name), $phoneNumberId);

            $context = $session->context ?? [];
            if ($PossuiContratoAberto) {
                $verificaVigente = true;

                // When at least one contract exists, return Flow 2 step 1 (the 'buscando' step)
                $stepbuscndo = $this->getStepFluxo(5);
                $this->sendMessage($wa_id, $this->replacePlaceholders($stepbuscndo->prompt, $session->context, $name), $phoneNumberId);

                $session->context = $context;
                // Persistir contexto e log do resultado
                $session->save();

                if ($verificaVigente) {
                    $stepNaoVigente = $this->getStepFluxo(6);
                    $options = [
                        ['id' => 'negociar', 'title' => 'Negociar'],
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

                    $this->sendMessage($wa_id, body: $this->replacePlaceholders('envia pdf', $session->context, $name), $phoneNumberId);

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
























        // 4️⃣ Lógica baseada no fluxo (passa phone_number_id explicitamente)
        // $this->processFlow($session, $wa_id, $name, $messageText, $metadata['phone_number_id'] ?? $session->phone_number_id ?? null);

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

    private function processFlow($session, $wa_id, $name, $messageText, $phoneNumberId)
    {


        // Dispatcher por fluxo: facilita adicionar lógica específica por fluxo conforme migrations
        $flow = WhatsappFlow::find($session->flow_id);
        // Log::info('fluxo nome : ' . $flow->name);
        switch ($flow->name) {
            case 'Fluxo Inicial':
                // Migrations: flow_id = 1, steps: 1 (welcome), 2 (solicita cpf), 3 (buscando), 4 (nao localizado)
                $this->handleFlowInicial($session, $wa_id, $name, $messageText, $phoneNumberId);
                break;
            case 'Fluxo Negociar':
                // Migrations: flow_id = 2, step 1 expects 'botao' and next_step_condition = 'processar_opcao'
                $this->handleFlowNegociar($session, $wa_id, $name, $messageText, $phoneNumberId);
                break;
            case 'Fluxo Proposta':
                $this->handleFlowProposta($session, $wa_id, $name, $messageText, $phoneNumberId);
                break;
            case 'Fluxo Acordos':
                $this->handleFlowAcordos($session, $wa_id, $name, $messageText, $phoneNumberId);
                break;
            case 'Fluxo Confirma Acordo':
                $this->handleFlowConfirmaAcordo($session, $wa_id, $name, $messageText, $phoneNumberId);
                break;
            case 'Fluxo Envia Código de Barras':
                $this->handleFlowEnviaCodigoBarras($session, $wa_id, $name, $messageText, $phoneNumberId);
                break;
            case 'Fluxo Erros':
                $this->handleFlowErros($session, $wa_id, $name, $messageText, $phoneNumberId);
                break;
            default:
                // Handler genérico para fluxos sem lógica customizada ainda
                $this->processGenericFlow($session, $wa_id, $name, $messageText, $phoneNumberId);
                break;
        }

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

    private function handleFlowNegociar($session, $wa_id, $name, $messageText, $phoneNumberId)
    {
        // Delegate to generic flow processor so that steps defined in the DB
        // (for example step 9 or 10 which contain next_step_condition like
        // 'processar_opcao_verifica_vigente_true/false') are executed when
        // the user replies to the menu. This prevents executing negotiation
        // logic immediately and waits for the user's response.
        Log::info('handleFlowNegociar delegating to processGenericFlow', ['contact_id' => $session->contact_id, 'current_step_id' => $session->current_step_id]);
        $this->processGenericFlow($session, $wa_id, $name, $messageText, $phoneNumberId);
    }
    private function handleFlowNegocia($session, $wa_id, $name, $messageText, $phoneNumberId)
    {





        // // botao input treated as option text/id
        // $nextStep = $this->processCondition($currentStep->next_step_condition, $session, $wa_id, $name, $messageText, $phoneNumberId);
        // $context = $session->context ?? $context;

        // if ($nextStep) {
        //     $session->update(['current_step_id' => $nextStep->id, 'flow_id' => $nextStep->flow_id, 'context' => $context]);
        //     $prompt = $this->replacePlaceholders($nextStep->prompt, $context, $name);
        //     $this->sendMessage($wa_id, $prompt, $phoneNumberId);

        //     if (!empty($nextStep->next_step_condition)) {
        //         $followUp = $this->processCondition($nextStep->next_step_condition, $session, $wa_id, $name, $messageText, $phoneNumberId);
        //         if ($followUp) {
        //             if ($followUp->id === $nextStep->id) {
        //                 $session->update(['current_step_id' => $followUp->id, 'flow_id' => $followUp->flow_id, 'context' => $context]);
        //                 return;
        //             }
        //             $session->update(['current_step_id' => $followUp->id, 'flow_id' => $followUp->flow_id, 'context' => $context]);
        //             $prompt2 = $this->replacePlaceholders($followUp->prompt, $context, $name);
        //             $this->sendMessage($wa_id, $prompt2, $phoneNumberId);
        //             if ($followUp->expected_input === 'botao') {
        //                 // explicit menu for negociacao
        //                 $options = [
        //                     ['id' => 'negociar', 'title' => 'Negociar'],
        //                     ['id' => '2_via_boleto', 'title' => '2ª via de boleto'],
        //                     ['id' => 'enviar_comprovante', 'title' => 'Enviar comprovante'],
        //                     ['id' => 'atendimento', 'title' => 'Atendimento'],
        //                     ['id' => 'encerrar_conversa', 'title' => 'Encerrar conversa'],
        //                 ];
        //                 $this->sendMenuOptions($wa_id, $phoneNumberId, $options);
        //             }
        //         }
        //     }
        // } else {
        //     $session->update(['current_step_id' => null, 'context' => $context]);
        // }
    }

    private function handleFlowProposta($session, $wa_id, $name, $messageText, $phoneNumberId)
    {
        // Reuse generic step processing to handle the current step for this flow
        $this->processCurrentStep($session, $wa_id, $name, $messageText, $phoneNumberId);
    }

    private function handleFlowAcordos($session, $wa_id, $name, $messageText, $phoneNumberId)
    {
        $this->processCurrentStep($session, $wa_id, $name, $messageText, $phoneNumberId);
    }

    private function handleFlowConfirmaAcordo($session, $wa_id, $name, $messageText, $phoneNumberId)
    {
        $this->processCurrentStep($session, $wa_id, $name, $messageText, $phoneNumberId);
    }

    private function handleFlowEnviaCodigoBarras($session, $wa_id, $name, $messageText, $phoneNumberId)
    {
        $this->processCurrentStep($session, $wa_id, $name, $messageText, $phoneNumberId);
    }

    /**
     * Process the current step for any flow: validate input, execute condition,
     * update session and send prompts. This centralizes step processing so each
     * flow can have a single entrypoint that calls this method.
     */
    private function processCurrentStep($session, $wa_id, $name, $messageText, $phoneNumberId)
    {
        $currentStep = WhatsappFlowStep::find($session->current_step_id);
        if (!$currentStep)
            return;

        // Validate input
        if (!$this->validateInput($messageText, $currentStep->expected_input)) {
            // Send a generic error or use the Fluxo Erros
            $errorFlow = WhatsappFlow::where('name', 'Fluxo Erros')->first();
            if ($errorFlow) {
                $errorStep = WhatsappFlowStep::where('flow_id', $errorFlow->id)->where('step_number', 1)->first();
                if ($errorStep) {
                    $this->sendMessage($wa_id, $this->replacePlaceholders($errorStep->prompt, $session->context, $name), $phoneNumberId);
                }
            } else {
                $this->sendMessage($wa_id, 'Resposta inválida. Por favor, responda conforme solicitado.', $phoneNumberId);
            }
            return;
        }

        $context = $session->context ?? [];
        if ($currentStep->expected_input === 'cpf') {
            $context['document'] = preg_replace('/\D/', '', $messageText);
        }

        // Execute server-side condition and get the next step
        $nextStep = $this->processCondition($currentStep->next_step_condition, $session, $wa_id, $name, $messageText, $phoneNumberId);
        $context = $session->context ?? $context;

        if ($nextStep) {
            $session->update(['current_step_id' => $nextStep->id, 'flow_id' => $nextStep->flow_id, 'context' => $context]);
            $prompt = $this->replacePlaceholders($nextStep->prompt, $context, $name);
            $this->sendMessage($wa_id, $prompt, $phoneNumberId);
        } else {
            $session->update(['current_step_id' => null, 'context' => $context]);
        }
    }

    private function handleFlowErros($session, $wa_id, $name, $messageText, $phoneNumberId)
    {
        // Mostra mensagem de erro padrão e repete a pergunta atual
        $errorStep = WhatsappFlowStep::where('flow_id', WhatsappFlow::where('name', 'Fluxo Erros')->first()->id ?? 0)->where('step_number', 1)->first();
        if ($errorStep) {
            $this->sendMessage($wa_id, $this->replacePlaceholders($errorStep->prompt, $session->context, $name), $phoneNumberId);
        }
    }


    /**
     * Lógica genérica: valida input, processa condição, envia prompts e previne reenvios duplicados.
     * Será chamada por handlers específicos quando não houver necessidade de sobreposição.
     */
    private function processGenericFlow($session, $wa_id, $name, $messageText, $phoneNumberId)
    {
        // Recupera o passo corrente
        $currentStep = WhatsappFlowStep::find($session->current_step_id);
        if (!$currentStep)
            return;

        // Validação do input
        if (!$this->validateInput($messageText, $currentStep->expected_input)) {
            if ($currentStep->expected_input === 'cpf') {
                $this->sendMessage($wa_id, 'CPF/CNPJ inválido. Por favor digite apenas os números do seu CPF (11 dígitos) ou CNPJ (14 dígitos).', $phoneNumberId);
            } else {
                $errorFlow = WhatsappFlow::where('name', 'Fluxo Erros')->first();
                if ($errorFlow) {
                    $errorStep = WhatsappFlowStep::where('flow_id', $errorFlow->id)->where('step_number', 1)->first();
                    if ($errorStep) {
                        $prompt = $this->replacePlaceholders($errorStep->prompt, $session->context, $name);
                        $this->sendMessage($wa_id, $prompt, $phoneNumberId);
                    }
                }
            }
            return;
        }

        // Atualiza contexto a partir da entrada do usuário
        $context = $session->context ?? [];
        if ($currentStep->expected_input === 'cpf') {
            $context['document'] = preg_replace('/\D/', '', $messageText);
        }

        // Processa condição do passo atual
        $nextStep = $this->processCondition($currentStep->next_step_condition, $session, $wa_id, $name, $messageText, $phoneNumberId);
        $context = $session->context ?? $context;

        if ($nextStep) {
            // Atualiza sessão para o nextStep e envia prompt configurado
            $session->update([
                'current_step_id' => $nextStep->id,
                'flow_id' => $nextStep->flow_id,
                'context' => $context,
            ]);
            $prompt = $this->replacePlaceholders($nextStep->prompt, $context, $name);
            $this->sendMessage($wa_id, $prompt, $phoneNumberId);

            // Se o nextStep tem uma condição server-side, processa imediatamente e trata follow-up
            if (!empty($nextStep->next_step_condition)) {
                $followUp = $this->processCondition($nextStep->next_step_condition, $session, $wa_id, $name, $messageText, $phoneNumberId);
                if ($followUp) {
                    // Evita reenvio quando followUp é o mesmo passo que acabamos de enviar
                    if ($followUp->id === $nextStep->id) {
                        $session->update([
                            'current_step_id' => $followUp->id,
                            'flow_id' => $followUp->flow_id,
                            'context' => $context,
                        ]);
                        return;
                    }

                    // Atualiza sessão e envia prompt do followUp
                    $session->update([
                        'current_step_id' => $followUp->id,
                        'flow_id' => $followUp->flow_id,
                        'context' => $context,
                    ]);
                    $prompt2 = $this->replacePlaceholders($followUp->prompt, $context, $name);
                    $this->sendMessage($wa_id, $prompt2, $phoneNumberId);

                    // Envia menu interativo se necessário
                    if ($followUp->expected_input === 'botao') {
                        if ($followUp->flow_id == 2) {
                            $options = [
                                ['id' => 'negociar', 'title' => 'Negociar'],
                                ['id' => '2_via_boleto', 'title' => '2ª via de boleto'],
                                ['id' => 'enviar_comprovante', 'title' => 'Enviar comprovante'],
                                ['id' => 'atendimento', 'title' => 'Atendimento'],
                                ['id' => 'encerrar_conversa', 'title' => 'Encerrar conversa'],
                            ];
                            $this->sendMenuOptions($wa_id, $phoneNumberId, $options);
                        } else {
                            $options = [
                                ['id' => 'sim', 'title' => 'Sim'],
                                ['id' => 'nao', 'title' => 'Não'],
                            ];
                            $this->sendMenuOptions($wa_id, $phoneNumberId, $options);
                        }
                    }
                }
            }
        } else {
            // Sem next step: encerra sessão atual (ou aguarda intervenção)
            $session->update(['current_step_id' => null, 'context' => $context]);
        }
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

    private function processCondition($condition, &$session, $wa_id, $name, $messageText, $phoneNumberId)
    {
        switch ($condition) {
            case 'solicita_cpf':
                return WhatsappFlowStep::find($session->current_step_id); // Mesmo passo
            case 'api_valida_cpf':


                // Fallback
                return null;
            case 'verifica_horario':
                $msg = strtolower(trim($messageText));
                if (in_array($msg, ['sim', 's'])) {
                    // Avança para solicitar CPF (Fluxo Inicial step 3)
                    $flow = WhatsappFlow::where('name', 'Fluxo Inicial')->first();
                    if ($flow) {
                        $session->flow_id = $flow->id;
                        return WhatsappFlowStep::where('flow_id', $flow->id)->where('step_number', 3)->first();
                    }
                    return null;
                }
                // Caso 'não' ou qualquer outra resposta, informar fora do horário e encerrar/aguardar
                $this->sendMessage($wa_id, 'No momento estamos fora do horário de atendimento. Nosso expediente é 07:00-22:00 (Sábados 07:00-14:00). Por favor, entre em contato novamente no próximo horário útil.', $phoneNumberId);
                // encerra a interação (não altera current_step)
                return null;
            case 'verifica_acordo':
                $msg = strtolower(trim($messageText));
                if (in_array($msg, ['sim', 's'])) {
                    return $this->processCondition('fluxo_acordos', $session, $wa_id, $name, $messageText, $phoneNumberId);
                }
                // se não possui acordo vigente, seguir para proposta/consulta de débitos
                return $this->processCondition('fluxo_proposta', $session, $wa_id, $name, $messageText, $phoneNumberId);
            case 'seleciona_acordo':
                // Espera-se que context['acordos'] seja um array com identificadores ou índices
                $context = $session->context ?? [];
                $acordos = $context['acordos'] ?? null;
                $msg = trim($messageText);
                if (is_array($acordos) && !empty($acordos)) {
                    // tentativa: se a mensagem for um índice numérico, selecionar pelo índice
                    if (ctype_digit($msg)) {
                        $idx = (int) $msg;
                        if (isset($acordos[$idx])) {
                            $context['selectedAcordo'] = $acordos[$idx];
                            $session->context = $context;
                            $session->save();
                            // retornar passo 3 do fluxo Acordos (opções para o acordo selecionado)
                            $flow = WhatsappFlow::where('name', 'Fluxo Acordos')->first();
                            if ($flow) {
                                return WhatsappFlowStep::where('flow_id', $flow->id)->where('step_number', 3)->first();
                            }
                        }
                    }
                    // se não encontrou, retorna ao mesmo passo para repetir
                    return WhatsappFlowStep::find($session->current_step_id);
                }
                // sem acordos no contexto, retorna null
                return null;
            case 'fluxo_negociar':
                Log::info('processCondition fluxo_negociar called');
                $flow = WhatsappFlow::where('name', 'Fluxo Negociar')->first();
                Log::info('flow found: ' . ($flow ? $flow->id : 'null'));
                if ($flow) {
                    $session->flow_id = $flow->id;
                    $step = WhatsappFlowStep::where('flow_id', $flow->id)->where('step_number', 1)->first();
                    Log::info('step found: ' . ($step ? $step->id . ' flow_id:' . $step->flow_id : 'null'));
                    return $step;
                }
                Log::info('returning null');
                return null;
            case 'fluxo_proposta':
                $flow = WhatsappFlow::where('name', 'Fluxo Proposta')->first();
                if ($flow) {
                    $session->flow_id = $flow->id;
                    return WhatsappFlowStep::where('flow_id', $flow->id)->where('step_number', 1)->first();
                }
                return null;
            case 'fluxo_acordos':
                $flow = WhatsappFlow::where('name', 'Fluxo Acordos')->first();
                if ($flow) {
                    $session->flow_id = $flow->id;
                    return WhatsappFlowStep::where('flow_id', $flow->id)->where('step_number', 1)->first();
                }
                return null;
            case 'fluxo_confirma_acordo':
                $flow = WhatsappFlow::where('name', 'Fluxo Confirma Acordo')->first();
                if ($flow) {
                    $session->flow_id = $flow->id;
                    return WhatsappFlowStep::where('flow_id', $flow->id)->where('step_number', 1)->first();
                }
                return null;
            case 'fluxo_envia_codigo_barras':
                $flow = WhatsappFlow::where('name', 'Fluxo Envia Código de Barras')->first();
                if ($flow) {
                    $session->flow_id = $flow->id;
                    return WhatsappFlowStep::where('flow_id', $flow->id)->where('step_number', 1)->first();
                }
                return null;
            case 'fluxo_algo_mais':
                return null; // Encerra
            case 'repetir_pergunta':
                return WhatsappFlowStep::find($session->current_step_id);
            case 'avaliacao_finalizada':
                return null;
            case 'salvar_configuracao':
                return null;
            case 'processar_opcao':
                // normalize incoming option (users may respond with id or title)
                $msgRaw = strtolower(trim($messageText));
                $msg = str_replace(['_', '-'], ' ', $msgRaw);

                // map possible user inputs to canonical actions
                $aliases = [
                    'negociar' => ['negociar'],
                    'inicio' => ['início', 'inicio', 'início', 'iniciar', 'inicio'],
                    '2_via' => ['2 via de boleto', '2 via boleto', '2 via', '2ª via de boleto', '2 via', '2 via boleto', '2via', '2ª via'],
                    'enviar_comprovante' => ['enviar comprovante', 'enviar_comprovante'],
                    'atendimento' => ['atendimento'],
                    'encerrar' => ['encerrar conversa', 'encerrar'],
                    'gerar_acordo' => ['gerar acordo', 'gerar_acordo'],
                    'mais_opcoes' => ['mais opcoes', 'mais opções', 'mais_opcoes', 'mais opçãO'],
                    'parcelar' => ['parcelar pagamento', 'parcelamento', 'parcelar_pagamento'],
                    'alterar_vencimento' => ['alterar vencimento'],
                    'ver_outro_contrato' => ['ver outro contrato'],
                    'voltar' => ['voltar'],
                ];

                $action = null;
                foreach ($aliases as $key => $variants) {
                    foreach ($variants as $v) {
                        if ($msg === strtolower($v)) {
                            $action = $key;
                            break 2;
                        }
                    }
                }

                switch ($action) {
                    case 'inicio':
                        return $this->processCondition('fluxo_inicial', $session, $wa_id, $name, $messageText, $phoneNumberId);
                    case 'negociar':
                        return $this->processCondition('fluxo_proposta', $session, $wa_id, $name, $messageText, $phoneNumberId);
                    case '2_via':
                    case 'enviar_comprovante':
                        return $this->processCondition('fluxo_envia_codigo_barras', $session, $wa_id, $name, $messageText, $phoneNumberId);
                    case 'atendimento':
                        $this->sendMessage($wa_id, 'Entrando em contato com atendimento.', $phoneNumberId);
                        return null;
                    case 'encerrar':
                        return null;
                    case 'gerar_acordo':
                        return $this->processCondition('fluxo_confirma_acordo', $session, $wa_id, $name, $messageText, $phoneNumberId);
                    case 'confirmar':
                        return $this->processCondition('fluxo_envia_codigo_barras', $session, $wa_id, $name, $messageText, $phoneNumberId);
                    case 'mais_opcoes':
                        $flow = WhatsappFlow::where('name', 'Fluxo Proposta')->first();
                        if ($flow) {
                            return WhatsappFlowStep::where('flow_id', $flow->id)->where('step_number', 3)->first();
                        }
                        return null;
                    case 'parcelar':
                        // tenta obter parcelamento via API usando document no contexto
                        $context = $session->context ?? [];
                        $document = $context['document'] ?? preg_replace('/\D/', '', $messageText);
                        if (empty($document)) {
                            // solicita CPF para prosseguir
                            $flowInit = WhatsappFlow::where('name', 'Fluxo Inicial')->first();
                            if ($flowInit) {
                                return WhatsappFlowStep::where('flow_id', $flowInit->id)->where('step_number', 3)->first();
                            }
                            return null;
                        }
                        $parcel = $this->findDebtsByDocument($document);
                        if ($parcel) {
                            $context['parcelamento'] = $parcel['parcelamento'] ?? $parcel;
                            $context['valorTotal'] = $parcel['valorDivida'] ?? $parcel['amount'] ?? $context['valorTotal'] ?? null;
                            $session->context = $context;
                            $session->save();
                            // passo 4 do Fluxo Proposta: lista de opções de parcelamento
                            $flow = WhatsappFlow::where('name', 'Fluxo Proposta')->first();
                            if ($flow) {
                                return WhatsappFlowStep::where('flow_id', $flow->id)->where('step_number', 4)->first();
                            }
                            return null;
                        }
                        // API não retornou opções -> passo 5 do Fluxo Proposta (erro API)
                        $flow = WhatsappFlow::where('name', 'Fluxo Proposta')->first();
                        if ($flow) {
                            return WhatsappFlowStep::where('flow_id', $flow->id)->where('step_number', 5)->first();
                        }
                        return null;
                    case 'alterar_vencimento':
                        $this->sendMessage($wa_id, 'Para alterar o vencimento, por favor informe a nova data no formato DD/MM/AAAA.', $phoneNumberId);
                        return null;
                    case 'ver_outro_contrato':
                        $neg = WhatsappFlow::where('name', 'Fluxo Negociar')->first();
                        if ($neg) {
                            return WhatsappFlowStep::where('flow_id', $neg->id)->where('step_number', 3)->first();
                        }
                        return null;
                    case 'voltar':
                        // if we're in Fluxo Confirma Acordo, return to Fluxo Proposta step 4 (parcelamento options)
                        $flowCur = WhatsappFlow::find($session->flow_id);
                        if ($flowCur && $flowCur->name === 'Fluxo Confirma Acordo') {
                            $prop = WhatsappFlow::where('name', 'Fluxo Proposta')->first();
                            if ($prop) {
                                return WhatsappFlowStep::where('flow_id', $prop->id)->where('step_number', 4)->first();
                            }
                        }
                        return WhatsappFlowStep::find($session->current_step_id);
                    default:
                        return WhatsappFlowStep::find($session->current_step_id);
                }
            case 'processar_opcao_verifica_vigente_true':
                // Trata as opções específicas do menu quando verificaVigente == true
                $msgRaw = strtolower(trim($messageText));
                $msg = str_replace(['_', '-'], ' ', $msgRaw);

                Log::info('processar_opcao_verifica_vigente_true called', ['msg' => $msg, 'contact_id' => $session->contact_id]);

                // Negociar -> direcionar para Fluxo Negociar
                if (strpos($msg, 'negoci') !== false || $msg === 'negociar') {
                    Log::info('Usuário escolheu negociar - entrando no Fluxo Negociar', ['contact_id' => $session->contact_id]);
                    $flow = WhatsappFlow::where('name', 'Fluxo Negociar')->first();
                    if ($flow) {
                        $session->flow_id = $flow->id;
                        return WhatsappFlowStep::where('flow_id', $flow->id)->where('step_number', 1)->first();
                    }
                    return null;
                }

                // Atendimento -> encaminhar para humano / enviar mensagem de aguarde
                if (strpos($msg, 'atend') !== false || $msg === 'atendimento') {
                    $this->sendMessage($wa_id, 'Entrando em contato com atendimento. Aguarde um momento, por favor.', $phoneNumberId);
                    return null;
                }

                // Encerrar conversa -> direcionar para módulo de encerramento (flow_id = 11)
                if (strpos($msg, 'encerr') !== false || $msg === 'encerrar conversa' || $msg === 'encerrar') {
                    Log::info('Usuário escolheu encerrar conversa', ['contact_id' => $session->contact_id]);
                    $encerrarFlow = WhatsappFlow::find(11);
                    if ($encerrarFlow) {
                        return WhatsappFlowStep::where('flow_id', $encerrarFlow->id)->where('step_number', 1)->first();
                    }
                    // fallback: envia mensagem simples
                    $this->sendMessage($wa_id, 'Encerrando a conversa. Obrigado!', $phoneNumberId);
                    return null;
                }

                // Se não reconheceu, retornar o passo atual para repetir
                return WhatsappFlowStep::find($session->current_step_id);
            default:
                return null;
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

    private function findDebtsByDocument(string $document): ?array
    {
        // Primeiro tenta encontrar pelo documento exatamente como veio
        $contato = ContatoDados::where('document', $document)->first();

        // Se não encontrou, tentar formatos alternativos comuns:
        // - remover zeros à esquerda (ex: '019...' -> '19...')
        // - preencher com zeros à esquerda para 11 dígitos (cpf) caso esteja menor
        if (!$contato) {
            // tenta sem zeros à esquerda
            $altNoLeading = ltrim($document, '0');
            if (!empty($altNoLeading)) {
                $contato = ContatoDados::where('document', $altNoLeading)->first();
                if ($contato) {
                    Log::info('findDebtsByDocument: matched after ltrim', ['original' => $document, 'tried' => $altNoLeading]);
                }
            }
        }

        if (!$contato) {
            // se for somente dígitos e tiver menos que 11, tenta preencher à esquerda com zeros (possível CPF armazenado como 11)
            if (ctype_digit($document) && strlen($document) < 11) {
                $pad = str_pad($document, 11, '0', STR_PAD_LEFT);
                $contato = ContatoDados::where('document', $pad)->first();
                if ($contato) {
                    Log::info('findDebtsByDocument: matched after pad', ['original' => $document, 'tried' => $pad]);
                }
            }
        }

        if (!$contato) {
            Log::info('findDebtsByDocument result', ['document' => $document, 'carteira' => null, 'result' => 'no_contato']);
            return null;
        }
        $carteiraId = $contato->carteira;
        switch ($carteiraId) {
            case 875:
                $codigoUsuarioCarteiraCobranca = 30;
                break;

            case 874:
                $codigoUsuarioCarteiraCobranca = 24;
                break;

            case 873:
                $codigoUsuarioCarteiraCobranca = 24;
                break;

            case 872:
                $codigoUsuarioCarteiraCobranca = 24;
                break;

            case 871:
                $codigoUsuarioCarteiraCobranca = 24;
                break;

            case 870:
                $codigoUsuarioCarteiraCobranca = 24;
                break;

            default:
                $codigoUsuarioCarteiraCobranca = null;
                break;
        }
        if ($codigoUsuarioCarteiraCobranca === null) {
            Log::info('findDebtsByDocument result', ['document' => $document, 'carteira' => $carteiraId, 'result' => 'codigoUsuarioCarteiraCobranca_null']);
            return null;
        }

        $client = new Client();
        $data = [
            "codigoUsuarioCarteiraCobranca" => (string) $codigoUsuarioCarteiraCobranca,
            "codigoCarteiraCobranca" => (string) $carteiraId,
            "pessoaCodigo" => $contato->numero_contrato,
            "dataPrimeiraParcela" => Carbon::today()->toDateString(),
            "valorEntrada" => 0,
            "chave" => "3cr1O35JfhQ8vBO",
            "renegociaSomenteDocumentosEmAtraso" => false
        ];
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->gerarToken()
        ];
        try {
            $response = $client->post('https://cobrancaexternaapi.apps.havan.com.br/api/v3/CobrancaExternaTradicional/ObterOpcoesParcelamento', [
                'json' => $data,
                'headers' => $headers,
            ]);
            $responseBody = $response->getBody();
            $responseData = json_decode($responseBody, true);
            if (isset($responseData[0]['messagem']) && !empty($responseData[0]['messagem'])) {
                Log::info('findDebtsByDocument result', ['document' => $document, 'carteira' => $carteiraId, 'result' => 'api_messagem', 'message' => $responseData[0]['messagem']]);
                return null;
            }
            if (!isset($responseData[0]['parcelamento']) || $responseData[0]['parcelamento'] === null || empty($responseData[0]['parcelamento'])) {
                return null;
            }
            $ultimoArray = end($responseData);
            if (!$ultimoArray || !isset($ultimoArray['parcelamento']) || !is_array($ultimoArray['parcelamento']) || empty($ultimoArray['parcelamento'])) {
                Log::info('findDebtsByDocument result', ['document' => $document, 'carteira' => $carteiraId, 'result' => 'no_parcelamento']);
                return null;
            }
            $result = [
                'amount' => $ultimoArray['valorDivida'],
                'contract' => $contato->numero_contrato,
                'carteira' => $carteiraId,
                'parcelamento' => $ultimoArray['parcelamento'],
                'valorTotalOriginal' => $ultimoArray['valorTotalOriginal'],
                'valorDivida' => $ultimoArray['valorDivida'],
            ];
            Log::info('findDebtsByDocument result', ['document' => $document, 'carteira' => $carteiraId, 'result' => 'found', 'amount' => $result['amount']]);
            return $result;
        } catch (\Exception $e) {
            Log::info('findDebtsByDocument result', ['document' => $document, 'carteira' => $carteiraId ?? null, 'result' => 'error', 'error' => $e->getMessage()]);
            return null;
        }
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
