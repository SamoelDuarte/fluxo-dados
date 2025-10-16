@extends('layouts.app')

@section('title', 'Conectar com whatsapp')

@section('content')
<div class="container mt-5">
    <div class="card">
        <div class="card-header bg-success text-white">
            <i class="fab fa-whatsapp"></i> Conectar com whatsapp
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('whatsapp.save') }}">
                @csrf
                <div class="form-group">
                    <label for="app_id">App ID</label>
                    <input type="text" class="form-control" id="app_id" name="app_id" value="{{ old('app_id', $data->app_id ?? '') }}">
                </div>
                <div class="form-group">
                    <label for="app_secret">App Secret</label>
                    <input type="text" class="form-control" id="app_secret" name="app_secret" value="{{ old('app_secret', $data->app_secret ?? '') }}">
                </div>
                <div class="form-group">
                    <label for="redirect_uri">Redirect URI</label>
                    <input type="text" class="form-control" id="redirect_uri" name="redirect_uri" value="{{ old('redirect_uri', $data->redirect_uri ?? '') }}">
                </div>

                @if(!empty($data->access_token))
                <hr>
                <div class="alert alert-success">
                    <strong>Conectado!</strong>
                </div>
                <div class="form-group">
                    <label for="access_token">Access Token</label>
                    <textarea class="form-control" id="access_token" name="access_token" readonly>{{ $data->access_token }}</textarea>
                </div>
                {{-- <div class="form-group">
                    <label for="refresh_token">Refresh Token</label>
                    <textarea class="form-control" id="refresh_token" name="refresh_token" readonly>{{ $data->refresh_token }}</textarea>
                </div>
                <div class="form-group">
                    <label for="token_expires_at">Token Expires At</label>
                    <input type="text" class="form-control" id="token_expires_at" name="token_expires_at" value="{{ isset($data->token_expires_at) ? $data->token_expires_at : '' }}" readonly>
                </div> --}}
                @endif
                <button type="submit" class="btn btn-success">Salvar</button>
            </form>

            @if(!empty($data->app_id) && !empty($data->app_secret) && !empty($data->redirect_uri) && empty($data->access_token))
                <form method="GET" action="{{ route('whatsapp.authFacebook') }}" class="mt-3">
                    <button type="submit" class="btn btn-primary">Conectar com Facebook</button>
                </form>
            @endif
            </form>
        </div>
    </div>
</div>
@endsection
