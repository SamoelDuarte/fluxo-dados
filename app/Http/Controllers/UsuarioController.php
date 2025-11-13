<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UsuarioController extends Controller
{
    /**
     * Construtor - Aplica middleware de autenticação
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Exibe a lista de usuários
     */
    public function index()
    {
        return view('usuarios.index');
    }

    /**
     * Retorna usuários em JSON para DataTable
     */
    public function listar()
    {
        $usuarios = User::select('id', 'name', 'email', 'created_at')->get();
        return response()->json(['data' => $usuarios]);
    }

    /**
     * Salva novo usuário ou atualiza existente
     */
    public function salvar(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $request->id,
            'password' => $request->id ? 'nullable|min:6' : 'required|min:6|confirmed',
            'password_confirmation' => $request->id ? 'nullable' : 'required',
        ], [
            'name.required' => 'Nome é obrigatório',
            'email.required' => 'Email é obrigatório',
            'email.email' => 'Email inválido',
            'email.unique' => 'Este email já existe',
            'password.required' => 'Senha é obrigatória',
            'password.min' => 'Senha deve ter no mínimo 6 caracteres',
            'password.confirmed' => 'As senhas não conferem',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $data = $request->only(['name', 'email']);
            
            // Se estiver editando e houver nova senha
            if ($request->id) {
                $usuario = User::findOrFail($request->id);
                if ($request->password) {
                    $data['password'] = Hash::make($request->password);
                }
                $usuario->update($data);
                $mensagem = 'Usuário atualizado com sucesso';
            } else {
                // Novo usuário
                $data['password'] = Hash::make($request->password);
                User::create($data);
                $mensagem = 'Usuário criado com sucesso';
            }

            return response()->json(['success' => true, 'message' => $mensagem]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erro ao salvar: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Obtém dados de um usuário para edição
     */
    public function editar($id)
    {
        try {
            $usuario = User::findOrFail($id);
            return response()->json(['success' => true, 'data' => $usuario]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Usuário não encontrado'], 404);
        }
    }

    /**
     * Deleta um usuário
     */
    public function deletar($id)
    {
        try {
            // Evita deletar o próprio usuário
            if (auth()->id() == $id) {
                return response()->json(['success' => false, 'message' => 'Você não pode deletar sua própria conta'], 403);
            }

            $usuario = User::findOrFail($id);
            $usuario->delete();
            return response()->json(['success' => true, 'message' => 'Usuário deletado com sucesso']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erro ao deletar: ' . $e->getMessage()], 500);
        }
    }
}
