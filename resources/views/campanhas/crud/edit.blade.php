@extends('layouts.app')

@section('title', 'Editar Campanha')

@section('content')
    <div class="card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <strong>Editar Campanha</strong>
                    <div class="text-muted small">Clique nos contatos e telefones para selecionar/desselecionar. Use os botões para selecionar todos ou limpar.</div>
                </div>
                <div class="text-right">
                    <div class="small">Contatos selecionados: <span id="selectedContatosCount">0</span>/<span id="totalContatosCount">0</span></div>
                </div>
            </div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('campanhas.crud.update', $campanha) }}" id="campanhaForm">
                @csrf
                @method('PUT')

                <div class="row">
                    <div class="col-md-12">

                        <div class="form-group">
                    <label>Nome</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name', $campanha->name) }}" required>
                </div>

                <div class="form-group">
                    <label>Contatos <small class="text-muted">(clique para selecionar)</small></label>
                    <div class="mb-2">
                        <button type="button" id="clearContatos" class="btn btn-sm btn-outline-secondary">Limpar</button>
                    </div>
                    <div id="contatosList" class="mb-3">
                        @foreach($contatos as $contato)
                            <div class="d-inline-block m-0 p-0">
                                <button type="button" class="btn btn-light btn-sm contato-item m-1 {{ $campanha->contatos->contains($contato->id) ? '' : '' }}" data-id="{{ $contato->id }}" title="{{ $contato->name }}" aria-pressed="{{ $campanha->contatos->contains($contato->id) ? 'true' : 'false' }}">{{ $contato->name }}</button>
                                <input type="checkbox" name="contatos[]" value="{{ $contato->id }}" id="contato_checkbox_{{ $contato->id }}" class="contato-checkbox d-none" {{ $campanha->contatos->contains($contato->id) ? 'checked' : '' }}>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="form-group">
                    <label>Telefones <small class="text-muted">(clique para adicionar à seleção)</small></label>
                    <div id="telefonesList" class="mb-3">
                        @foreach($telefones as $telefone)
                            <div class="d-inline-block m-0 p-0">
                                <button type="button" class="btn btn-light btn-sm telefone-item m-1 {{ $campanha->telefones->contains($telefone->id) ? '' : '' }}" data-id="{{ $telefone->id }}" title="{{ $telefone->phone_number }}" aria-pressed="{{ $campanha->telefones->contains($telefone->id) ? 'true' : 'false' }}">{{ $telefone->phone_number }}</button>
                                <input type="checkbox" name="telefones[]" value="{{ $telefone->id }}" id="telefone_checkbox_{{ $telefone->id }}" class="telefone-checkbox d-none" {{ $campanha->telefones->contains($telefone->id) ? 'checked' : '' }}>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Cada item tem checkbox oculto para submissão natural do form --}}

                <div class="mt-3">
                    <button class="btn btn-primary">Salvar</button>
                    <a href="{{ route('campanhas.crud.index') }}" class="btn btn-secondary">Voltar</a>
                </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <style>
        /* visual focus for keyboard users */
        .contato-item:focus, .telefone-item:focus {
            outline: 3px solid rgba(13,110,253,0.25);
            outline-offset: 2px;
        }
        .contato-item.contato-selected {
            background-color: #0d6efd;
            color: #fff;
            border-color: #0d6efd;
        }
        .telefone-item.telefone-selected {
            background-color: #198754;
            color: #fff;
            border-color: #198754;
        }
        .contato-item.contato-selected, .telefone-item.telefone-selected {
            box-shadow: 0 0 0 0.2rem rgba(13,110,253,0.12);
        }
    </style>

    <script>
        (function(){
            // Contatos e Telefones
            const contatoButtons = document.querySelectorAll('.contato-item');
            const telefoneButtons = document.querySelectorAll('.telefone-item');
            const selectedContatosCount = document.getElementById('selectedContatosCount');
            const totalContatosCount = document.getElementById('totalContatosCount');

            totalContatosCount.textContent = contatoButtons.length;

            function countCheckedContatos(){
                return document.querySelectorAll('.contato-checkbox:checked').length;
            }

            function updateCount(){
                selectedContatosCount.textContent = countCheckedContatos();
            }

            contatoButtons.forEach(btn => {
                const id = btn.getAttribute('data-id');
                const cb = document.getElementById('contato_checkbox_' + id);
                if(cb && cb.checked){ btn.classList.add('contato-selected'); btn.setAttribute('aria-pressed','true'); }
                btn.tabIndex = 0;

                btn.addEventListener('click', () => {
                    if(!cb) return;
                    cb.checked = !cb.checked;
                    btn.classList.toggle('contato-selected', cb.checked);
                    btn.setAttribute('aria-pressed', cb.checked ? 'true' : 'false');
                    updateCount();
                });
                btn.addEventListener('keydown', (e) => { if(e.key === 'Enter' || e.key === ' ') { e.preventDefault(); btn.click(); } });
            });

            telefoneButtons.forEach(btn => {
                const id = btn.getAttribute('data-id');
                const cb = document.getElementById('telefone_checkbox_' + id);
                if(cb && cb.checked){ btn.classList.add('telefone-selected'); btn.setAttribute('aria-pressed','true'); }
                btn.tabIndex = 0;

                btn.addEventListener('click', () => {
                    if(!cb) return;
                    cb.checked = !cb.checked;
                    btn.classList.toggle('telefone-selected', cb.checked);
                    btn.setAttribute('aria-pressed', cb.checked ? 'true' : 'false');
                    updateCount();
                });
                btn.addEventListener('keydown', (e) => { if(e.key === 'Enter' || e.key === ' ') { e.preventDefault(); btn.click(); } });
            });

            document.getElementById('clearContatos').addEventListener('click', () => {
                document.querySelectorAll('.contato-checkbox').forEach(cb => cb.checked = false);
                contatoButtons.forEach(btn => { btn.classList.remove('contato-selected'); btn.setAttribute('aria-pressed','false'); });
                updateCount();
            });

            updateCount();
        })();
    </script>
    @endpush
@endsection
