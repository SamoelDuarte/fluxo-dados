<?php

namespace App\Http\Controllers;

use App\Models\ValoresFrete;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\HeadingRowImport;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExcelController extends Controller
{
    public function index()
    {
        return view('excel.upload'); // Exibe a view para upload
    }

    public function upload(Request $request)
{
    $request->validate([
        'file' => 'required|file|mimes:xlsx',
    ]);

    // Caminho do arquivo carregado
    $filePath = $request->file('file')->getPathname();

    // Ler todas as linhas da planilha (ignorando a primeira linha, que é o cabeçalho)
    $rows = Excel::toArray([], $filePath)[0]; 
    $filteredRows = array_slice($rows, 1); // Ignora cabeçalho

    // Criar a nova planilha
    $newSpreadsheet = new Spreadsheet();
    $sheet = $newSpreadsheet->getActiveSheet();

    // Adicionar os cabeçalhos
    $sheet->setCellValue('A1', 'regiao');
    $sheet->setCellValue('B1', 'cep_inicial');
    $sheet->setCellValue('C1', 'cep_final');
    $sheet->setCellValue('D1', 'peso_inicial');
    $sheet->setCellValue('E1', 'peso_final');
    $sheet->setCellValue('F1', 'valor_frete');
    $sheet->setCellValue('G1', 'valor_extra_por_peso');
    $sheet->setCellValue('H1', 'dias_para_entrega');
    $sheet->setCellValue('I1', 'porcentagem_adicional');

    // Estilo do cabeçalho
    $sheet->getStyle('A1:I1')->applyFromArray([
        'font' => ['bold' => true],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders' => [
            'bottom' => ['borderStyle' => Border::BORDER_THIN],
        ],
    ]);

    $pesoInicialCount = 0; 
    $pesoFinalCount = 0;   

    $rowIndex = 2; 
    $pesoFinal = 0;
    $pesoInicial = 0;

    foreach ($filteredRows as $row) {
        // Agora a região é simplesmente o nome completo (coluna 3)
        $regiao = $row[3] ?? '';  

        $cepInicial = $row[0] ?? ''; // pode ajustar se o CEP vier em outra coluna
        $cepFinal   = $row[1] ?? ''; 

        // Lógica de peso
        if ($pesoInicialCount == 0) {
            $pesoInicial = 0;
            $pesoFinal = 0.5;
        } else if ($pesoInicialCount == 1) {
            $pesoInicial = 0.501;
            $pesoFinal = 1;
        } else if ($pesoInicialCount == 2) {
            $pesoInicial = 1.001;
        } else if ($pesoInicialCount == 3) {
            $pesoInicial = 2.001;
        } else if ($pesoInicialCount > 3) {
            $pesoInicial = $pesoInicial + 1;
        }

        $InicialConvert = $pesoInicialCount != 0 ? $pesoInicial : 0;

        // Buscar valor do frete
        $valorFrete = ValoresFrete::where('peso', strval($pesoFinal))->first();
        $valorFreteValue = $valorFrete ? $valorFrete->{$row[10] ?? ''} : 0; 

        $prazo = $row[7] ?? 0;

        // Preencher linhas
        $sheet->setCellValue("A{$rowIndex}", $regiao);
        $sheet->setCellValue("B{$rowIndex}", $cepInicial);
        $sheet->setCellValue("C{$rowIndex}", $cepFinal);
        $sheet->setCellValue("D{$rowIndex}", $InicialConvert);
        $sheet->setCellValue("E{$rowIndex}", $pesoFinal);
        $sheet->setCellValue("F{$rowIndex}", $valorFreteValue);
        $sheet->setCellValue("G{$rowIndex}", 0);
        $sheet->setCellValue("H{$rowIndex}", $prazo);
        $sheet->setCellValue("I{$rowIndex}", 0);

        // Incrementar
        $pesoInicialCount++;
        $pesoFinalCount++;
        $pesoFinal++;

        if ($pesoInicialCount > 29) $pesoInicialCount = 0;
        if ($pesoFinalCount > 29) $pesoFinalCount = 0;

        $rowIndex++;
    }

    // Salvar e retornar
    $newFilePath = storage_path('app/public/resultado.xlsx');
    $writer = new Xlsx($newSpreadsheet);
    $writer->save($newFilePath);

    return response()->download($newFilePath)->deleteFileAfterSend();
}



    public function insertUpload(Request $request)
    {
        // Validar o arquivo
        $request->validate([
            'insert_into' => 'required|mimes:xlsx,xls,csv|max:2048',
        ]);

        // Obter o arquivo
        $file = $request->file('insert_into');

        // Usar Laravel Excel para importar o arquivo
        $data = Excel::toArray([], $file)[0]; // O índice 0 refere-se à primeira aba da planilha

        // Assumir que a primeira linha contém os títulos
        $headers = $data[0];

        // Iterar sobre as linhas da planilha (ignorando a primeira linha, que são os cabeçalhos)
        foreach ($data as $index => $row) {
            if ($index == 0) continue; // Pular a primeira linha (cabeçalhos)

            // Mapeamento de dados conforme os cabeçalhos
            $values = [];

            // Definir o 'id' como o número da linha (index + 1, já que o index começa de 0)
            $values['id'] = $index;

            foreach ($headers as $key => $header) {
                if ($header == 'peso') {
                    $values['peso'] = $row[$key]; // Preencher a coluna 'peso' com o valor da célula correspondente
                } else {
                    // Preencher as outras colunas conforme os cabeçalhos
                    $values[$header] = $row[$key];
                }
            }

            // Atualizar o registro se o ID já existir
            $existingRecord = ValoresFrete::find($index); // Procurar o registro com o id correspondente
            if ($existingRecord) {
                // Se o registro existir, atualiza os valores
                $existingRecord->update($values);
            }
        }

        return back()->with('success', 'Dados inseridos com sucesso!');
    }
}
