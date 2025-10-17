@extends('layouts.app')

@section('title', 'Contatos')

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Contatos</h5>
            <a href="{{ route('contatos.create') }}" class="btn btn-primary">Adicionar contatos</a>
        </div>
        <div class="card-body">
            <table class="table table-striped">
                <thead>
                <tr>
                    <th>#</th>
                    <th>Nome</th>
                    <th>Dados</th>
                </tr>
                </thead>
                <tbody>
                @foreach($contatos as $contato)
                    <tr>
                        <td>{{ $contato->id }}</td>
                        <td>{{ $contato->name }}</td>
                        <td>{{ $contato->dados()->count() }} registros</td>
                        <td>
                            <form action="{{ route('contatos.destroy', $contato) }}" method="POST" onsubmit="return confirm('Remover este contato e todos os seus dados?');">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-danger">Remover</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            {{ $contatos->links() }}
        </div>
    </div>
@endsection
