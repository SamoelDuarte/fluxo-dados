<?php

namespace App\Http\Controllers;

use App\Models\Campanha;
use App\Models\Contato;
use App\Models\Telefone;
use Illuminate\Http\Request;

class CampanhaCrudController extends Controller
{
    public function index()
    {
        $campanhas = Campanha::orderBy('id', 'desc')->paginate(20);
        return view('campanhas.crud.index', compact('campanhas'));
    }

    public function create()
    {
        $contatos = Contato::orderBy('name')->get();
        $telefones = Telefone::orderBy('phone_number')->get();
        return view('campanhas.crud.create', compact('contatos', 'telefones'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'contatos' => 'array',
            'telefones' => 'array',
        ]);

        $campanha = Campanha::create(['name' => $data['name']]);

        if (!empty($data['contatos'])) $campanha->contatos()->sync($data['contatos']);
        if (!empty($data['telefones'])) $campanha->telefones()->sync($data['telefones']);

        return redirect()->route('campanhas.crud.index')->with('success', 'Campanha criada.');
    }

    public function edit(Campanha $campanha)
    {
        $contatos = Contato::orderBy('name')->get();
        $telefones = Telefone::orderBy('phone_number')->get();
        return view('campanhas.crud.edit', compact('campanha', 'contatos', 'telefones'));
    }

    public function update(Request $request, Campanha $campanha)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'contatos' => 'array',
            'telefones' => 'array',
        ]);

        $campanha->update(['name' => $data['name']]);
        $campanha->contatos()->sync($data['contatos'] ?? []);
        $campanha->telefones()->sync($data['telefones'] ?? []);

        return redirect()->route('campanhas.crud.index')->with('success', 'Campanha atualizada.');
    }

    public function destroy(Campanha $campanha)
    {
        $campanha->delete();
        return redirect()->route('campanhas.crud.index')->with('success', 'Campanha removida.');
    }

    public function play(Campanha $campanha)
    {
        $campanha->update(['status' => 'playing']);
        return redirect()->back()->with('success', 'Campanha iniciada.');
    }

    public function pause(Campanha $campanha)
    {
        $campanha->update(['status' => 'paused']);
        return redirect()->back()->with('success', 'Campanha pausada.');
    }
}
