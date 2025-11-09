@extends('layouts.app')

@section('title', 'Acordos')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-handshake"></i> Acordos</h1>
        </div>
        <div class="col-md-4 text-right">
            <a href="{{ route('acordos.create') }}" class="btn btn-primary">
                <i class="fas fa-plus"></i> Novo Acordo
            </a>
        </div>
    </div>

    @if($acordos->isEmpty())
    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i> Nenhum acordo cadastrado. <a href="{{ route('acordos.create') }}">Criar novo</a>
    </div>
    @else
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Lista de Acordos</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="acordosTable">
                    <thead class="table-primary">
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>Documento</th>
                            <th>Telefone</th>
                            <th>Status</th>
                            <th>Criado em</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($acordos as $acordo)
                        <tr>
                            <td><strong>#{{ $acordo->id }}</strong></td>
                            <td>{{ $acordo->nome }}</td>
                            <td>{{ $acordo->documento }}</td>
                            <td>{{ $acordo->telefone }}</td>
                            <td>
                                <span class="badge badge-{{ $acordo->status === 'ativo' ? 'success' : ($acordo->status === 'cancelado' ? 'danger' : 'warning') }}">
                                    {{ ucfirst($acordo->status) }}
                                </span>
                            </td>
                            <td>{{ $acordo->created_at->format('d/m/Y H:i') }}</td>
                            <td>
                                <a href="{{ route('acordos.show', $acordo->id) }}" class="btn btn-sm btn-info" title="Ver">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="{{ route('acordos.edit', $acordo->id) }}" class="btn btn-sm btn-warning" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form action="{{ route('acordos.destroy', $acordo->id) }}" method="POST" style="display: inline;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger" title="Deletar" onclick="return confirm('Tem certeza?')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-3">
                {{ $acordos->links() }}
            </div>
        </div>
    </div>
    @endif
</div>

@endsection

@section('scripts')
<script>
$(function() {
    $('#acordosTable').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/pt-BR.json',
        },
        paging: false,
        info: false
    });
});
</script>
@endsection
