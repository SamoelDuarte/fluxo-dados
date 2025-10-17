<?php

namespace App\Http\Controllers;


use App\Models\Lote;
use App\Models\Contrato;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;

class UploadController extends Controller
{
    public function index()
    {
        return view('uploads.index'); // View onde o formulário estará
    }

    public function upload(Request $request)
    {
        ini_set('memory_limit', '512M'); // Aumenta limite de memória para arquivos grandes
        try {
            $request->validate([
                'file' => 'required|file|mimes:xlsx,xls,csv|max:10240', // agora aceita CSV também
            ]);

            // Salvar arquivo no storage
            $file = $request->file('file');
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('uploads', $filename, 'public');

            // Criar o lote
            $lote = Lote::create(['created_at' => now()]);

            // Lê o arquivo com o PhpSpreadsheet
            $filePath = storage_path('app/public/' . $path);
            $inputFileType = IOFactory::identify($filePath);
            \Log::info('Input file type', ['type' => $inputFileType]);

            if ($inputFileType === 'Csv') {
                // Use fgetcsv for CSV
                $rows = [];
                if (($handle = fopen($filePath, 'r')) !== false) {
                    while (($data = fgetcsv($handle, 0, ';', '"')) !== false) {
                        $rows[] = $data;
                    }
                    fclose($handle);
                }
            } else {
                // For Excel
                $reader = IOFactory::createReader($inputFileType);
                $spreadsheet = $reader->load($filePath);
                $worksheet = $spreadsheet->getActiveSheet();
                $rows = $worksheet->toArray();
            }

            if (empty($rows)) {
                return response()->json(['error' => 'Planilha vazia ou inválida.'], 422);
            }

            // Detecta se a primeira linha é cabeçalho
            $headers = array_map('strtolower', array_shift($rows));

            \Log::info('Headers detectados', $headers);

            // Processa as linhas
            foreach ($rows as $row) {

                \Log::info('Processing row', $row);

                if (count($headers) !== count($row)) {
                    \Log::warning('Row length mismatch', ['headers_count' => count($headers), 'row_count' => count($row), 'row' => $row]);
                    continue;
                }

                // Mapeia automaticamente pelos nomes das colunas
                $data = array_combine($headers, $row);

                if (empty(array_filter($data))) {
                    continue; // pula linhas totalmente vazias
                }

                try {
                    Contrato::create([
                        'carteira_id' => $data['carteira_id'] ?? $data['carteira'] ?? null,
                        'contrato'    => $data['contrato'] ?? null,
                        'documento'   => $data['documento'] ?? $data['cpf'] ?? null,
                        'nome'        => $data['nome'] ?? $data['cliente'] ?? null,
                        'lote_id'     => $lote->id,
                    ]);
                } catch (\Exception $e) {
                    \Log::error('Erro ao criar contrato no upload: ' . $e->getMessage(), ['data' => $data, 'row' => $row]);
                    // Continue para o próximo, ou retorne erro
                    continue;
                }
            }

            return response()->json([
                'success' => 'Arquivo enviado e processado com sucesso!',
                'lote' => $lote,
                'contratos_importados' => Contrato::where('lote_id', $lote->id)->count(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Erro geral no upload: ' . $e->getMessage());
            return response()->json(['error' => 'Erro interno no servidor: ' . $e->getMessage()], 500);
        }
    }


    public function getLotes()
    {
        // Definir a data limite de 2 dias atrás
        $dataLimite = Carbon::now()->subDays(5)->startOfDay();

        $lotes = Lote::with(['contratos.planilhas' => function ($query) {
            // Carregar as planilhas que têm valor_proposta_1 diferente de null
            $query->whereNotNull('valor_proposta_1');
        }])
            // Filtrar lotes criados nos últimos 2 dias
            ->where('created_at', '>=', $dataLimite)
            ->get()
            ->map(function ($lote) {
                $quantidadeErro = $lote->contratos->where('erro', true)->count();

                // Contar contratos que têm planilhas com valor_proposta_1 diferente de null
                $quantidadeSucesso = $lote->contratos->filter(function ($contrato) {
                    return $contrato->planilhas->isNotEmpty();
                })->count();

                return [
                    'id' => $lote->id,
                    'data' => $lote->created_at->format('d/m/Y'), // Formatar a data
                    'quantidade' => $lote->contratos->count(), // Contagem de contratos
                    'quantidade_erro' => $quantidadeErro, // Contagem de erros
                    'quantidade_sucesso' => $quantidadeSucesso // Contagem de sucessos
                ];
            });

        return response()->json($lotes);
    }


    public function getContratosComErro($loteId)
    {
        $contratosComErro = Contrato::where('lote_id', $loteId)
            ->where('erro', true)
            ->select('id', 'carteira', 'contrato', 'nome', 'documento', 'mensagem_erro') // Inclua 'mensagem_erro' para o modal
            ->get();

        return response()->json($contratosComErro);
    }



    public function getCarteirasByLote($loteId)
    {
        $contratos = Contrato::where('lote_id', $loteId)->get();

        return response()->json($contratos);
    }

    public function uploadChunk(Request $request)
    {
        ini_set('memory_limit', '512M');

        $chunk = $request->file('file');
        $chunkNumber = $request->input('chunkNumber');
        $totalChunks = $request->input('totalChunks');
        $fileName = $request->input('fileName');
        $uploadId = $request->input('uploadId'); // Para identificar o upload

        if (!$chunk || !is_numeric($chunkNumber) || !is_numeric($totalChunks) || !$fileName) {
            return response()->json(['error' => 'Parâmetros inválidos para chunk.'], 400);
        }

        // Cria diretório temp se não existir
        $tempDir = storage_path('app/temp');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $tempPath = $tempDir . '/' . $uploadId . '_' . $fileName . '_chunk_' . $chunkNumber;
        $chunk->move($tempDir, basename($tempPath));

        // Verifica se todos os chunks foram recebidos
        $chunksReceived = 0;
        for ($i = 0; $i < $totalChunks; $i++) {
            if (file_exists($tempDir . '/' . $uploadId . '_' . $fileName . '_chunk_' . $i)) {
                $chunksReceived++;
            }
        }

        if ($chunksReceived == $totalChunks) {
            // Assembla o arquivo
            $finalPath = storage_path('app/public/uploads/' . $fileName);
            $out = fopen($finalPath, 'wb');
            for ($i = 0; $i < $totalChunks; $i++) {
                $chunkPath = $tempDir . '/' . $uploadId . '_' . $fileName . '_chunk_' . $i;
                if (file_exists($chunkPath)) {
                    $in = fopen($chunkPath, 'rb');
                    stream_copy_to_stream($in, $out);
                    fclose($in);
                    unlink($chunkPath);
                }
            }
            fclose($out);

            // Agora processa o arquivo como no upload normal
            try {
                // Criar o lote
                $lote = Lote::create(['created_at' => now()]);

                // Lê o arquivo
                $inputFileType = IOFactory::identify($finalPath);
                \Log::info('Input file type chunk', ['type' => $inputFileType]);

                if ($inputFileType === 'Csv') {
                    // Use fgetcsv for CSV
                    $rows = [];
                    if (($handle = fopen($finalPath, 'r')) !== false) {
                        while (($data = fgetcsv($handle, 0, ';', '"')) !== false) {
                            $rows[] = $data;
                        }
                        fclose($handle);
                    }
                } else {
                    // For Excel
                    $reader = IOFactory::createReader($inputFileType);
                    $spreadsheet = $reader->load($finalPath);
                    $worksheet = $spreadsheet->getActiveSheet();
                    $rows = $worksheet->toArray();
                }

                if (empty($rows)) {
                    return response()->json(['error' => 'Planilha vazia.'], 422);
                }

                $headers = array_map('strtolower', array_shift($rows));

                foreach ($rows as $row) {
                    if (empty(array_filter($row))) continue;

                    \Log::info('Processing row', $row);

                    if (count($headers) !== count($row)) {
                        \Log::warning('Row length mismatch', ['headers_count' => count($headers), 'row_count' => count($row), 'row' => $row]);
                        continue;
                    }

                    $data = array_combine($headers, $row);
                    try {
                        Contrato::create([
                            'carteira_id' => $data['carteira_id'] ?? $data['carteira'] ?? null,
                            'contrato'    => $data['contrato'] ?? null,
                            'documento'   => $data['documento'] ?? $data['cpf'] ?? null,
                            'nome'        => $data['nome'] ?? $data['cliente'] ?? null,
                            'lote_id'     => $lote->id,
                        ]);
                    } catch (\Exception $e) {
                        \Log::error('Erro ao criar contrato: ' . $e->getMessage(), ['data' => $data]);
                        continue;
                    }
                }

                return response()->json([
                    'success' => 'Upload concluído!',
                    'lote' => $lote,
                    'contratos_importados' => Contrato::where('lote_id', $lote->id)->count(),
                ]);
            } catch (\Exception $e) {
                \Log::error('Erro no processamento do arquivo: ' . $e->getMessage());
                return response()->json(['error' => 'Erro interno: ' . $e->getMessage()], 500);
            }
        }

        return response()->json(['message' => 'Chunk recebido.']);
    }
}
