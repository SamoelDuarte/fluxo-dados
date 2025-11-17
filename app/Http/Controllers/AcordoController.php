<?php

namespace App\Http\Controllers;

use App\Models\Acordo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AcordoController extends Controller
{
    /**
     * Listar todos os acordos
     */
    public function index(Request $request)
    {
        $query = Acordo::query();

        // Filtros opcionais
        if ($request->filled('documento')) {
            $query->where('documento', 'like', '%' . $request->documento . '%');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('data_inicio')) {
            $query->whereDate('created_at', '>=', $request->data_inicio);
        }

        if ($request->filled('data_fim')) {
            $query->whereDate('created_at', '<=', $request->data_fim);
        }

        // Ordenar por desc (mais recentes primeiro)
        $acordos = $query->orderBy('id', 'desc')->paginate(15);

        return view('acordos.index', compact('acordos'));
    }

    /**
     * Criar novo acordo
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'nome' => 'required|string',
                'documento' => 'required|string|unique:acordos',
                'telefone' => 'required|string',
                'phone_number_id' => 'nullable|string',
                'status' => 'required|in:pendente,ativo,finalizado,cancelado',
                'texto' => 'nullable|string',
            ]);

            $acordo = Acordo::create($validated);
            
            Log::info('Acordo criado: ' . $acordo->id . ' - ' . $acordo->nome);

            return response()->json([
                'message' => 'Acordo criado com sucesso',
                'data' => $acordo
            ], 201);
        } catch (\Exception $e) {
            Log::error('Erro ao criar acordo: ' . $e->getMessage());
            return response()->json([
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Buscar acordo por ID
     */
    public function show($id)
    {
        $acordo = Acordo::find($id);
        
        if (!$acordo) {
            return response()->json([
                'error' => 'Acordo não encontrado'
            ], 404);
        }

        return response()->json($acordo);
    }

    /**
     * Buscar acordo por documento
     */
    public function porDocumento($documento)
    {
        $acordo = Acordo::porDocumento($documento)->first();
        
        if (!$acordo) {
            return response()->json([
                'error' => 'Acordo não encontrado para este documento'
            ], 404);
        }

        return response()->json($acordo);
    }

    /**
     * Buscar acordos ativos
     */
    public function ativos()
    {
        $acordos = Acordo::ativos()->get();
        return response()->json($acordos);
    }

    /**
     * Buscar acordos pendentes
     */
    public function pendentes()
    {
        $acordos = Acordo::pendentes()->get();
        return response()->json($acordos);
    }

    /**
     * Atualizar acordo
     */
    public function update(Request $request, $id)
    {
        try {
            $acordo = Acordo::find($id);
            
            if (!$acordo) {
                return response()->json([
                    'error' => 'Acordo não encontrado'
                ], 404);
            }

            $validated = $request->validate([
                'nome' => 'sometimes|string',
                'documento' => 'sometimes|string',
                'telefone' => 'sometimes|string',
                'phone_number_id' => 'sometimes|nullable|string',
                'status' => 'sometimes|in:pendente,ativo,finalizado,cancelado',
                'texto' => 'sometimes|nullable|string',
            ]);

            $acordo->update($validated);
            
            Log::info('Acordo atualizado: ' . $acordo->id);

            return response()->json([
                'message' => 'Acordo atualizado com sucesso',
                'data' => $acordo
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao atualizar acordo: ' . $e->getMessage());
            return response()->json([
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Deletar acordo
     */
    public function destroy($id)
    {
        try {
            $acordo = Acordo::find($id);
            
            if (!$acordo) {
                return response()->json([
                    'error' => 'Acordo não encontrado'
                ], 404);
            }

            $acordo->delete();
            
            Log::info('Acordo deletado: ' . $id);

            return response()->json([
                'message' => 'Acordo deletado com sucesso'
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao deletar acordo: ' . $e->getMessage());
            return response()->json([
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Atualizar status do acordo
     */
    public function atualizarStatus(Request $request, $id)
    {
        try {
            $acordo = Acordo::find($id);
            
            if (!$acordo) {
                return response()->json([
                    'error' => 'Acordo não encontrado'
                ], 404);
            }

            $validated = $request->validate([
                'status' => 'required|in:pendente,ativo,finalizado,cancelado',
            ]);

            $acordo->update($validated);
            
            Log::info('Status do acordo atualizado: ' . $id . ' -> ' . $validated['status']);

            return response()->json([
                'message' => 'Status atualizado com sucesso',
                'data' => $acordo
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao atualizar status: ' . $e->getMessage());
            return response()->json([
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Armazenar acordo via requisição (alias para store)
     * POST /api/acordos/store
     * 
     * Exemplo de requisição:
     * {
     *   "nome": "João Silva",
     *   "documento": "12345678900",
     *   "telefone": "11986123456",
     *   "phone_number_id": "1234567890123456",
     *   "status": "ativo",
     *   "texto": "Acordo de negociação de dívida no Cartão Havan"
     * }
     */
    public function storeAcordo(Request $request)
    {
        try {
            Log::info('Recebida requisição para criar acordo: ' . json_encode($request->all()));

            $validated = $request->validate([
                'nome' => 'required|string|max:255',
                'documento' => 'required|string|max:20',
                'telefone' => 'required|string|max:20',
                'phone_number_id' => 'nullable|string|max:255',
                'status' => 'required|in:pendente,ativo,finalizado,cancelado',
                'texto' => 'nullable|string',
            ]);

            // Verificar se já existe acordo com este documento
            $acordoExistente = Acordo::porDocumento($validated['documento'])->first();
            if ($acordoExistente) {
                return response()->json([
                    'error' => 'Já existe um acordo cadastrado para este documento',
                    'acordo_existente' => $acordoExistente
                ], 409);
            }

            $acordo = Acordo::create($validated);
            
            Log::info('✓ Acordo criado com sucesso: ID ' . $acordo->id . ' - ' . $acordo->nome . ' (' . $acordo->documento . ')');

            return response()->json([
                'message' => 'Acordo criado com sucesso',
                'data' => $acordo,
                'id' => $acordo->id
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Validação falhou: ' . json_encode($e->errors()));
            return response()->json([
                'error' => 'Erro de validação',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('✗ Erro ao criar acordo: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erro ao criar acordo',
                'message' => $e->getMessage()
            ], 400);
        }
    }
}

