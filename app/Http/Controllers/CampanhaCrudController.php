<?php

namespace App\Http\Controllers;

use App\Models\Campanha;
use App\Models\Contato;
use App\Models\ContatoDados;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use GuzzleHttp\Client;
use PhpOffice\PhpSpreadsheet\IOFactory;

class CampanhaCrudController extends Controller
{
    public function index()
    {
        $campanhas = Campanha::orderBy('id', 'desc')->paginate(20);
        return view('campanhas.crud.index', compact('campanhas'));
    }

    public function create()
    {
        $wabas = [];
        
        // Buscar business_id da tabela whatsapp
        $whatsappConfig = DB::table('whatsapp')->first();
        
        if ($whatsappConfig && $whatsappConfig->business_id && $whatsappConfig->access_token) {
            try {
                $client = new Client();
                $response = $client->get(
                    'https://graph.facebook.com/v22.0/' . $whatsappConfig->business_id . '/owned_whatsapp_business_accounts',
                    [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $whatsappConfig->access_token
                        ]
                    ]
                );
                
                $data = json_decode($response->getBody(), true);
                $wabas = $data['data'] ?? [];
            } catch (\Exception $e) {
                // Se houver erro na API, continua sem WABAs
                $wabas = [];
            }
        }
        
        return view('campanhas.crud.create', compact('wabas'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'template_id' => 'required|string',
            'template_name' => 'required|string',
            'waba_id' => 'required|string',
            'phone_numbers' => 'array',
            'planilha' => 'nullable|file|mimes:xlsx,xls,csv',
        ]);

        // Criar a campanha com timestamp
        $agora = now();
        $nomeCampanha = 'campanha (' . $agora->format('d/m/y:H:i') . ') ';
        $campanha = Campanha::create([
            'name' => $nomeCampanha,
            'template_id' => $data['template_id'],
            'template_name' => $data['template_name'],
            'waba_id' => $data['waba_id'],
            'created_at' => $agora,
            'updated_at' => $agora
        ]);

