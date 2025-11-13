@extends('layouts.app')

@section('title', 'Usuários')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">Gerenciar Usuários</h6>
                <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#modalUsuario" onclick="novoUsuario()">
                    <i class="fas fa-plus"></i> Novo Usuário
                </button>
            </div>
            <div class="card-body">
                <table class="table table-striped table-hover" id="tabelaUsuarios">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nome</th>
                            <th>Email</th>
                            <th>Criado em</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Usuário -->
<div class="modal fade" id="modalUsuario" tabindex="-1" role="dialog" aria-labelledby="modalUsuarioLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalUsuarioLabel">Novo Usuário</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="formUsuario">
                @csrf
                <input type="hidden" id="usuarioId" name="id">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="name">Nome</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                        <small class="form-text text-danger" id="error_name"></small>
                    </div>

                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                        <small class="form-text text-danger" id="error_email"></small>
                    </div>

                    <div class="form-group">
                        <label for="password">Senha</label>
                        <input type="password" class="form-control" id="password" name="password">
                        <small class="form-text text-muted">Deixe em branco se não deseja alterar</small>
                        <small class="form-text text-danger" id="error_password"></small>
                    </div>

                    <div class="form-group">
                        <label for="password_confirmation">Confirmar Senha</label>
                        <input type="password" class="form-control" id="password_confirmation" name="password_confirmation">
                        <small class="form-text text-danger" id="error_password_confirmation"></small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Salvar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
let tabela;
const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

$(document).ready(function() {
    // Inicializa DataTable
    carregarUsuarios();

    // Form Submit
    $('#formUsuario').on('submit', function(e) {
        e.preventDefault();
        salvarUsuario();
    });
});

function carregarUsuarios() {
    $.ajax({
        url: '{{ route("usuarios.listar") }}',
        type: 'GET',
        success: function(response) {
            if (tabela) {
                tabela.destroy();
            }

            // Popula a tabela
            let html = '';
            response.data.forEach(function(usuario) {
                const dataCriacao = new Date(usuario.created_at).toLocaleDateString('pt-BR', {
                    year: 'numeric',
                    month: '2-digit',
                    day: '2-digit',
                    hour: '2-digit',
                    minute: '2-digit'
                });

                html += `
                    <tr>
                        <td>${usuario.id}</td>
                        <td>${usuario.name}</td>
                        <td>${usuario.email}</td>
                        <td>${dataCriacao}</td>
                        <td>
                            <button class="btn btn-sm btn-info" onclick="editarUsuario(${usuario.id})" title="Editar">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="deletarUsuario(${usuario.id})" title="Deletar">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });

            $('#tabelaUsuarios tbody').html(html);

            // Inicializa DataTable novamente
            tabela = $('#tabelaUsuarios').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/pt-BR.json'
                },
                paging: true,
                searching: true,
                ordering: true,
                pageLength: 10
            });
        },
        error: function() {
            Swal.fire('Erro', 'Erro ao carregar usuários', 'error');
        }
    });
}

function novoUsuario() {
    $('#usuarioId').val('');
    $('#modalUsuarioLabel').text('Novo Usuário');
    $('#formUsuario')[0].reset();
    limparErros();
    $('#password').attr('required', true);
    $('#password_confirmation').attr('required', true);
}

function editarUsuario(id) {
    $.ajax({
        url: `{{ route("usuarios.editar", ":id") }}`.replace(':id', id),
        type: 'GET',
        success: function(response) {
            if (response.success) {
                $('#usuarioId').val(response.data.id);
                $('#name').val(response.data.name);
                $('#email').val(response.data.email);
                $('#password').val('');
                $('#password_confirmation').val('');
                $('#password').attr('required', false);
                $('#password_confirmation').attr('required', false);
                $('#modalUsuarioLabel').text('Editar Usuário');
                limparErros();
                $('#modalUsuario').modal('show');
            }
        },
        error: function() {
            Swal.fire('Erro', 'Erro ao carregar dados do usuário', 'error');
        }
    });
}

function salvarUsuario() {
    const formData = new FormData($('#formUsuario')[0]);

    $.ajax({
        url: '{{ route("usuarios.salvar") }}',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        headers: {
            'X-CSRF-TOKEN': token
        },
        success: function(response) {
            if (response.success) {
                Swal.fire('Sucesso!', response.message, 'success');
                $('#modalUsuario').modal('hide');
                carregarUsuarios();
            }
        },
        error: function(xhr) {
            if (xhr.status === 422) {
                const errors = xhr.responseJSON.errors;
                limparErros();
                
                for (let field in errors) {
                    $(`#error_${field}`).text(errors[field][0]);
                }
            } else {
                Swal.fire('Erro', 'Erro ao salvar usuário', 'error');
            }
        }
    });
}

function deletarUsuario(id) {
    Swal.fire({
        title: 'Tem certeza?',
        text: 'Esta ação não pode ser desfeita!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sim, deletar!',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: `{{ route("usuarios.deletar", ":id") }}`.replace(':id', id),
                type: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': token
                },
                success: function(response) {
                    if (response.success) {
                        Swal.fire('Deletado!', response.message, 'success');
                        carregarUsuarios();
                    }
                },
                error: function(xhr) {
                    const message = xhr.responseJSON?.message || 'Erro ao deletar usuário';
                    Swal.fire('Erro', message, 'error');
                }
            });
        }
    });
}

function limparErros() {
    $('#error_name').text('');
    $('#error_email').text('');
    $('#error_password').text('');
    $('#error_password_confirmation').text('');
}
</script>
@endsection
