
var url = window.location.origin;
$('#table-user').DataTable({
    processing: true,
    serverSide: true,
    "ajax": {
        "url": url + "/usuarios/getUser",
        "type": "GET"
    },
    "columns": [{
        "data": "first_name"
    },
    {
        "data": "email"
        },
    
    {
        "data": "phone"
    },{
        "data": "phone"
    },
    ],
    'columnDefs': [
        {
            targets: [2],
            className: 'dt-body-center'
        }
    ],
    'rowCallback': function (row, data, index) {

        $('td:eq(0)', row).html('<label>' + data['full_name'] + '</label>');
        $('td:eq(2)', row).html( '<label class="phone">'+data['phone']+'</label>');
        $('td:eq(3)', row).html( '<a href="javascript:;" data-toggle="modal" onClick="configModalDelete(' + data["id"] + ')" data-target="#modalDelete" class="btn btn-sm btn-danger delete"><i class="far fa-trash-alt"></i></a>');


    },
});

function configModalDelete(id){
   
    $('#id_user').val(id);
 }