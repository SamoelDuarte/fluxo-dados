<?php

namespace App\Jobs;

use App\Models\ContatoImport;
use App\Models\ContatoDados;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class ProcessContatoImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $importId;

    public function __construct($importId)
    {
        $this->importId = $importId;
    }

    public function handle()
    {
        $import = ContatoImport::find($this->importId);
        if (!$import) return;

        $import->status = 'processing';
        $import->save();

        $path = storage_path('app/' . $import->file_path);
        if (!file_exists($path)) {
            $import->status = 'failed';
            $import->error = 'File not found';
            $import->save();
            return;
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            $import->status = 'failed';
            $import->error = 'Unable to open file';
            $import->save();
            return;
        }
        // First pass: count rows to set total_rows (excluding header)
        $totalRows = 0;
        $first = true;
        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            if ($first) { $first = false; continue; }
            $totalRows++;
        }

        // Update total_rows and reset file pointer
        $import->total_rows = $totalRows;
        $import->save();

        // Re-open file for processing (rewind)
        fclose($handle);
        $handle = fopen($path, 'r');

        $rowNum = 0;
        $headers = [];
        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            $rowNum++;
            if ($rowNum === 1) {
                $headers = array_map(function ($h) { return Str::lower(Str::slug($h, '_')); }, $row);
                continue;
            }

            // Map
            if (!empty($headers) && count($headers) === count($row)) {
                $mapped = array_combine($headers, $row);
            } else {
                $mapped = [
                    'telefone' => $row[0] ?? null,
                    'nome' => $row[1] ?? null,
                    'document' => $row[2] ?? null,
                    'numero_contrato' => $row[3] ?? null,
                    'data_vencimento' => $row[4] ?? null,
                    'dias_atraso' => $row[5] ?? null,
                    'valor' => $row[6] ?? null,
                    'carteira' => $row[7] ?? null,
                ];
            }

            $data_venc = $mapped['data_vencimento'] ?? null;
            if (preg_match('/\d{2}\/\d{2}\/\d{4}/', $data_venc)) {
                $parts = explode('/', $data_venc);
                $data_venc = $parts[2].'-'.$parts[1].'-'.$parts[0];
            }

            ContatoDados::create([
                'contato_id' => $import->contato_id,
                'telefone' => $mapped['telefone'] ?? null,
                'nome' => $mapped['nome'] ?? null,
                'document' => $mapped['document'] ?? null,
                'numero_contrato' => $mapped['numero_contrato'] ?? null,
                'data_vencimento' => $data_venc,
                'dias_atraso' => is_numeric($mapped['dias_atraso'] ?? null) ? intval($mapped['dias_atraso']) : null,
                'valor' => is_numeric($mapped['valor'] ?? null) ? floatval($mapped['valor']) : null,
                'carteira' => $mapped['carteira'] ?? null,
            ]);

            // Update progress
            $import->increment('processed_rows');
        }

        fclose($handle);

        $import->status = 'completed';
        $import->save();
    }
}
