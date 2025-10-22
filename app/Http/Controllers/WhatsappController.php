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

                    $this->sendMessage($wa_id,  $this->replacePlaceholders('envia pdf', $session->context, $name), $phoneNumberId);

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



            return response('EVENT_RECEIVED', 200);
        }

        return response('Método não suportado', 405);
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

}
