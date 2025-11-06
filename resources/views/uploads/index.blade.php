@extends('layouts.app')

@section('css')
    <!-- Incluindo o Bootstrap e o Font Awesome para ícones -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Estilos personalizados para a barra de progresso */
        #progress-bar {
            height: 20px;
            background-color: #4caf50;
            width: 0%;
            text-align: center;
            line-height: 20px;
            color: white;
        }

        #progress-container {
            width: 100%;
            background-color: #f3f3f3;
            border: 1px solid #ccc;
        }
    </style>
@endsection

@section('content')
    {{-- <div class="container mt-5">
        <h1>Envio de Arquivos com Barra de Progresso</h1>

        <!-- Mensagem de Sucesso -->
        @if (session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        <!-- Formulário de Upload -->
        <form id="upload-form" enctype="multipart/form-data">
            @csrf
            <div class="mb-3">
                <label for="file" class="form-label">Selecione um arquivo (.xlsx):</label>
                <input type="file" name="file" id="file" class="form-control" required>
            </div>

            <!-- Barra de Progresso -->
            <div id="progress-container" class="mb-3" style="display:none;">
                <div id="progress-bar"></div>
            </div>

            <button type="submit" class="btn btn-primary">Enviar</button>
        </form>

        <!-- Tabela para Exibir Dados -->
        <h2 class="mt-5">Uploads Realizados</h2>
        <table class="table table-bordered mt-3">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Data</th>
                    <th>Quantidade</th>
                    <th>QTDE Sucesso</th> <!-- Nova coluna -->
                    <th>QTDE Erros</th> <!-- Nova coluna -->
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody id="upload-table-body">
                <!-- Os dados serão inseridos via JavaScript -->
            </tbody>
        </table>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="carteirasModal" tabindex="-1" aria-labelledby="carteirasModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="carteirasModalLabel">Carteiras do Lote</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Carteira</th>
                                <th>Contrato</th>
                                <th>Documento</th>
                                <th>Nome</th>
                                <th>Erro</th>
                            </tr>
                        </thead>
                        <tbody id="modal-table-body">
                            <!-- As carteiras serão inseridas dinamicamente aqui -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div> --}}
@endsection

@section('scripts')
    <!-- Incluindo os Scripts do Bootstrap e jQuery -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        $(document).ready(function() {
            $('#upload-form').submit(function(event) {
                event.preventDefault();
                $('#progress-container').show(); // Mostra a barra de progresso
                $('#progress-bar')
                    .css('width', '0%') // Inicializa a barra de progresso em 0%
                    .text('Por favor, aguarde...'); // Exibe a mensagem enquanto o upload está em andamento

                var formData = new FormData(this);

                var xhr = new XMLHttpRequest();

                // Configura o evento de progresso para a requisição AJAX
                xhr.upload.addEventListener('progress', function(e) {
                    if (e.lengthComputable) {
                        var progress = (e.loaded / e.total) * 100; // Calcula o progresso
                        $('#progress-bar')
                            .css('width', progress + '%') // Atualiza a largura da barra
                            .text('Por favor, aguarde...'); // Exibe o progresso
                    }
                });

                xhr.open('POST', '{{ route('upload.upload') }}', true);

                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4 && xhr.status === 200) {
                        location.reload();
                    } else if (xhr.readyState === 4) {
                        alert('Erro ao enviar o arquivo.');
                    }
                };

                // Envia o formulário via AJAX com o XMLHttpRequest
                xhr.send(formData);
            });




            // Função para atualizar a tabela de uploads
            function updateTable(uploads) {
                var tableBody = $('#upload-table-body');
                tableBody.empty(); // Limpar a tabela antes de adicionar novas linhas

                uploads.forEach(function(upload) {
                    var row = `
                        <tr>
                            <td>${upload.lote}</td>
                            <td>${upload.data}</td>
                            <td>${upload.quantidade}</td>
                            <td>
                                <button class="btn btn-info" onclick="viewDetails(${upload.id})">
                                    <i class="fas fa-eye"></i> Ver
                                </button>
                            </td>
                        </tr>
                    `;
                    tableBody.append(row);
                });
            }

            window.viewDetails = function(loteId, showErrors = false) {
                $.ajax({
                    url: `/lotes/${loteId}/carteiras`, // Rota para buscar as carteiras
                    type: 'GET',
                    success: function(response) {
                        populateModal(response, showErrors); // Passar a flag para o filtro
                        $('#carteirasModal').modal('show'); // Exibir o modal
                    },
                    error: function() {
                        alert('Erro ao buscar as carteiras do lote.');
                    }
                });
            };


            function populateModal(carteiras, showErrors = false) {
                var modalBody = $('#modal-table-body');
                modalBody.empty(); // Limpar a tabela antes de inserir novas linhas

                // Filtrar carteiras caso showErrors seja verdadeiro
                var filteredCarteiras = showErrors ?
                    carteiras.filter(carteira => carteira.mensagem_erro) :
                    carteiras;

                if (filteredCarteiras.length === 0) {
                    modalBody.append('<tr><td colspan="5">Nenhum erro encontrado.</td></tr>');
                } else {
                    filteredCarteiras.forEach(function(carteira) {
                        var row = `
                <tr>
                    <td>${carteira.id}</td>
                    <td>${carteira.carteira_id}</td>
                    <td>${carteira.contrato}</td>
                    <td>${carteira.documento}</td>
                    <td>${carteira.nome}</td>
                    <td>${carteira.mensagem_erro || ''}</td>
                </tr>
            `;
                        modalBody.append(row);
                    });
                }
            }

        });

        // Função para buscar os lotes e atualizar a tabela
        function fetchLotes() {
            $.ajax({
                url: '{{ route('lotes.get') }}', // URL da nova rota
                type: 'GET',
                success: function(response) {
                    updateTable(response); // Atualizar a tabela com os dados recebidos
                },
                error: function() {
                    alert('Erro ao buscar os lotes.');
                }
            });
        }

        // Função para atualizar a tabela de uploads
        function updateTable(lotes) {
            var tableBody = $('#upload-table-body');
            tableBody.empty(); // Limpar a tabela antes de adicionar novas linhas

            lotes.forEach(function(lote) {
                var row = `
                            <tr>
                                <td>${lote.id}</td>
                                <td>${lote.data}</td>
                                <td>${lote.quantidade}</td>
                                <td>${lote.quantidade_sucesso}</td>
                                <td>${lote.quantidade_erro}</td>
                                <td>
                                    <button class="btn btn-info" onclick="viewDetails(${lote.id})">
                                        <i class="fas fa-eye"></i> Ver Todos
                                    </button>
                                    <button class="btn btn-danger" onclick="viewDetails(${lote.id}, true)">
                                        <i class="fas fa-exclamation-circle"></i> Ver Erros
                                    </button>
                                        <a href="/download-lotes/${lote.id}" class="btn btn-success">
                <i class="fas fa-download"></i> Baixar Excel
            </a>
                                </td>
                            </tr>
                        `;
                tableBody.append(row);
            });
        }


        // Inicializa a tabela ao carregar a página
        fetchLotes();



        function populateModal(carteiras) {
            var modalBody = $('#modal-table-body');
            modalBody.empty(); // Limpar a tabela antes de inserir novas linhas

            carteiras.forEach(function(carteira) {
                var row = `
            <tr>
                <td>${carteira.id}</td>
                <td>${carteira.carteira}</td>
                <td>${carteira.contrato}</td>
                <td>${carteira.documento}</td>
                <td>${carteira.mensagem_erro}</td> <!-- Exibe a mensagem de erro -->
            </tr>
        `;
                modalBody.append(row);
            });
        }
    </script>
@endsection
