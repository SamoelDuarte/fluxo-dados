@extends('layouts.app')

@section('title', 'Editar Telefone')

@section('content')
    <div class="card">
        <div class="card-header">Editar Telefone</div>
        <div class="card-body">
            <form method="POST" action="{{ route('telefones.update', $telefone) }}">
                @csrf
                @method('PUT')

                <div class="form-group">
                    <label>Phone Number ID</label>
                    <input type="text" name="phone_number_id" class="form-control" value="{{ old('phone_number_id', $telefone->phone_number_id) }}">
                    @error('phone_number_id')<small class="text-danger">{{ $message }}</small>@enderror
                </div>

                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" name="phone_number" class="form-control" value="{{ old('phone_number', $telefone->phone_number) }}" required>
                    @error('phone_number')<small class="text-danger">{{ $message }}</small>@enderror
                </div>

                <button class="btn btn-primary mt-2">Salvar</button>
                <a href="{{ route('telefones.index') }}" class="btn btn-secondary mt-2">Cancelar</a>
            </form>
        </div>
    </div>
@endsection
