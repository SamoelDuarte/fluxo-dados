@extends('layouts.app')

@section('title', 'Novo Acordo')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-12">
            <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-handshake"></i> Novo Acordo</h1>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Formulário de Acordo</h6>
        </div>
        <div class="card-body">
            <form action="{{ route('acordos.store') }}" method="POST">
                @csrf

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="nome">Nome do Cliente</label>
                            <input type="text" class="form-control @error('nome') is-invalid @enderror" id="nome" name="nome" value="{{ old('nome') }}" required>
                            @error('nome')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="documento">Documento (CPF/CNPJ)</label>
                            <input type="text" class="form-control @error('documento') is-invalid @enderror" id="documento" name="documento" value="{{ old('documento') }}" required>
                            @error('documento')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="telefone">Telefone</label>
                            <input type="text" class="form-control @error('telefone') is-invalid @enderror" id="telefone" name="telefone" value="{{ old('telefone') }}" required>
                            @error('telefone')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="phone_number_id">Phone Number ID (WhatsApp)</label>
                            <input type="text" class="form-control @error('phone_number_id') is-invalid @enderror" id="phone_number_id" name="phone_number_id" value="{{ old('phone_number_id') }}">
                            @error('phone_number_id')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select class="form-control @error('status') is-invalid @enderror" id="status" name="status" required>
                                <option value="">-- Selecione --</option>
                                <option value="pendente" {{ old('status') === 'pendente' ? 'selected' : '' }}>Pendente</option>
                                <option value="ativo" {{ old('status') === 'ativo' ? 'selected' : '' }}>Ativo</option>
                                <option value="finalizado" {{ old('status') === 'finalizado' ? 'selected' : '' }}>Finalizado</option>
                                <option value="cancelado" {{ old('status') === 'cancelado' ? 'selected' : '' }}>Cancelado</option>
                            </select>
                            @error('status')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="texto">Detalhes/Observações</label>
                    <textarea class="form-control @error('texto') is-invalid @enderror" id="texto" name="texto" rows="5" placeholder="Adicione observações sobre o acordo...">{{ old('texto') }}</textarea>
                    @error('texto')<span class="invalid-feedback">{{ $message }}</span>@enderror
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Salvar Acordo
                    </button>
                    <a href="{{ route('acordos.index') }}" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
$(function() {
    // Máscaras de telefone
    $('#telefone').mask('(00) 00000-0000');
    
    // Máscaras de documento (CPF/CNPJ)
    $('#documento').mask('000.000.000-00', {
        translation: {
            '0': {pattern: /\d/}
        },
        onKeyPress: function(cep, e, field, options) {
            let masks = ['000.000.000-00', '00.000.000/0000-00'];
            let mask = (cep.replace(/\D/g, '').length <= 11) ? masks[0] : masks[1];
            $(field).mask(mask, options);
        }
    });
});
</script>
@endsection
