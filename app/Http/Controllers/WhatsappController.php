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

            // Se vier metadata com phone_number_id, salvar na configuração para usar no envio
            $metadata = $data['entry'][0]['changes'][0]['value']['metadata'] ?? null;
            if (!empty($metadata['phone_number_id'])) {
                try {
                    DB::table('whatsapp')->updateOrInsert(['id' => 1], [
                        'phone_number_id' => $metadata['phone_number_id']
                    ]);
                } catch (\Exception $e) {
                    // metadata save failed - suppressed log per request
                }
            }

            // Verifica se veio uma mensagem
            $messageData = $data['entry'][0]['changes'][0]['value']['messages'][0] ?? null;
            $contactData = $data['entry'][0]['changes'][0]['value']['contacts'][0] ?? null;

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
                ['flow_id' => null, 'current_step_id' => null, 'context' => [], 'phone_number_id' => $metadata['phone_number_id'] ?? null]
            );

            // 4️⃣ Lógica baseada no fluxo
            $this->processFlow($session, $wa_id, $name, $messageText, $session->phone_number_id ?? null);

            return response('EVENT_RECEIVED', 200);
        }

        return response('Método não suportado', 405);
    }

    private function processFlow($session, $wa_id, $name, $messageText, $phoneNumberId)
    {
        if (!$session->flow_id) {
            // Inicia fluxo inicial
            $flow = WhatsappFlow::where('name', 'Fluxo Inicial')->first();
            if (!$flow) {
                return;
            }
            $step1 = WhatsappFlowStep::where('flow_id', $flow->id)->where('step_number', 1)->first();
            $step2 = WhatsappFlowStep::where('flow_id', $flow->id)->where('step_number', 2)->first();
            if (!$step1 || !$step2) {
                return;
            }
            $session->update([
                'flow_id' => $flow->id,
                'current_step_id' => $step2->id, // Começa no passo 2 (pedir CPF)
                'context' => [],
            ]);
            // Envia prompt do passo 1 (welcome) diretamente (sem typing/delay para evitar bloqueio)
            $prompt1 = $this->replacePlaceholders($step1->prompt, $session->context, $name);
            $this->sendMessage($wa_id, $prompt1, $phoneNumberId);
            // Envia prompt do passo 2 (CPF) diretamente
            $prompt2 = $this->replacePlaceholders($step2->prompt, $session->context, $name);
            $this->sendMessage($wa_id, $prompt2, $phoneNumberId);
            return;
        }

        // Tem fluxo, processa resposta
        $currentStep = WhatsappFlowStep::find($session->current_step_id);
        if (!$currentStep) {
            return;
        }

        // Valida input
        if (!$this->validateInput($messageText, $currentStep->expected_input)) {
            // Envia erro específico para CPF diretamente
            if ($currentStep->expected_input === 'cpf') {
                $this->sendMessage($wa_id, 'CPF/CNPJ inválido. Por favor digite apenas os números do seu CPF (11 dígitos) ou CNPJ (14 dígitos).', $phoneNumberId);
            } else {
                // Envia erro genérico diretamente
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

        // Salva no contexto se necessário
        $context = $session->context ?? [];
        if ($currentStep->expected_input === 'cpf') {
            $context['document'] = preg_replace('/\D/', '', $messageText);
        }
        // Outros conforme necessário

    // Processa condição
    $nextStep = $this->processCondition($currentStep->next_step_condition, $session, $wa_id, $name, $messageText, $phoneNumberId);
    // reload context from session because processCondition may have modified it
    $context = $session->context ?? [];

        if ($nextStep) {
            $session->update([
                'current_step_id' => $nextStep->id,
                'flow_id'=> $nextStep->flow_id,
                'context' => $context,
            ]);
            $prompt = $this->replacePlaceholders($nextStep->prompt, $context, $name);
            // always send the configured step prompt (no special-case awaiting flag)
            $this->sendMessage($wa_id, $prompt, $phoneNumberId);

            // If this step has a server-side next_step_condition (like 'fluxo_negociar'), process it immediately
            if (!empty($nextStep->next_step_condition)) {
                $followUp = $this->processCondition($nextStep->next_step_condition, $session, $wa_id, $name, $messageText, $phoneNumberId);
                if ($followUp) {
                    // update session to the new flow/step returned by the condition
                    $session->update([
                        'current_step_id' => $followUp->id,
                        'flow_id' => $followUp->flow_id,
                        'context' => $context,
                    ]);

                    $prompt2 = $this->replacePlaceholders($followUp->prompt, $context, $name);
                    $this->sendMessage($wa_id, $prompt2, $phoneNumberId);
                    // If the follow-up expects a button/menu, send appropriate interactive message
                    if ($followUp->expected_input === 'botao') {
                        // For Fluxo Negociar (flow_id == 2) send the full menu options
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
                            // Generic botao: if <=3 options the sendMenuOptions will map to quick replies
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
            // Fim ou erro
            $session->update(['current_step_id' => null, 'context' => $context]);
        }
    }

    private function validateInput($input, $expected)
    {
        if (!$expected) return true;
        switch ($expected) {
            case 'cpf':
                $digits = preg_replace('/\D/', '', $input);
                return $this->isValidCpfCnpj($digits);
            case 'numero':
                return is_numeric($input);
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
                $flow = WhatsappFlow::find($session->flow_id);
                $context = $session->context ?? [];
                $document = $context['document'] ?? '';
                $debts = $this->findDebtsByDocument($document);
                if ($debts) {
                    $context['qtdContratos'] = 1;
                    $context['debts'] = $debts;
                    $context['valorTotal'] = 'R$ ' . number_format($debts['amount'], 2, ',', '.');
                    $context['data'] = date('d/m/Y', strtotime('+30 days'));
                    $context['parcelamento'] = $debts['parcelamento'];
                    $context['carteira'] = $debts['carteira'];
                    $context['data_response'] = [
                        '0' => [
                            'valorTotalOriginal' => $debts['valorTotalOriginal'],
                            'valorTotalAtualizado' => $debts['valorDivida'],
                            'opcoesPagamento' => [
                                ['valorTotal' => $debts['parcelamento'][0]['valorTotal'] ?? 0]
                            ]
                        ],
                        'opcoesPagamento' => $debts['parcelamento']
                    ];
                }
                else {
                    // Não encontrou cadastro para o documento informado -> retornar o passo 4 (mensagem 'não localizado')
                    // A mensagem será enviada pela lógica que envia o step.prompt
                    return WhatsappFlowStep::where('flow_id', $flow->id)->where('step_number', 4)->first();
                }
                $session->context = $context;
                return WhatsappFlowStep::where('flow_id', $flow->id)->where('step_number', 3)->first();
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
                switch (strtolower($messageText)) {
                    case 'negociar':
                        return $this->processCondition('fluxo_proposta', $session, $wa_id, $name, $messageText, $phoneNumberId);
                    case '2 via de boleto':
                        return $this->processCondition('fluxo_envia_codigo_barras', $session, $wa_id, $name, $messageText, $phoneNumberId);
                    case 'enviar comprovante':
                        return $this->processCondition('fluxo_envia_codigo_barras', $session, $wa_id, $name, $messageText, $phoneNumberId);
                    case 'atendimento':
                        $this->sendMessage($wa_id, 'Entrando em contato com atendimento.', $phoneNumberId);
                        return null;
                    case 'encerrar conversa':
                        return null;
                    default:
                        return WhatsappFlowStep::find($session->current_step_id);
                }
            default:
                return null;
        }
    }

    private function replacePlaceholders($text, $context, $name)
    {
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
        $contato = ContatoDados::where('document', $document)->first();
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
            "codigoUsuarioCarteiraCobranca" => (string)$codigoUsuarioCarteiraCobranca,
            "codigoCarteiraCobranca" => (string)$carteiraId,
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

        // Se tiver até 3 opções, usa interactive type=button (quick replies)
        if (count($normalized) <= 3) {
            $buttons = array_map(function ($opt) {
                return [
                    'type' => 'reply',
                    'reply' => [
                        'id' => $opt['id'],
                        'title' => $opt['title']
                    ]
                ];
            }, $normalized);

            $body = [
                'messaging_product' => 'whatsapp',
                'to' => $wa_id,
                'type' => 'interactive',
                'interactive' => [
                    'type' => 'button',
                    'body' => [
                        'text' => $title ?? 'Escolha uma opção:'
                    ],
                    'action' => [
                        'buttons' => $buttons
                    ]
                ]
            ];

            Log::info('sendMenuOptions body: ' . json_encode($body));
            $url = "https://graph.facebook.com/v18.0/{$phoneNumberId}/messages";
            $res = Http::withToken($token)->post($url, $body);
            return true;
        }

        // Se tiver mais de 3 opções, usa interactive type=list (mais flexível)
        $rows = [];
        foreach ($normalized as $idx => $opt) {
            $rows[] = [
                'id' => $opt['id'],
                'title' => $opt['title'],
                'description' => ''
            ];
        }

        $section = [
            'title' => $title ?? 'Opções',
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
                    'text' => $title ?? 'Selecione:'
                ],
                'body' => [
                    'text' => $title ?? 'Escolha uma opção abaixo:'
                ],
                'action' => [
                    'button' => 'Ver opções',
                    'sections' => [$section]
                ]
            ]
        ];

        Log::info('sendMenuOptions body: ' . json_encode($body));
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
