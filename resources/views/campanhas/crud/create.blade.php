@extends('layouts.app')

@section('title', 'Nova Campanha')

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <strong>Nova Campanha</strong>
                <div class="text-muted small">Configure e inicie uma nova campanha de contatos</div>
            </div>
        </div>

        <div class="card-body">
            <form method="POST" action="{{ route('campanhas.crud.store') }}" id="campanhaForm" enctype="multipart/form-data">
                @csrf

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">WABA (WhatsApp Business Account)</label>
                            <select name="waba_id" class="form-control" required id="wabaSelect">
                                <option value="">-- Selecione uma WABA --</option>
                                @forelse ($wabas as $waba)
                                    <option value="{{ $waba['id'] }}" data-name="{{ $waba['name'] ?? $waba['id'] }}">
                                        {{ $waba['name'] ?? 'Sem nome' }}
                                        {{ $waba['id'] }}</option>
                                @empty
                                    <option value="" disabled>Nenhuma WABA disponível. Configure o business_id
                                        primeiro.</option>
                                @endforelse
                            </select>
                            <input type="hidden" name="name" id="wabaName" value="">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Template de Mensagem</label>
                            <select name="template_id" class="form-control" id="templateSelect" required>
                                <option value="">-- Selecione um template --</option>
                            </select>
                            <input type="hidden" name="template_name" id="templateName" value="">
                        </div>
                         <div class="mb-3">
                            <label class="form-label">Planilha de Contatos <small class="text-muted">(Excel)</small></label>
                            <input type="file" name="planilha" class="form-control" accept=".xlsx,.xls,.csv" id="planilhaInput">
                            <small class="form-text text-muted">Formatos aceitos: .xlsx, .xls, .csv</small>
                        </div>
                         <div class="mb-3">
                            <label class="form-label">Telefones <small class="text-muted">(clique para
                                    selecionar)</small></label>
                            <div id="telefonesLoadingMessage" class="text-muted small mb-2" style="display: none;">
                                Carregando telefones...</div>
                            <div id="telefonesList" class="mb-2">
                                <!-- Telefones carregados dinamicamente via API -->
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div id="templatePreview" class="mt-3" style="display: none;">
                            <div class="card border-primary">
                                <div class="card-header bg-primary text-white">
                                    <strong id="previewName">Template</strong>
                                </div>
                                <div class="card-body">
                                    <div id="previewBody" class="mb-3"></div>
                                    <div id="previewButtons" class="mb-2"></div>
                                    <div id="previewFooter" class="text-muted small text-end"></div>
                                </div>
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
            .telefone-item:focus {
                outline: 3px solid rgba(13, 110, 253, 0.25);
                outline-offset: 2px;
            }

            .telefone-item.telefone-selected {
                background-color: #198754;
                color: #fff;
                border-color: #198754;
            }
        </style>

        <script>
            (function() {
                // Elements
                const wabaSelect = document.getElementById('wabaSelect');
                const wabaNameInput = document.getElementById('wabaName');
                const telefonesList = document.getElementById('telefonesList');
                const telefonesLoadingMessage = document.getElementById('telefonesLoadingMessage');
                const templateSelect = document.getElementById('templateSelect');
                const templatePreview = document.getElementById('templatePreview');
                const previewName = document.getElementById('previewName');
                const previewBody = document.getElementById('previewBody');
                const previewButtons = document.getElementById('previewButtons');
                const previewFooter = document.getElementById('previewFooter');

                let allTemplates = []; // Armazenar todos os templates

                // Buscar templates e telefones quando WABA é selecionada
                if (wabaSelect) {
                    wabaSelect.addEventListener('change', function() {
                        const selectedOption = this.options[this.selectedIndex];
                        const wabaId = selectedOption.value;
                        const name = selectedOption.getAttribute('data-name');

                        wabaNameInput.value = name || '';

                        // Limpar templates e telefones
                        templateSelect.innerHTML = '<option value="">-- Selecione um template --</option>';
                        telefonesList.innerHTML = '';
                        templatePreview.style.display = 'none';
                        allTemplates = [];

                        if (!wabaId) return;

                        // Buscar templates
                        fetch('{{ route('campanhas.crud.templates') }}?waba_id=' + encodeURIComponent(wabaId))
                            .then(response => response.json())
                            .then(data => {


                                if (data.error) {
                                    console.error(data.error);
                                    return;
                                }

                                const templates = data.templates || [];
                                allTemplates = templates;

                                if (templates.length === 0) {
                                    const option = document.createElement('option');
                                    option.value = '';
                                    option.disabled = true;
                                    option.textContent = 'Nenhum template disponível';
                                    templateSelect.appendChild(option);
                                    return;
                                }

                                templates.forEach(template => {
                                    const option = document.createElement('option');
                                    option.value = template.id;
                                    option.textContent = template.name;
                                    option.setAttribute('data-template', JSON.stringify(template));
                                    templateSelect.appendChild(option);
                                });
                            })
                            .catch(error => {

                                console.error('Erro:', error);
                            });

                        // Buscar telefones
                        telefonesLoadingMessage.style.display = 'block';
                        fetch('{{ route('campanhas.crud.phone-numbers') }}?waba_id=' + encodeURIComponent(wabaId))
                            .then(response => response.json())
                            .then(data => {
                                telefonesLoadingMessage.style.display = 'none';

                                if (data.error) {
                                    console.error(data.error);
                                    const div = document.createElement('div');
                                    div.className = 'alert alert-danger';
                                    div.textContent = 'Erro ao buscar telefones';
                                    telefonesList.appendChild(div);
                                    return;
                                }

                                const phoneNumbers = data.phone_numbers || [];

                                if (phoneNumbers.length === 0) {
                                    const div = document.createElement('div');
                                    div.className = 'alert alert-info';
                                    div.textContent = 'Nenhum número de telefone encontrado nesta WABA';
                                    telefonesList.appendChild(div);
                                    return;
                                }

                                phoneNumbers.forEach((phone, index) => {
                                    const containerDiv = document.createElement('div');
                                    containerDiv.className = 'd-inline-block m-0 p-0';

                                    const button = document.createElement('button');
                                    button.type = 'button';
                                    button.className = 'btn btn-light btn-sm telefone-item m-1';
                                    button.setAttribute('data-id', phone.id);
                                    button.setAttribute('aria-pressed', 'false');
                                    button.textContent = phone.display_phone_number || phone.id;

                                    const checkbox = document.createElement('input');
                                    checkbox.type = 'checkbox';
                                    checkbox.name = 'phone_numbers[]';
                                    checkbox.value = phone.id;
                                    checkbox.id = 'phone_checkbox_' + phone.id;
                                    checkbox.className = 'phone-checkbox d-none';

                                    containerDiv.appendChild(button);
                                    containerDiv.appendChild(checkbox);
                                    telefonesList.appendChild(containerDiv);

                                    // Add event listener to button
                                    button.addEventListener('click', (e) => {
                                        e.preventDefault();
                                        checkbox.checked = !checkbox.checked;
                                        button.classList.toggle('telefone-selected', checkbox
                                            .checked);
                                        button.setAttribute('aria-pressed', checkbox.checked ?
                                            'true' : 'false');
                                    });

                                    button.addEventListener('keydown', (e) => {
                                        if (e.key === 'Enter' || e.key === ' ') {
                                            e.preventDefault();
                                            button.click();
                                        }
                                    });
                                });
                            })
                            .catch(error => {
                                telefonesLoadingMessage.style.display = 'none';
                                console.error('Erro:', error);
                                const div = document.createElement('div');
                                div.className = 'alert alert-danger';
                                div.textContent = 'Erro ao buscar telefones';
                                telefonesList.appendChild(div);
                            });
                    });

                    // Mostrar preview quando template é selecionado
                    templateSelect.addEventListener('change', function() {
                        const selectedOption = this.options[this.selectedIndex];
                        const templateData = selectedOption.getAttribute('data-template');
                        const templateNameInput = document.getElementById('templateName');

                        if (!templateData) {
                            templatePreview.style.display = 'none';
                            templateNameInput.value = '';
                            return;
                        }

                        const template = JSON.parse(templateData);
                        templateNameInput.value = template.name; // Preencher o campo hidden com o nome
                        
                        previewName.textContent = template.name;
                        previewBody.innerHTML = '';
                        previewButtons.innerHTML = '';
                        previewFooter.innerHTML = '';

                        // Extrair componentes
                        const components = template.components || [];

                        components.forEach(component => {
                            if (component.type === 'BODY') {
                                const bodyText = component.text.replace(/\{\{1\}\}|\{\{2\}\}|\{\{3\}\}/g,
                                    '...');
                                previewBody.innerHTML = '<p>' + bodyText.replace(/\n/g, '<br>') + '</p>';
                            } else if (component.type === 'BUTTONS') {
                                const buttonContainer = document.createElement('div');
                                buttonContainer.className = 'd-flex flex-column gap-2 mt-2';
                                component.buttons.forEach(btn => {
                                    const btnEl = document.createElement('button');
                                    btnEl.type = 'button';
                                    btnEl.className = 'btn btn-sm btn-outline-primary';
                                    btnEl.disabled = true;
                                    btnEl.textContent = btn.text;
                                    buttonContainer.appendChild(btnEl);
                                });
                                previewButtons.appendChild(buttonContainer);
                            } else if (component.type === 'FOOTER') {
                                previewFooter.textContent = component.text;
                            }
                        });

                        templatePreview.style.display = 'block';
                    });
                }
            })();
        </script>
    @endpush
@endsection
