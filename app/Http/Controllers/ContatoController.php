<?php

namespace App\Http\Controllers;

use App\Models\Contato;
use App\Models\ContatoDados;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\ContatoImport;

class ContatoController extends Controller
{
    public function index()
    {
        $contatos = Contato::orderBy('id', 'desc')->paginate(20);
        return view('contatos.index', compact('contatos'));
    }

    public function create()
    {
        return view('contatos.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'file' => 'required|file',
        ]);

        $file = $request->file('file');
        $ext = Str::lower($file->getClientOriginalExtension());

        // Create contato
        $contato = Contato::create(['name' => $data['name']]);

        // Store file to storage/app/contatos_imports
        $path = $file->store('contatos_imports');

        // Create import record without counting rows here to avoid blocking the request
        $import = ContatoImport::create([
            'contato_id' => $contato->id,
            'file_path' => $path,
            'total_rows' => null,
            'processed_rows' => 0,
            'status' => 'pending',
        ]);

        // Do NOT dispatch a job here. We'll process via AJAX-chunks initiated by the frontend.
        return response()->json(['success' => true, 'import_id' => $import->id]);
    }

    public function processChunk($importId)
    {
        $import = ContatoImport::find($importId);
        if (!$import) {
            return response()->json(['error' => 'Import not found'], 404);
        }

        $path = storage_path('app/' . $import->file_path);
        if (!file_exists($path)) {
            $import->status = 'failed';
            $import->error = 'File not found';
            $import->save();
            return response()->json(['error' => 'File not found'], 422);
        }

        // If total_rows is null, count it first (quick scan)
        if (is_null($import->total_rows)) {
            $h = fopen($path, 'r');
            $total = 0;
            $first = true;
            while (($row = fgetcsv($h, 0, ';')) !== false) {
                if ($first) { $first = false; continue; }
                $total++;
            }
            fclose($h);
            $import->total_rows = $total;
            $import->save();
        }

        $chunkSize = 200; // rows per request
        $processed = $import->processed_rows ?? 0;

        $handle = fopen($path, 'r');
        if ($handle === false) {
            $import->status = 'failed';
            $import->error = 'Unable to open file';
            $import->save();
            return response()->json(['error' => 'Unable to open file'], 422);
        }

        // Skip header
        $rowNum = 0;
        $headers = [];
        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            $rowNum++;
            if ($rowNum === 1) {
                $headers = array_map(function ($h) { return Str::lower(Str::slug($h, '_')); }, $row);
                continue;
            }

            // Skip already processed rows
            if ($rowNum - 2 < $processed) continue;

            // Process this row
            if (!empty($headers) && count($headers) === count($row)) {
                $mapped = array_combine($headers, $row);
            } else {
                $mapped = [
                    'numero' => $row[2] ?? null,
                    'devedor' => $row[1] ?? null,
                    'numero_contrato' => $row[0] ?? null,
                    'grupo' => $row[3] ?? null,
                    'cpf' => $row[4] ?? null,
                    'status' => $row[5] ?? null,
                    'ddd' => $row[6] ?? null,
                    'telefone' => $row[2] ?? null,
                    'id_contrato' => $row[8] ?? null,
                    'codigo_da_carteira' => $row[9] ?? null,
                    'valor_contrato' => $row[10] ?? null,
                    'dias_em_atraso' => $row[11] ?? null,
                    'data_vencimento' => $row[12] ?? null,
                ];
            }

            // Map to standard fields based on headers
            // Tenta primeiro com headers parseados, depois com fallback de índices
            $telefone = $mapped['numero'] ?? $mapped['numero'] ?? null;
            $nome = $mapped['devedor'] ?? $mapped['nome'] ?? null;
            $document = $mapped['cpf'] ?? $mapped['cpfcnpj'] ?? null;
            $cod_cliente = $mapped['id_contrato'] ?? $mapped['numero_contrato'] ?? null;
            $carteira = $mapped['codigo_da_carteira'] ?? $mapped['carteira'] ?? null;
            $valor_str = $mapped['valor_contrato'] ?? $mapped['valor'] ?? null;
            $dias_atraso_str = $mapped['dias_em_atraso'] ?? $mapped['dias_atraso'] ?? null;
            $data_venc_str = $mapped['data_vencimento'] ?? null;

            // Debug: Log para ver a estrutura dos dados
            if ($rowNum === 2) {
                \Log::info('Headers mapeados:', ['headers' => array_keys($mapped)]);
                \Log::info('Primeira linha mapeada:', $mapped);
            }

            // Skip if carteira is 869
            if ($carteira == '869') {
                $import->increment('processed_rows');
                continue;
            }

            // Process date - converte de d/m/Y para Y-m-d
            $data_venc = null;
            if (!empty($data_venc_str)) {
                // Limpa espaços
                $data_venc_str = trim($data_venc_str);
                
                // Verifica se está no formato dd/m/YYYY ou d/m/YYYY
                if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $data_venc_str)) {
                    $parts = explode('/', $data_venc_str);
                    $day = str_pad($parts[0], 2, '0', STR_PAD_LEFT);
                    $month = str_pad($parts[1], 2, '0', STR_PAD_LEFT);
                    $year = $parts[2];
                    $data_venc = $year . '-' . $month . '-' . $day;
                }
            }

            // Process valor (replace comma with dot)
            $valor = null;
            if ($valor_str) {
                $valor_clean = str_replace(',', '.', trim($valor_str));
                $valor = is_numeric($valor_clean) ? floatval($valor_clean) : null;
            }

            // Process dias_atraso
            $dias_atraso = is_numeric($dias_atraso_str) ? intval($dias_atraso_str) : null;

            // Limpa dados de string
            $document = !empty($document) ? preg_replace('/\D/', '', $document) : null;
            $telefone = !empty($telefone) ? preg_replace('/\D/', '', $telefone) : null;
            $nome = !empty($nome) ? trim($nome) : null;
            $carteira = !empty($carteira) ? trim($carteira) : null;
            $cod_cliente = !empty($cod_cliente) ? trim($cod_cliente) : null;

            ContatoDados::create([
                'contato_id' => $import->contato_id,
                'telefone' => $telefone,
                'nome' => $nome,
                'document' => $document,
                'cod_cliente' => $cod_cliente,
                'data_vencimento' => $data_venc,
                'dias_atraso' => $dias_atraso,
                'valor' => $valor,
                'carteira' => $carteira,
            ]);

            $import->increment('processed_rows');
            $processed++;

            // Stop after chunkSize rows
            if ($processed - ($import->processed_rows - 1) >= $chunkSize) {
                break;
            }
        }

        fclose($handle);

        // Update status
        if ($import->processed_rows >= $import->total_rows) {
            $import->status = 'completed';
        } else {
            $import->status = 'processing';
        }
        $import->save();

        return response()->json([
            'status' => $import->status,
            'processed_rows' => $import->processed_rows,
            'total_rows' => $import->total_rows,
        ]);
    }

    public function destroy(Contato $contato)
    {
        // This will cascade delete contato_dados due to foreign key constraint
        $contato->delete();

        // If request expects JSON (AJAX), return JSON, else redirect back with message
        if (request()->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('contatos.index')->with('success', 'Contato removido com sucesso.');
    }

    public function importStatus($importId)
    {
        $import = ContatoImport::find($importId);
        if (!$import) {
            return response()->json(['error' => 'Import not found'], 404);
        }

        return response()->json([
            'status' => $import->status,
            'total_rows' => $import->total_rows,
            'processed_rows' => $import->processed_rows,
        ]);
    }

    private function parseDate($value)
    {
        if (empty($value)) return null;
        // Try Y-m-d or d/m/Y
        if (preg_match('/\d{4}-\d{2}-\d{2}/', $value)) return $value;
        if (preg_match('/\d{2}\/\d{2}\/\d{4}/', $value)) {
            $parts = explode('/', $value);
            return $parts[2].'-'.$parts[1].'-'.$parts[0];
        }
        return null;
    }
}
