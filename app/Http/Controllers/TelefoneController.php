<?php

namespace App\Http\Controllers;

use App\Models\Telefone;
use Illuminate\Http\Request;

class TelefoneController extends Controller
{
    public function index()
    {
        $telefones = Telefone::orderBy('id', 'desc')->paginate(20);
        return view('telefones.index', compact('telefones'));
    }

    public function create()
    {
        return view('telefones.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'phone_number_id' => 'nullable|string|max:255',
            'phone_number' => 'required|string|max:255',
        ]);

        Telefone::create($data);

        return redirect()->route('telefones.index')->with('success', 'Telefone criado com sucesso.');
    }

    public function edit(Telefone $telefone)
    {
        return view('telefones.edit', compact('telefone'));
    }

    public function update(Request $request, Telefone $telefone)
    {
        $data = $request->validate([
            'phone_number_id' => 'nullable|string|max:255',
            'phone_number' => 'required|string|max:255',
        ]);

        $telefone->update($data);

        return redirect()->route('telefones.index')->with('success', 'Telefone atualizado com sucesso.');
    }

    public function destroy(Telefone $telefone)
    {
        $telefone->delete();
        return redirect()->route('telefones.index')->with('success', 'Telefone removido.');
    }
}
