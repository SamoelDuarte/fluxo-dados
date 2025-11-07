@extends('layouts.app')

@section('title', 'Nova Campanha')

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <strong>Nova Campanha</strong>
                <div class="text-muted small">Escolha os contatos e telefones para enviar. Clique nos itens abaixo para
                    selecioná-los/desselecioná-los.</div>
            </div>
            <div class="text-right small">Contatos selecionados: <span id="selectedContatosCount">0</span>/<span
                    id="totalContatosCount">0</span></div>
        </div>

        <div class="card-body">
            <form method="POST" action="{{ route('campanhas.crud.store') }}" id="campanhaForm">
                @csrf

                <div class="row">

                    <div class="col-md-12">
                        <div class="mb-3">
                            <label class="form-label">Nome</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Contatos <small class="text-muted">(clique para
                                    selecionar)</small></label>
                            <div class="mb-2">
                                <button type="button" id="clearContatos"
                                    class="btn btn-sm btn-outline-secondary">Limpar</button>
                            </div>
                            <div id="contatosList" class="mb-2">
                                @foreach ($contatos as $contato)
                                    <div class="d-inline-block m-0 p-0">
                                        <button type="button" class="btn btn-light btn-sm contato-item m-1"
                                            data-id="{{ $contato->id }}" aria-pressed="false">{{ $contato->name }}</button>
                                        <input type="checkbox" name="contatos[]" value="{{ $contato->id }}"
                                            id="contato_checkbox_{{ $contato->id }}" class="contato-checkbox d-none">
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Telefones <small class="text-muted">(clique para
                                    selecionar)</small></label>
                            <div id="telefonesList" class="mb-2">
                                @foreach ($telefones as $telefone)
                                    <div class="d-inline-block m-0 p-0">
                                        <button type="button" class="btn btn-light btn-sm telefone-item m-1"
                                            data-id="{{ $telefone->id }}"
                                            aria-pressed="false">{{ $telefone->phone_number }}</button>
                                        <input type="checkbox" name="telefones[]" value="{{ $telefone->id }}"
                                            id="telefone_checkbox_{{ $telefone->id }}" class="telefone-checkbox d-none">
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>



                <div class="mt-3">
                    <button class="btn btn-primary">Salvar</button>
                    <a href="{{ route('campanhas.crud.index') }}" class="btn btn-secondary">Voltar</a>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
        <style>
            .contato-item:focus,
            .telefone-item:focus {
                outline: 3px solid rgba(13, 110, 253, 0.25);
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
        </style>

        <script>
            (function() {
                // Contatos e Telefones
                const contatoButtons = document.querySelectorAll('.contato-item');
                const telefoneButtons = document.querySelectorAll('.telefone-item');
                const selectedContatosCount = document.getElementById('selectedContatosCount');
                const totalContatosCount = document.getElementById('totalContatosCount');

                totalContatosCount.textContent = contatoButtons.length;

                function updateCount() {
                    selectedContatosCount.textContent = document.querySelectorAll('.contato-checkbox:checked').length;
                }

                contatoButtons.forEach(btn => {
                    const id = btn.getAttribute('data-id');
                    const cb = document.getElementById('contato_checkbox_' + id);
                    btn.tabIndex = 0;
                    btn.addEventListener('click', () => {
                        if (!cb) return;
                        cb.checked = !cb.checked;
                        btn.classList.toggle('contato-selected', cb.checked);
                        btn.setAttribute('aria-pressed', cb.checked ? 'true' : 'false');
                        updateCount();
                    });
                    btn.addEventListener('keydown', (e) => {
                        if (e.key === 'Enter' || e.key === ' ') {
                            e.preventDefault();
                            btn.click();
                        }
                    });
                });

                telefoneButtons.forEach(btn => {
                    const id = btn.getAttribute('data-id');
                    const cb = document.getElementById('telefone_checkbox_' + id);
                    btn.tabIndex = 0;
                    btn.addEventListener('click', () => {
                        if (!cb) return;
                        cb.checked = !cb.checked;
                        btn.classList.toggle('telefone-selected', cb.checked);
                        btn.setAttribute('aria-pressed', cb.checked ? 'true' : 'false');
                    });
                    btn.addEventListener('keydown', (e) => {
                        if (e.key === 'Enter' || e.key === ' ') {
                            e.preventDefault();
                            btn.click();
                        }
                    });
                });

                document.getElementById('clearContatos').addEventListener('click', () => {
                    document.querySelectorAll('.contato-checkbox').forEach(cb => cb.checked = false);
                    contatoButtons.forEach(btn => {
                        btn.classList.remove('contato-selected');
                        btn.setAttribute('aria-pressed', 'false');
                    });
                    updateCount();
                });

                updateCount();
            })();
        </script>
    @endpush
@endsection
