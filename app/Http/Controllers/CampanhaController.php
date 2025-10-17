<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Contato;

class CampanhaController extends Controller
{
    public function campanhas()
    {
        // Placeholder list view for campanhas
        return view('campanhas.index');
    }

    public function contatos()
    {
        $total = Contato::count();
        return view('campanhas.contatos', ['totalContatos' => $total]);
    }

    public function relatorio()
    {
        // Placeholder report view
        return view('campanhas.relatorio');
    }
}
