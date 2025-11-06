@extends('layouts.app')

@section('title', 'Campanhas')

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Campanhas</h5>
            <a href="{{ route('campanhas.crud.create') }}" class="btn btn-primary">Nova Campanha</a>
        </div>
        <div class="card-body">
            <table class="table table-striped">
                <thead>
                <tr>
                    <th>#</th>
                    <th>Imagem</th>
                    <th>Nome</th>
                    <th>Status</th>
                    <th>Contatos</th>
                    <th>Total Enviado</th>
                    <th>Ações</th>
                </tr>
                </thead>
                <tbody>
                @foreach($campanhas as $campanha)
                    <tr>
                        <td>{{ $campanha->id }}</td>
                        <td>
                            @if($campanha->imagemPrincipal)
                                <img src="{{ asset('storage/' . $campanha->imagemPrincipal->caminho_imagem) }}" alt="Imagem" style="width: 60px; height: 60px; object-fit: cover; border-radius: 4px;">
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>{{ $campanha->name }}</td>
                        <td>{{ $campanha->status === 'paused' ? 'Pausado' : 'Rodando' }}</td>
                        <td>{{ $campanha->contatos()->join('contato_dados', 'contatos.id', '=', 'contato_dados.contato_id')->where('contato_dados.send', 0)->count() }}</td>
                        <td>{{ $campanha->contatos()->join('contato_dados', 'contatos.id', '=', 'contato_dados.contato_id')->where('contato_dados.send', 1)->count() }}</td>
                        <td>
                            <a href="{{ route('campanhas.crud.edit', $campanha) }}" class="btn btn-sm btn-secondary">Editar</a>
                            <form action="{{ route('campanhas.crud.destroy', $campanha) }}" method="POST" style="display:inline-block;" onsubmit="return confirm('Remover campanha?');">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-danger">Remover</button>
                            </form>
                            @if($campanha->status === 'playing')
                                <form action="{{ route('campanhas.crud.pause', $campanha) }}" method="POST" style="display:inline-block;">
                                    @csrf
                                    <button class="btn btn-sm btn-warning">Pause</button>
                                </form>
                            @else
                                <form action="{{ route('campanhas.crud.play', $campanha) }}" method="POST" style="display:inline-block;">
                                    @csrf
                                    <button class="btn btn-sm btn-success">Iniciar</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            {{ $campanhas->links() }}
        </div>
    </div>
@endsection
