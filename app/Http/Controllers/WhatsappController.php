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
                ['flow_id' => null, 'current_step_id' => null, 'context' => []]
            );

            // 4️⃣ Se não tiver fluxo iniciado, inicia o fluxo "Solicitar CPF"
            if (!$session->flow_id) {
                $flow = WhatsappFlow::firstOrCreate(['name' => 'Solicita CPF']);
                $firstStep = WhatsappFlowStep::firstOrCreate(
                    ['flow_id' => $flow->id, 'step_number' => 1],
                    ['prompt' => "Para localizar suas informações, por favor informe seu *CPF/CNPJ*:\nDigite apenas os números conforme o exemplo abaixo.\n01091209120"]
                );

                $session->update([
                    'flow_id' => $flow->id,
                    'current_step_id' => $firstStep->id,
                    'context' => [],
                ]);

                // Envia mensagem de boas-vindas
                $this->sendMessage($wa_id, "Seja bem-vindo(a) ao nosso canal digital! Eu sou a assistente digital da Neocob em nome das {{NomeBanco}}.");

                // Envia prompt do primeiro passo do fluxo
                $this->sendMessage($wa_id, $firstStep->prompt);
            }

            return response('EVENT_RECEIVED', 200);
        }

        return response('Método não suportado', 405);
    }

    // Função para enviar mensagem via WhatsApp Cloud API
    private function sendMessage($to, $body)
    {
        // Tenta obter token e phoneNumberId salvos no banco (configurados pelo fluxo de auth)
        $config = DB::table('whatsapp')->first();
        $token = $config->access_token ?? env('WHATSAPP_TOKEN');
        // Se houver um campo phone_number_id no registro, use-o. Caso contrário, fallback para env
        $phoneNumberId = $config->phone_number_id ?? env('WHATSAPP_PHONE_ID');

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

    // ...existing code...
}
