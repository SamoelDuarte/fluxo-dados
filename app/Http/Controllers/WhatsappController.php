<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Jobs\SendWhatsappMessage;
use App\Jobs\SendWhatsappTypingThenMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Log;
use App\Models\WhatsappContact;
use App\Models\WhatsappMessage;
use App\Models\WhatsappSession;
use App\Models\WhatsappFlow;
use App\Models\WhatsappFlowStep;

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
            Log::info('Webhook recebido: ' . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            // Se vier metadata com phone_number_id, salvar na configuração para usar no envio
            $metadata = $data['entry'][0]['changes'][0]['value']['metadata'] ?? null;
            if (!empty($metadata['phone_number_id'])) {
                try {
                    DB::table('whatsapp')->updateOrInsert(['id' => 1], [
                        'phone_number_id' => $metadata['phone_number_id']
                    ]);
                    Log::info('phone_number_id salvo a partir do webhook: ' . $metadata['phone_number_id']);
                } catch (\Exception $e) {
                    Log::error('Erro ao salvar phone_number_id: ' . $e->getMessage());
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
                'content' => $messageData['text']['body'] ?? '',
                'type' => $messageData['type'] ?? 'text',
                'timestamp' => isset($messageData['timestamp']) ? date('Y-m-d H:i:s', $messageData['timestamp']) : now(),
                'raw' => $messageData,
            ]);

            // 3️⃣ Verifica sessão do usuário
            $session = WhatsappSession::firstOrCreate(
                ['contact_id' => $contact->id],
                ['flow_id' => null, 'current_step_id' => null, 'context' => [], 'phone_number_id' => $metadata['phone_number_id'] ?? null]
            );

            // === Nova lógica: se já existe um fluxo e um passo atual, trata a resposta do usuário ===
            if ($session->flow_id && $session->current_step_id) {
                $currentStep = WhatsappFlowStep::find($session->current_step_id);
                if ($currentStep && $currentStep->step_number == 1) {
                    $text = $messageData['text']['body'] ?? '';
                    $digits = preg_replace('/\D/', '', $text);

                    if (!$this->isValidCpfCnpj($digits)) {
                        SendWhatsappMessage::dispatch($wa_id, 'CPF/CNPJ inválido. Por favor digite apenas os números do seu CPF (11 dígitos) ou CNPJ (14 dígitos).', $session->phone_number_id ?? null);
                        return response('EVENT_RECEIVED', 200);
                    }

                    // salva documento no contexto da sessão
                    $ctx = $session->context ?? [];
                    $ctx['document'] = $digits;
                    $session->update(['context' => $ctx]);

                    // avisa que está buscando
                    SendWhatsappMessage::dispatch($wa_id, 'Estou buscando as informações necessárias para seguirmos por aqui, só um instante.', $session->phone_number_id ?? null);

                    // busca dívidas (implementação abaixo). Retorna null ou array de resultados.
                    $debts = $this->findDebtsByDocument($digits);

                    if (!empty($debts)) {
                        SendWhatsappMessage::dispatch($wa_id, 'Encontrei você!', $session->phone_number_id ?? null);

                        // Envia resumo (ajuste conforme formato real dos dados)
                        foreach ($debts as $d) {
                            $line = "Contrato: {$d['contract']}\nValor: {$d['amount']}\nVencimento: {$d['due_date']}";
                            SendWhatsappMessage::dispatch($wa_id, $line, $session->phone_number_id ?? null);
                        }

                        // opções dinâmicas vindas de fora (quem chama monta o array)
                        $options = [
                            ['id' => 'negociar', 'title' => 'Negociar'],
                            ['id' => 'segunda_via', 'title' => '2ª Via de Boleto'],
                            ['id' => 'comprovante', 'title' => 'Enviar Comprovante'],
                            ['id' => 'atendimento', 'title' => 'Atendimento'],
                            ['id' => 'encerrar', 'title' => 'Encerrar conversa'],
                        ];

                        // chama a função que monta e envia o menu (decide formato automaticamente)
                        $this->sendMenuOptions($wa_id, $session->phone_number_id ?? null, $options, 'Selecione uma opção:');
                        // aqui você pode avançar o fluxo para etapa de negociação/consulta detalhada
                    } else {
                        $firstName = explode(' ', trim($name))[0] ?? 'Cliente';
                        $menu = "Solicita Ação - Cliente Não Encontrado\n\n";
                        $menu .= "@{$firstName}, não localizei cadastro com o seu documento.\n\n";
                        $menu .= "Selecione uma opção abaixo:\n";
                        $menu .= "1) Digitar novamente\n2) Atendimento\n3) Encerrar conversa";
                        SendWhatsappMessage::dispatch($wa_id, $menu, $session->phone_number_id ?? null);

                        // opcional: atualizar current_step_id para um passo "não encontrado" se quiser tratar opções depois
                    }

                    return response('EVENT_RECEIVED', 200);
                }
            }

            // 4️⃣ Se não tiver fluxo iniciado, inicia o fluxo "Solicitar CPF"
            if (!$session->flow_id) {
                $flow = WhatsappFlow::firstOrCreate(['name' => 'Solicita CPF']);
                $firstStep = WhatsappFlowStep::firstOrCreate(
                    ['flow_id' => $flow->id, 'step_number' => 1],
                    ['prompt' => "Solicita CPF\nPara localizar suas informações, por favor informe seu *CPF/CNPJ*:\nDigite apenas os números conforme o exemplo abaixo.\n01091209120"]
                );

                $session->update([
                    'flow_id' => $flow->id,
                    'current_step_id' => $firstStep->id,
                    'context' => [],
                ]);

                // Envia mensagem de boas-vindas (imediata)
                $sessionPhone = $session->phone_number_id ?? null;
                $welcome = "Seja bem-vindo(a) ao nosso canal digital!\n";
                $welcome .= "Eu sou a assistente digital da Neocob \n";
                $welcome .= "em nome das {{NomeBanco}}.";
                SendWhatsappMessage::dispatch($wa_id, $welcome, $sessionPhone);

                // substitui os dois dispatches por um job que faz typing_on -> espera -> envia mensagem
                SendWhatsappTypingThenMessage::dispatch($wa_id, $firstStep->prompt, $sessionPhone, 4);
            }

            return response('EVENT_RECEIVED', 200);
        }

        return response('Método não suportado', 405);
    }

    private function isValidCpfCnpj(string $digits): bool
    {
        $len = strlen($digits);
        if ($len === 11)
            return $this->isValidCpf($digits);
        if ($len === 14)
            return $this->isValidCnpj($digits);
        return false;
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

    private function findDebtsByDocument(string $document): ?array
    {
        // Se existir uma API configurada, consulta; senão retorna null (não encontrado)
        $apiUrl = env('DEBT_API_URL');
        if (empty($apiUrl)) {
            Log::info('findDebtsByDocument: DEBT_API_URL não configurada, retornando null', ['doc' => $document]);
            return null;
        }

        try {
            $res = Http::get($apiUrl, ['document' => $document]);
            if ($res->successful()) {
                $body = $res->json();
                // espera array de débitos. ajuste conforme API real
                return $body['debts'] ?? $body;
            } else {
                Log::warning('findDebtsByDocument: resposta não OK', ['status' => $res->status(), 'body' => $res->body()]);
            }
        } catch (\Exception $e) {
            Log::error('findDebtsByDocument erro: ' . $e->getMessage());
        }

        return null;
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
            ->post("https://graph.facebook.com/v21.0/{$phoneNumberId}/messages", $data);

        Log::info('Mensagem enviada: ' . $body . ' | Response: ' . $response->body());
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

            $url = "https://graph.facebook.com/v21.0/{$phoneNumberId}/messages";
            $res = Http::withToken($token)->post($url, $body);
            Log::info('sendMenuOptions (buttons) response', ['status' => $res->status(), 'body' => $res->body()]);
            return true;
        }

        // Se tiver mais de 3 opções, usa interactive type=list (mais flexível)
        $rows = [];
        foreach ($normalized as $idx => $opt) {
            $rows[] = [
                'id' => $opt['id'],
                'title' => $opt['title'],
                'description' => $opt['title']
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

        $url = "https://graph.facebook.com/v21.0/{$phoneNumberId}/messages";
        $res = Http::withToken(token: $token)->post($url, $body);
        Log::info('sendMenuOptions (list) response', ['status' => $res->status(), 'body' => $res->body()]);
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
}
