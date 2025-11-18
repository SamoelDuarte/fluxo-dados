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

    <!-- Card de Filtros -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-filter"></i> Filtros
            </h6>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('acordos.index') }}" class="form-inline">
                <div class="form-group mr-3">
                    <label for="documento" class="mr-2">Documento:</label>
                    <input type="text" class="form-control" id="documento" name="documento" placeholder="CPF/CNPJ" value="{{ request('documento') }}">
                </div>

                <div class="form-group mr-3">
                    <label for="status" class="mr-2">Status:</label>
                    <select class="form-control" id="status" name="status">
                        <option value="">-- Todos --</option>
                        <option value="pendente" {{ request('status') === 'pendente' ? 'selected' : '' }}>Pendente</option>
                        <option value="ativo" {{ request('status') === 'ativo' ? 'selected' : '' }}>Ativo</option>
                        <option value="finalizado" {{ request('status') === 'finalizado' ? 'selected' : '' }}>Finalizado</option>
                        <option value="cancelado" {{ request('status') === 'cancelado' ? 'selected' : '' }}>Cancelado</option>
                    </select>
                </div>

                <div class="form-group mr-3">
                    <label for="data_inicio" class="mr-2">De:</label>
                    <input type="date" class="form-control" id="data_inicio" name="data_inicio" value="{{ request('data_inicio') }}">
                </div>

                <div class="form-group mr-3">
                    <label for="data_fim" class="mr-2">Até:</label>
                    <input type="date" class="form-control" id="data_fim" name="data_fim" value="{{ request('data_fim') }}">
                </div>

                <button type="submit" class="btn btn-primary mr-2">
                    <i class="fas fa-search"></i> Filtrar
                </button>
                <a href="{{ route('acordos.index') }}" class="btn btn-secondary mr-2">
                    <i class="fas fa-redo"></i> Limpar
                </a>
                <a href="{{ route('acordos.export', request()->query()) }}" class="btn btn-success">
                    <i class="fas fa-download"></i> Exportar Excel
                </a>
            </form>
        </div>
    </div>

    @if($acordos->isEmpty())
    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i> Nenhum acordo encontrado. <a href="{{ route('acordos.create') }}">Criar novo</a>
    </div>
    @else
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Lista de Acordos (Total: {{ $acordos->total() }})</h6>
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
                {{ $acordos->appends(request()->query())->links() }}
            </div>
        </div>
    </div>
    @endif
</div>

@endsection

@section('scripts')
{{-- <script>
$(function() {
    $('#acordosTable').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/pt-BR.json',
        },
        paging: false,
        info: false
    });
});
</script> --}}
@endsection