        // Inserir phone_number_ids na tabela campanha_telefone
        if (!empty($data['phone_numbers'])) {
            foreach ($data['phone_numbers'] as $phoneNumberId) {
                DB::table('campanha_telefone')->insert([
                    'campanha_id' => $campanha->id,
                    'phone_number_id' => $phoneNumberId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Processar planilha se fornecida
        if ($request->hasFile('planilha')) {
            $this->processarPlanilha($campanha, $request->file('planilha'));
        }

        return redirect()->route('campanhas.crud.index')->with('success', 'Campanha criada com sucesso.');
    }

    public function edit(Campanha $campanha)
    {
        $contatos = Contato::orderBy('name')->get();
        return view('campanhas.crud.edit', compact('campanha', 'contatos'));
    }

    public function update(Request $request, Campanha $campanha)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'contatos' => 'array',
            'phone_numbers' => 'array',
        ]);

        $campanha->update([
            'name' => $data['name'],
        ]);
        $campanha->contatos()->sync($data['contatos'] ?? []);
        
        // Sincronizar phone_number_ids
        // Primeiro, limpar os phone_numbers antigos
        DB::table('campanha_telefone')->where('campanha_id', $campanha->id)->delete();
        
        // Depois inserir os novos
        if (!empty($data['phone_numbers'])) {
            foreach ($data['phone_numbers'] as $phoneNumberId) {
                DB::table('campanha_telefone')->insert([
                    'campanha_id' => $campanha->id,
                    'phone_number_id' => $phoneNumberId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        return redirect()->route('campanhas.crud.index')->with('success', 'Campanha atualizada.');
    }

    public function destroy(Campanha $campanha)
    {
        $campanha->delete();
        return redirect()->route('campanhas.crud.index')->with('success', 'Campanha removida.');
    }

    public function play(Campanha $campanha)
    {
        try {
            $campanha->update(['status' => 'playing']);

            // // Buscar token WhatsApp
            // $whatsappConfig = DB::table('whatsapp')->first();
            // if (!$whatsappConfig || !$whatsappConfig->access_token) {
            //     \Log::error('Token WhatsApp nao configurado');
            //     return redirect()->back()->with('error', 'Token WhatsApp nao configurado');
            // }

            // $token = trim($whatsappConfig->access_token);

            // // Buscar phone_number_ids da campanha
            // $phoneNumberIds = $campanha->phoneNumbers();
            // if ($phoneNumberIds->isEmpty()) {
            //     \Log::error('Campanha sem phone_number_ids configurados');
            //     return redirect()->back()->with('error', 'Campanha sem phone_number_ids');
            // }

            // // Buscar contatos nao enviados (send=0)
            // $contatos = DB::table('contato_dados')
            //     ->whereIn('contato_id', $campanha->contatos->pluck('id'))
            //     ->where('send', 0)
            //     ->get();

            // if ($contatos->isEmpty()) {
            //     \Log::info('Nenhum contato para enviar na campanha ' . $campanha->id);
            //     return redirect()->back()->with('info', 'Nenhum contato pendente');
            // }

            // // Distribuir contatos na fila
            // $phoneNumberIdsArray = $phoneNumberIds->toArray();
            // $phoneCount = count($phoneNumberIdsArray);
            // $contatoIndex = 0;
            // $totalEnfileirado = 0;

            // foreach ($contatos as $contatoDado) {
            //     try {
            //         // Round-robin para distribuir entre telefones
            //         $phoneNumberId = $phoneNumberIdsArray[$contatoIndex % $phoneCount];
            //         $contatoIndex++;

            //         // Marcar como em fila
            //         DB::table('contato_dados')
            //             ->where('id', $contatoDado->id)
            //             ->update(['send' => 2]);

            //         // Disparar job (lock no job previne duplicacao)
            //         \App\Jobs\SendWhatsappMessageQueue::dispatch(
            //             $contatoDado->id,
            //             $campanha->id,
            //             $phoneNumberId,
            //             $token,
            //             $campanha->template_name
            //         )->onQueue('whatsapp');

            //         $totalEnfileirado++;
            //     } catch (\Exception $e) {
            //         \Log::error('Erro ao disparar job para contato ' . $contatoDado->id . ': ' . $e->getMessage());
            //     }
            // }

            // \Log::info("Campanha {$campanha->id} iniciada: {$totalEnfileirado} contatos enfileirados");
            return redirect()->back()->with('success', "Campanha iniciada! ");
        } catch (\Exception $e) {
            \Log::error('Erro: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Erro: ' . $e->getMessage());
        }
    }

    public function pause(Campanha $campanha)
    {
        try {
            $campanha->update(['status' => 'paused']);

            // Revert contatos marcados em fila (send=2) para send=0
        

            \Log::info("Campanha {$campanha->id} pausada");
            return redirect()->back()->with('success', 'Campanha pausada! Contatos removidos da fila');
        } catch (\Exception $e) {
            \Log::error('Erro: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Erro: ' . $e->getMessage());
        }
    }

    public function getTemplates(Request $request)
    {
        $wabaId = $request->query('waba_id');
        
        if (!$wabaId) {
            return response()->json(['error' => 'WABA ID não fornecido'], 400);
        }

        $whatsappConfig = DB::table('whatsapp')->first();
        
        if (!$whatsappConfig || !$whatsappConfig->access_token) {
            return response()->json(['error' => 'Configuração WhatsApp não encontrada'], 500);
        }

        try {
            $client = new Client();
            $response = $client->get(
                'https://graph.facebook.com/v22.0/' . $wabaId . '/message_templates',
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $whatsappConfig->access_token
                    ]
                ]
            );
            
            $data = json_decode($response->getBody(), true);
            $templates = $data['data'] ?? [];
            
            return response()->json(['templates' => $templates]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erro ao buscar templates: ' . $e->getMessage()], 500);
        }
    }

    public function getPhoneNumbers(Request $request)
    {
        $wabaId = $request->query('waba_id');
        
        if (!$wabaId) {
            return response()->json(['error' => 'WABA ID não fornecido'], 400);
        }

        $whatsappConfig = DB::table('whatsapp')->first();
        
        if (!$whatsappConfig || !$whatsappConfig->access_token) {
            return response()->json(['error' => 'Configuração WhatsApp não encontrada'], 500);
        }

        try {
            $client = new Client();
            $response = $client->get(
                'https://graph.facebook.com/v22.0/' . $wabaId . '/phone_numbers',
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $whatsappConfig->access_token
                    ]
                ]
            );
            
            $data = json_decode($response->getBody(), true);
            $phoneNumbers = $data['data'] ?? [];
            
            return response()->json(['phone_numbers' => $phoneNumbers]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erro ao buscar telefones: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Processa a planilha enviada e salva os contatos
     */
    private function processarPlanilha(Campanha $campanha, $file)
    {
        try {
            // Criar um contato genérico para esta campanha com data e hora
            $agora = now();
            $nomeContato = 'campanha (' . $agora->format('d/m/y:H:i') . ') ';
            $contato = Contato::firstOrCreate(
                ['name' => $nomeContato],
                ['name' => $nomeContato]
            );

            // Associar contato à campanha
            $campanha->contatos()->attach($contato->id);

            $path = $file->store('campanhas_imports');
            $fullPath = storage_path('app/' . $path);

            if (!file_exists($fullPath)) {
                \Log::error('Arquivo de planilha não encontrado: ' . $fullPath);
                return;
            }

            // Ler arquivo Excel usando PhpSpreadsheet
            $spreadsheet = IOFactory::load($fullPath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            if (empty($rows)) {
                \Log::warning('Planilha vazia');
                Storage::delete($path);
                return;
            }

            $importados = 0;

            foreach ($rows as $rowNum => $row) {
                // Pular header (primeira linha)
                if ($rowNum === 0) {
                    \Log::info('Headers: ' . json_encode($row));
                    continue;
                }

                // Parar de ler quando encontrar linha completamente vazia
                if (empty(array_filter($row, function ($value) {
                    return $value !== null && $value !== '';
                }))) {
                    \Log::info('Fim da planilha: primeira linha vazia encontrada em linha ' . ($rowNum + 1));
                    break;
                }

                // Log RAW
                \Log::info('Linha ' . ($rowNum + 1) . ' RAW: ' . json_encode($row));

                // Extrair campos pela posição correta baseado nos headers da planilha
                // 0=Número Contrato, 1=Devedor, 2=Número (phone com DDD), 3=Grupo, 4=CPF, 
                // 5=Status Telefone, 6=DDD, 7=Telefone (alternative), 8=Id Contrato, 
                // 9=Código da Carteira, 10=Valor Contrato, 11=Dias em atraso, 12=Data Vencimento
                $numero_contrato = $row[0] ?? null;
                $devedor = $row[1] ?? null;
                $telefone_raw = $row[2] ?? null;  // Coluna 2: Número (phone com DDD)
                $cpf = $row[4] ?? null;            // Coluna 4: CPF
                $carteira = $row[9] ?? null;       // Coluna 9: Código da Carteira
                $valor = $row[10] ?? null;         // Coluna 10: Valor Contrato
                $dias_atraso = $row[11] ?? null;   // Coluna 11: Dias em atraso
                $data_venc = $row[12] ?? null;     // Coluna 12: Data Vencimento

                // Limpar e validar
                $telefone = !empty($telefone_raw) ? preg_replace('/\D/', '', (string)$telefone_raw) : null;
                $nome = !empty($devedor) ? trim((string)$devedor) : null;
                $document = !empty($cpf) ? preg_replace('/\D/', '', (string)$cpf) : null;
                $valor_clean = !empty($valor) ? floatval(str_replace(',', '.', (string)$valor)) : null;
                $dias_clean = !empty($dias_atraso) ? intval($dias_atraso) : null;
                $carteira_clean = !empty($carteira) ? trim((string)$carteira) : null;

                // Converter data de d/m/Y ou m/d/Y para Y-m-d se necessário
                $data_venc_clean = null;
                if (!empty($data_venc)) {
                    $data_str = trim((string)$data_venc);
                    if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $data_str)) {
                        $parts = explode('/', $data_str);
                        // Detecta formato: se primeiro número > 12, é DD/MM/YYYY, senão é MM/DD/YYYY
                        if (intval($parts[0]) > 12) {
                            // DD/MM/YYYY format
                            $day = str_pad($parts[0], 2, '0', STR_PAD_LEFT);
                            $month = str_pad($parts[1], 2, '0', STR_PAD_LEFT);
                        } else {
                            // MM/DD/YYYY format
                            $month = str_pad($parts[0], 2, '0', STR_PAD_LEFT);
                            $day = str_pad($parts[1], 2, '0', STR_PAD_LEFT);
                        }
                        $year = $parts[2];
                        $data_venc_clean = $year . '-' . $month . '-' . $day;
                    }
                }

                // Validação básica
                if (empty($nome) && empty($telefone)) {
                    \Log::warning('Linha ' . ($rowNum + 1) . ' ignorada: sem nome e telefone');
                    continue;
                }

                // Log após limpeza
                \Log::info('Linha ' . ($rowNum + 1) . ' processada: nome=' . $nome . ', telefone=' . $telefone . ', valor=' . $valor_clean . ', data=' . $data_venc_clean);

                // Salvar em contato_dados
                try {
                    ContatoDados::create([
                        'id_contrato' => $numero_contrato,
                        'contato_id' => $contato->id,
                        'telefone' => $telefone,
                        'nome' => $nome ?? 'Cliente',
                        'document' => $document,
                        'cod_cliente' => substr((string)$numero_contrato, 0, 255),
                        'data_vencimento' => $data_venc_clean,
                        'dias_atraso' => $dias_clean,
                        'valor' => $valor_clean,
                        'carteira' => $carteira_clean,
                    ]);
                    $importados++;
                    \Log::info('Linha ' . ($rowNum + 1) . ' salva com sucesso');
                } catch (\Exception $e) {
                    \Log::error('Erro ao salvar linha ' . ($rowNum + 1) . ': ' . $e->getMessage());
                }
            }

            \Log::info('Planilha processada para campanha ' . $campanha->id . ': ' . $importados . ' contatos importados');

            // Deletar arquivo temporário
            Storage::delete($path);

        } catch (\Exception $e) {
            \Log::error('Erro ao processar planilha: ' . $e->getMessage());
            \Log::error('Stack: ' . $e->getTraceAsString());
        }
    }
}
