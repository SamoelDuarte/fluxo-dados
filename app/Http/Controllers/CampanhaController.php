<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CampanhaController extends Controller
{
    public function campanhas()
    {
        // Placeholder list view for campanhas
        return view('campanhas.index');
    }

    public function contatos()
    {
        // Placeholder contacts management
        return view('campanhas.contatos');
    }

    public function relatorio()
    {
        // Placeholder report view
        return view('campanhas.relatorio');
    }
}
