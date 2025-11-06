<?php

namespace App\Http\Controllers;

use App\Models\ImagemCampanha;
use Illuminate\Http\Request;

class ImagemCampanhaController extends Controller
{
    /**
     * Salvar uma nova imagem
     */
    public function store(Request $request)
    {
        $request->validate([
            'imagem' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120', // 5MB
        ]);

        if ($request->file('imagem')) {
            $path = $request->file('imagem')->store('imagens_campanhas', 'public');
            
            $imagem = ImagemCampanha::create([
                'caminho_imagem' => $path,
            ]);

            return response()->json([
                'success' => true,
                'imagem' => $imagem,
                'url' => asset('storage/' . $path),
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Erro ao salvar imagem',
        ], 400);
    }

    /**
     * Listar todas as imagens disponíveis
     */
    public function listAll()
    {
        $imagens = ImagemCampanha::all();
        
        return response()->json([
            'imagens' => $imagens->map(function ($imagem) {
                return [
                    'id' => $imagem->id,
                    'caminho_imagem' => $imagem->caminho_imagem,
                    'url' => asset('storage/' . $imagem->caminho_imagem),
                ];
            }),
        ]);
    }

    /**
     * Deletar uma imagem
     */
    public function destroy($id)
    {
        $imagem = ImagemCampanha::find($id);
        
        if (!$imagem) {
            return response()->json([
                'success' => false,
                'message' => 'Imagem não encontrada',
            ], 404);
        }

        // Deletar arquivo
        if (file_exists(public_path('storage/' . $imagem->caminho_imagem))) {
            unlink(public_path('storage/' . $imagem->caminho_imagem));
        }

        $imagem->delete();

        return response()->json([
            'success' => true,
            'message' => 'Imagem deletada com sucesso',
        ]);
    }
}
