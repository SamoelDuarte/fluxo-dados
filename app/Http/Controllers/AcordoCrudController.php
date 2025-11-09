<?php

namespace App\Http\Controllers;

use App\Models\Acordo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AcordoCrudController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $acordos = Acordo::paginate(15);
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
}
