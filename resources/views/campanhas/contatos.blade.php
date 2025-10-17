@extends('layouts.app')

@section('title', 'Contatos')

@section('content')
    <div class="card">
        <div class="card-header">Contatos</div>
        <div class="card-body">
            <p class="mb-2">Total de contatos importados: <strong>{{ $totalContatos ?? 0 }}</strong></p>
            <p>Gerencie seus contatos aqui. <a href="{{ route('contatos.index') }}">Ir para Contatos</a></p>
        </div>
    </div>
@endsection
