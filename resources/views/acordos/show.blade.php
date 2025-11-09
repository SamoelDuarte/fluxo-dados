@extends('layouts.app')

@section('title', 'Visualizar Acordo')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-12">
            <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-handshake"></i> Acordo #{{ $acordo->id }}</h1>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row align-items-center">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary">Detalhes do Acordo</h6>
                </div>
                <div class="col text-right">
                    <a href="{{ route('acordos.edit', $acordo->id) }}" class="btn btn-sm btn-warning">
                        <i class="fas fa-edit"></i> Editar
                    </a>
                    <a href="{{ route('acordos.index') }}" class="btn btn-sm btn-secondary">
                        <i class="fas fa-arrow-left"></i> Voltar
                    </a>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="text-muted small">Nome do Cliente</label>
                        <h5>{{ $acordo->nome }}</h5>
                    </div>
                    <div class="mb-3">
                        <label class="text-muted small">Documento</label>
                        <h5>{{ $acordo->documento }}</h5>
                    </div>
                    <div class="mb-3">
                        <label class="text-muted small">Telefone</label>
                        <h5>{{ $acordo->telefone }}</h5>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="text-muted small">Status</label>
                        <h5>
                            <span class="badge badge-{{ $acordo->status === 'ativo' ? 'success' : ($acordo->status === 'cancelado' ? 'danger' : 'warning') }}">
                                {{ ucfirst($acordo->status) }}
                            </span>
                        </h5>
                    </div>
                    <div class="mb-3">
                        <label class="text-muted small">Phone Number ID</label>
                        <h5>{{ $acordo->phone_number_id ?? 'N/A' }}</h5>
                    </div>
                    <div class="mb-3">
                        <label class="text-muted small">Data de Criação</label>
                        <h5>{{ $acordo->created_at->format('d/m/Y H:i') }}</h5>
                    </div>
                </div>
            </div>

            @if($acordo->texto)
            <div class="row mt-4">
                <div class="col-md-12">
                    <div class="mb-3">
                        <label class="text-muted small">Detalhes/Observações</label>
                        <div class="alert alert-light border">
                            <pre>{{ $acordo->texto }}</pre>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <div class="row mt-4">
                <div class="col-md-12">
                    <hr>
                    <form action="{{ route('acordos.destroy', $acordo->id) }}" method="POST" style="display: inline;">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger" onclick="return confirm('Tem certeza que deseja deletar este acordo?')">
                            <i class="fas fa-trash"></i> Deletar Acordo
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
