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

                    <div class="col-md-8">
                        <div class="mb-3">
                            <label class="form-label">Nome</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Mensagem</label>
                            <textarea name="mensagem" class="form-control" rows="3"
                                placeholder="Digite a mensagem da campanha (aceita emojis)"></textarea>
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
                    <div class="col-md-4">
                        {{-- Seção de Imagens --}}
                        <div class="mb-3">
                            <label class="form-label">Imagem da Campanha</label>

                            {{-- Botão para inserir imagem --}}
                            <div class="mb-2">
                                <button type="button" id="btnInserirImagem" class="btn btn-sm btn-primary">
                                    <i class="fas fa-plus"></i> Inserir Imagem
                                </button>
                            </div>

                            {{-- Input file oculto --}}
                            <input type="file" id="imagemInput" accept="image/*" style="display: none;">

                            {{-- Exibição de imagens selecionadas --}}
                            <div id="imagensContainer" class="d-flex flex-wrap gap-2 p-3 bg-light rounded"
                                style="min-height: 120px;">
                                <p class="text-muted w-100" id="semImagemMsg">Nenhuma imagem selecionada</p>
                            </div>

                            {{-- Input oculto para enviar a imagem selecionada --}}
                            <input type="hidden" name="img_campanha" id="img_campanha_input" value="">
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

            .imagem-item {
                position: relative;
                display: inline-block;
                border-radius: 6px;
                overflow: hidden;
                cursor: pointer;
                transition: all 0.3s ease;
                border: 2px solid transparent;
            }

            .imagem-item:hover {
                border-color: #0d6efd;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            }

            .imagem-item.selecionada {
                border-color: #0d6efd;
                box-shadow: 0 2px 8px rgba(13, 110, 253, 0.4);
            }

            .imagem-item img {
                width: 100px;
                height: 100px;
                object-fit: cover;
                display: block;
            }

            .imagem-remove-btn {
                position: absolute;
                top: 5px;
                right: 5px;
                background: rgba(255, 255, 255, 0.95);
                border: none;
                border-radius: 50%;
                width: 24px;
                height: 24px;
                padding: 0;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                color: #dc3545;
                font-size: 14px;
                opacity: 0;
                transition: opacity 0.2s;
            }

            .imagem-item:hover .imagem-remove-btn {
                opacity: 1;
            }

            .imagem-remove-btn:hover {
                background: #dc3545;
                color: white;
            }
        </style>

        <script>
            (function() {
                const btnInserirImagem = document.getElementById('btnInserirImagem');
                const imagemInput = document.getElementById('imagemInput');
                const imagensContainer = document.getElementById('imagensContainer');
                const imgCampanhaInput = document.getElementById('img_campanha_input');
                const semImagemMsg = document.getElementById('semImagemMsg');

                let imagensSelecionadas = new Map(); // id -> {imagem, selected}

                // Carregar imagens existentes
                function carregarImagens() {
                    fetch('{{ route('imagens.list') }}')
                        .then(r => r.json())
                        .then(data => {
                            imagensSelecionadas.clear();
                            data.imagens.forEach(img => {
                                imagensSelecionadas.set(img.id, {
                                    id: img.id,
                                    url: img.url,
                                    caminho: img.caminho_imagem,
                                    selected: false
                                });
                            });
                            renderizarImagens();
                        })
                        .catch(err => console.error('Erro ao carregar imagens:', err));
                }

                // Botão de inserir imagem
                btnInserirImagem.addEventListener('click', () => {
                    imagemInput.click();
                });

                // Ao selecionar arquivo
                imagemInput.addEventListener('change', (e) => {
                    const file = e.target.files[0];
                    if (!file) return;

                    const formData = new FormData();
                    formData.append('imagem', file);
                    formData.append('_token', document.querySelector('input[name="_token"]').value);

                    fetch('{{ route('imagens.store') }}', {
                            method: 'POST',
                            body: formData,
                        })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                imagensSelecionadas.set(data.imagem.id, {
                                    id: data.imagem.id,
                                    url: data.url,
                                    caminho: data.imagem.caminho_imagem,
                                    selected: false
                                });
                                renderizarImagens();
                            } else {
                                alert('Erro ao salvar imagem: ' + (data.message || 'Desconhecido'));
                            }
                        })
                        .catch(err => {
                            console.error('Erro:', err);
                            alert('Erro ao enviar imagem');
                        });

                    imagemInput.value = '';
                });

                // Renderizar imagens
                function renderizarImagens() {
                    imagensContainer.innerHTML = '';

                    if (imagensSelecionadas.size === 0) {
                        imagensContainer.appendChild(semImagemMsg);
                        return;
                    }

                    imagensSelecionadas.forEach((img) => {
                        const div = document.createElement('div');
                        div.className = 'imagem-item' + (img.selected ? ' selecionada' : '');
                        div.innerHTML = `
                        <img src="${img.url}" alt="Imagem">
                        <button type="button" class="imagem-remove-btn" data-id="${img.id}">
                            <i class="fas fa-times"></i>
                        </button>
                    `;

                        div.addEventListener('click', (e) => {
                            if (!e.target.closest('.imagem-remove-btn')) {
                                selecionarImagem(img.id);
                            }
                        });

                        div.querySelector('.imagem-remove-btn').addEventListener('click', (e) => {
                            e.preventDefault();
                            removerImagem(img.id);
                        });

                        imagensContainer.appendChild(div);
                    });
                }

                // Selecionar imagem (toggle)
                function selecionarImagem(id) {
                    const img = imagensSelecionadas.get(id);
                    if (!img) return;

                    // Se já está selecionada, desseleciona
                    if (img.selected) {
                        img.selected = false;
                        imgCampanhaInput.value = '';
                    } else {
                        // Se não está selecionada, desseleciona todas as outras e seleciona esta
                        imagensSelecionadas.forEach(i => {
                            i.selected = false;
                        });
                        img.selected = true;
                        imgCampanhaInput.value = id;
                    }

                    renderizarImagens();
                }

                // Remover imagem
                function removerImagem(id) {
                    if (!confirm('Tem certeza que deseja remover esta imagem?')) return;

                    fetch(`/imagens-campanha/${id}`, {
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
                            }
                        })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                imagensSelecionadas.delete(id);
                                if (imgCampanhaInput.value == id) {
                                    imgCampanhaInput.value = '';
                                }
                                renderizarImagens();
                            } else {
                                alert('Erro ao remover imagem');
                            }
                        })
                        .catch(err => {
                            console.error('Erro:', err);
                            alert('Erro ao remover imagem');
                        });
                }

                // Contatos e Telefones (código existente)
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
                        console.log('contato clicked', id);
                        if (!cb) {
                            console.log('no cb for', id);
                            return;
                        }
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
                        console.log('telefone clicked', id);
                        if (!cb) {
                            console.log('no cb for', id);
                            return;
                        }
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
                carregarImagens();
            })();
        </script>
    @endpush
@endsection
