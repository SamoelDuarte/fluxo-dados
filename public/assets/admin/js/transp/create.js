document.getElementById('cep').addEventListener('input', function () {
    let cep = this.value.replace(/\D/g, '');

    if (cep.length === 8) {
        cep = cep.substring(0, 5) + '-' + cep.substring(5);
        this.value = cep;

        // Consulta o ViaCEP automaticamente
        consultarViaCEP(cep);
    }
});

function consultarViaCEP(cep) {
    axios.get(`https://viacep.com.br/ws/${cep}/json/`)
        .then(function (response) {
            const data = response.data;
            if (data.erro) {
                // CEP inválido
                exibirAlertaErro();
                limparCampos();
                focarCampoCEP();
            } else {
                // Preencher campos com dados do CEP
                document.getElementById('estado').value = data.uf;
                document.getElementById('cidade').value = data.localidade;
                document.getElementById('endereco').value = data.logradouro;
                document.getElementById('bairro').value = data.bairro;
            }
        })
        .catch(function (error) {
            console.log(error);
        });
}

function exibirAlertaErro() {
    Swal.fire({
        icon: 'error',
        title: 'CEP Inválido',
        text: 'Por favor, insira um CEP válido.',
        timer: 3000,
        showConfirmButton: false,
    });
}

function limparCampos() {
    document.getElementById('estado').value = '';
    document.getElementById('cidade').value = '';
    document.getElementById('endereco').value = '';
    document.getElementById('bairro').value = '';
    document.getElementById('cep').value = '';
}

function focarCampoCEP() {
    document.getElementById('cep').focus();
}