<?php

namespace App\Http\Controllers;

use App\Models\Acordo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;

class AcordoCrudController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Acordo::query();

        // Filtro por status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filtro por data inicial
        if ($request->filled('data_inicio')) {
            $query->whereDate('created_at', '>=', $request->data_inicio);
        }

        // Filtro por data final
        if ($request->filled('data_fim')) {
            $query->whereDate('created_at', '<=', $request->data_fim);
        }

        // Filtro por documento
        if ($request->filled('documento')) {
            $query->where('documento', 'LIKE', '%' . $request->documento . '%');
        }

        // Ordenação
        $query->orderBy('created_at', 'DESC');

        $acordos = $query->paginate(15);
        
        return view('acordos.index', compact('acordos'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('acordos.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'nome' => 'required|string|max:255',
                'documento' => 'required|string|unique:acordos,documento',
                'telefone' => 'required|string|max:20',
                'phone_number_id' => 'nullable|string',
                'status' => 'required|in:pendente,ativo,finalizado,cancelado',
                'texto' => 'nullable|string',
            ]);

            $acordo = Acordo::create($validated);
            
            Log::info('✓ Acordo criado com sucesso: ID ' . $acordo->id . ' - ' . $acordo->nome);

            return redirect()->route('acordos.index')->with('success', 'Acordo criado com sucesso!');
        } catch (\Exception $e) {
            Log::error('✗ Erro ao criar acordo: ' . $e->getMessage());
            return back()->with('error', 'Erro ao criar acordo: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Acordo $acordo)
    {
        return view('acordos.show', compact('acordo'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Acordo $acordo)
    {
        return view('acordos.edit', compact('acordo'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Acordo $acordo)
    {
        try {
            $validated = $request->validate([
                'nome' => 'required|string|max:255',
                'documento' => 'required|string|unique:acordos,documento,' . $acordo->id,
                'telefone' => 'required|string|max:20',
                'phone_number_id' => 'nullable|string',
                'status' => 'required|in:pendente,ativo,finalizado,cancelado',
                'texto' => 'nullable|string',
            ]);

            $acordo->update($validated);
            
            Log::info('✓ Acordo atualizado com sucesso: ID ' . $acordo->id);

            return redirect()->route('acordos.index')->with('success', 'Acordo atualizado com sucesso!');
        } catch (\Exception $e) {
            Log::error('✗ Erro ao atualizar acordo: ' . $e->getMessage());
            return back()->with('error', 'Erro ao atualizar acordo: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Acordo $acordo)
    {
        try {
            $acordo->delete();
            
            Log::info('✓ Acordo deletado com sucesso: ID ' . $acordo->id);

            return redirect()->route('acordos.index')->with('success', 'Acordo deletado com sucesso!');
        } catch (\Exception $e) {
            Log::error('✗ Erro ao deletar acordo: ' . $e->getMessage());
            return back()->with('error', 'Erro ao deletar acordo: ' . $e->getMessage());
        }
    }

    /**
     * Exportar acordos em Excel
     */
    public function exportExcel(Request $request)
    {
        try {
            $query = Acordo::query();

            // Aplicar os mesmos filtros
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('data_inicio')) {
                $query->whereDate('created_at', '>=', $request->data_inicio);
            }

            if ($request->filled('data_fim')) {
                $query->whereDate('created_at', '<=', $request->data_fim);
            }

            if ($request->filled('documento')) {
                $query->where('documento', 'LIKE', '%' . $request->documento . '%');
            }

            $query->orderBy('created_at', 'DESC');
            $acordos = $query->get();

            // Cria novo spreadsheet
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Acordos');

            // Define as colunas e estilos do cabeçalho
            $columns = ['ID', 'Nome', 'Documento', 'Telefone', 'Phone Number ID', 'Texto', 'Status', 'Criado em', 'Atualizado em'];
            $columnLetters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'];

            foreach ($columnLetters as $index => $letter) {
                $cell = $sheet->getCell($letter . '1');
                $cell->setValue($columns[$index]);
                
                // Estilo do cabeçalho
                $cell->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF4472C4');
                $cell->getStyle()->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFFFF'))->setBold(true);
            }

            // Adiciona dados
            $row = 2;
            foreach ($acordos as $acordo) {
                $sheet->setCellValue('A' . $row, $acordo->id);
                $sheet->setCellValue('B' . $row, $acordo->nome);
                $sheet->setCellValue('C' . $row, $acordo->documento);
                $sheet->setCellValue('D' . $row, $acordo->telefone);
                $sheet->setCellValue('E' . $row, $acordo->phone_number_id ?? '-');
                $sheet->setCellValue('F' . $row, $acordo->texto ?? '-');
                $sheet->setCellValue('G' . $row, ucfirst($acordo->status));
                $sheet->setCellValue('H' . $row, $acordo->created_at->format('d/m/Y H:i'));
                $sheet->setCellValue('I' . $row, $acordo->updated_at->format('d/m/Y H:i'));
                
                $row++;
            }

            // Auto size das colunas
            foreach ($columnLetters as $letter) {
                $sheet->getColumnDimension($letter)->setAutoSize(true);
            }

            // Cria o arquivo
            $fileName = 'acordos_' . date('d_m_Y_H_i_s') . '.xlsx';
            $writer = new Xlsx($spreadsheet);

            // Headers para download
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('Cache-Control: max-age=0');

            $writer->save('php://output');
            exit;

        } catch (\Exception $e) {
            Log::error('✗ Erro ao exportar acordos: ' . $e->getMessage());
            return back()->with('error', 'Erro ao exportar: ' . $e->getMessage());
        }
    }
}
