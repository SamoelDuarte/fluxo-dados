var url = window.location.origin;
$('#tabela-entrega').DataTable({
    processing: true,
    serverSide: true,
    ajax: {
        url: url + "/entregas/getEntregas",
        type: "GET"
    },
    columns: [
        { data: "created_at" },
        { data: "id" },
        { data: "parcel" },
        { data: "invoice" },
        { data: "destination_state" },
        { 
            data: "carriers.trade_name",
            searchable: true // Habilita a pesquisa para o campo trade_name
        },
        {
            data: "id",
            render: function (data, type, row) {
                // Adiciona um botão de visualização (olho) para cada entrega
                return '<button class="btn btn-sm btn-primary" onclick="openModal(' + data + ')"><i class="fas fa-eye"></i></button>';
            }
        }
    ],
    columnDefs: [
        {
            targets: [2],
            className: 'dt-body-center'
        }
    ],
    rowCallback: function (row, data, index) {
        $('td:eq(0)', row).html(data['display_data']);
        $('td:eq(1)', row).html(data['carriers'].trade_name);
        $('td:eq(5)', row).html(data['status'][0].status);
    }
});

function openModal(id) {
    // Requisição AJAX para buscar informações da entrega pelo ID
    $.ajax({
        url: '/entregas/getinfoEntrega/' + id, // Rota para buscar os detalhes da entrega
        type: 'GET',
        success: function(response) {
            // Preencher os campos do modal com as informações retornadas
            $('#modalDetalhes .modal-body').html(response);
            // Abrir o modal
            $('#modalDetalhes').modal('show');
        },
        error: function(xhr, status, error) {
            console.error(error);
        }
    });
}

