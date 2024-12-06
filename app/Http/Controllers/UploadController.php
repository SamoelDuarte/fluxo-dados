<?php

namespace App\Http\Controllers;


use App\Models\Lote;
use App\Models\Contrato;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class UploadController extends Controller
{
    public function index()
    {
        return view('uploads.index'); // View onde o formulário estará
    }

    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:10240', // Limite de 10MB
        ]);

        $file = $request->file('file');
        $path = $file->storeAs('uploads', time() . '_' . $file->getClientOriginalName(), 'public');

        $lote = Lote::create(['created_at' => now()]);

        $importedData = Excel::toArray([], storage_path('app/public/' . $path));
        $rows = $importedData[0]; // Primeira planilha

        foreach ($rows as $index => $row) {
            if ($index === 0) continue; // Ignora cabeçalho

            Contrato::create([
                'carteira_id' => $row[0] ?? null,  // Carteira ID pode ser null se não presente
                'contrato' => $row[1] ?? null,     // Contrato pode ser null se não presente
                'documento' => $row[2] ?? null,    // Documento pode ser null se não presente
                'nome' => $row[3] ?? null,         // Nome pode ser null se não presente
                'lote_id' => $lote->id,            // Lote ID, certifique-se que $lote existe e tem o ID
            ]);
        }

        return response()->json([
            'success' => 'Arquivo enviado e processado com sucesso!',
            'lote' => $lote,
            'carteiras' => $lote->carteiras,
        ]);
    }


    public function getLotes()
    {
        $lotes = Lote::with(['contratos.planilhas' => function ($query) {
            // Carregar as planilhas que têm valor_proposta_1 diferente de null
            $query->whereNotNull('valor_proposta_1');
        }])
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

    public function downloadExcel($loteId)
    {
        // Carregar o lote e seus contratos e planilhas associadas
        $lote = Lote::with('contratos.planilhas')->findOrFail($loteId);

        // Filtrando as planilhas associadas ao lote
        $planilhas = $lote->contratos->flatMap(function ($contrato) {
            return $contrato->planilhas;  // Para cada contrato, pegar as planilhas associadas
        });

        // Preparar os dados para exportação
        $carteiras = [];
        foreach ($planilhas as $planilha) {
            $carteiras[] = [
                'cpf' => $planilha->contrato->documento,
                'nome' => $planilha->contrato->nome,
                'empresa' => $planilha->empresa,
                'carteira' => 'Havan',  // Ajuste conforme a lógica do seu sistema
                'valor_atualizado' => $planilha->valor_atualizado,
                'valor_proposta_1' => $planilha->valor_proposta_1,
                'data_vencimento_proposta_1' => $planilha->data_vencimento_proposta_1,
                'quantidade_parcelas_proposta_2' => $planilha->quantidade_parcelas_proposta_2,
                'valor_proposta_2' => $planilha->valor_proposta_2,
                'data_vencimento_proposta_2' => $planilha->data_vencimento_proposta_2,
                'telefone_recado' => $planilha->telefone_recado,
                'linha_digitavel' => $planilha->linha_digitavel,
                'ddd_telefone_1' => $planilha->dddtelefone_1,
                'ddd_telefone_2' => $planilha->dddtelefone_2,
                'ddd_telefone_3' => $planilha->dddtelefone_3,
                'ddd_telefone_4' => $planilha->dddtelefone_4,
                'ddd_telefone_5' => $planilha->dddtelefone_5,
                'ddd_telefone_6' => $planilha->dddtelefone_6,
                'ddd_telefone_7' => $planilha->dddtelefone_7,
                'ddd_telefone_8' => $planilha->dddtelefone_8,
                'ddd_telefone_9' => $planilha->dddtelefone_9,
                'ddd_telefone_10' => $planilha->dddtelefone_10,
            ];
        }

        // Agora você cria a instância da classe LoteExport passando as carteiras
        return Excel::download(new LoteExport($carteiras), "lote_{$loteId}.xlsx");
    }
}
