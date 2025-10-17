@extends('layouts.app')

@section('title', 'Telefones')

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Telefones</h5>
            <a href="{{ route('telefones.create') }}" class="btn btn-primary">Novo Telefone</a>
        </div>
        <div class="card-body">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <table class="table table-striped">
                <thead>
                <tr>
                    <th>#</th>
                    <th>Phone Number ID</th>
                    <th>Phone Number</th>
                    <th>Ações</th>
                </tr>
                </thead>
                <tbody>
                @foreach($telefones as $telefone)
                    <tr>
                        <td>{{ $telefone->id }}</td>
                        <td>{{ $telefone->phone_number_id }}</td>
                        <td>{{ $telefone->phone_number }}</td>
                        <td>
                            <a href="{{ route('telefones.edit', $telefone) }}" class="btn btn-sm btn-secondary">Editar</a>
                            <form action="{{ route('telefones.destroy', $telefone) }}" method="POST" style="display:inline-block;">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-danger" onclick="return confirm('Remover este telefone?')">Remover</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            {{ $telefones->links() }}
        </div>
    </div>
@endsection
