@extends('layouts.app')

@section('title', 'Adicionar Contatos')

@section('content')
    <div class="card">
        <div class="card-header">Adicionar Contatos (upload CSV)</div>
        <div class="card-body">
            <form id="uploadForm">
                @csrf
                <div class="form-group">
                    <label>Nome do contato (grupo)</label>
                    <input type="text" name="name" id="name" class="form-control" required>
                </div>

                <div class="form-group">
                    <label>Arquivo (CSV)(Excel)</label>
                    <input type="file" name="file" id="file" accept=".csv,.xls,.xlsx" class="form-control" required>
                </div>

                <div class="form-group">
                    <div class="progress" style="height:20px; display:none;" id="progressWrapper">
                        <div id="progressBar" class="progress-bar" role="progressbar" style="width:0%">0%</div>
                    </div>
                    <div id="progressStatus" style="margin-top:8px; display:none;">Aguardando envio...</div>
                </div>

                <button id="btnSave" class="btn btn-primary">Salvar</button>
                <a href="{{ route('contatos.index') }}" class="btn btn-secondary">Voltar</a>
            </form>
        </div>
    </div>

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script>
        document.getElementById('uploadForm').addEventListener('submit', function (e) {
            e.preventDefault();
            const name = document.getElementById('name').value;
            const file = document.getElementById('file').files[0];

            if (!name || !file) {
                alert('Preencha nome e selecione um arquivo.');
                return;
            }

            const formData = new FormData();
            formData.append('name', name);
            formData.append('file', file);
            formData.append('_token', '{{ csrf_token() }}');

            const progressWrapper = document.getElementById('progressWrapper');
            const progressBar = document.getElementById('progressBar');
            progressWrapper.style.display = 'block';

            axios.post('{{ route('contatos.store') }}', formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
                onUploadProgress: function (progressEvent) {
                    const percentCompleted = Math.round((progressEvent.loaded * 100) / progressEvent.total);
                    progressBar.style.width = percentCompleted + '%';
                    progressBar.textContent = percentCompleted + '%';
                }
            }).then(function (res) {
                if (res.data.success && res.data.import_id) {
                    // Start polling import status
                    const importId = res.data.import_id;
                    const pollInterval = 1000; // ms

                    // show initial activity so user knows something started
                    progressBar.style.width = '5%';
                    progressBar.textContent = 'Iniciando...';
                    const progressStatus = document.getElementById('progressStatus');
                    progressStatus.style.display = 'block';
                    progressStatus.textContent = 'Arquivo enviado. Aguardando processamento...';

                    // Now drive processing by requesting server to process chunks repeatedly
                    const processChunkUrl = "{{ url('/contatos/imports') }}/" + importId + "/process";
                    const drive = function () {
                        axios.post(processChunkUrl, {_token: '{{ csrf_token() }}'})
                            .then(function (r) {
                                const data = r.data;
                                if (data.total_rows && data.total_rows > 0) {
                                    const percent = Math.round((data.processed_rows * 100) / data.total_rows);
                                    progressBar.style.width = percent + '%';
                                    progressBar.textContent = percent + '% (' + data.processed_rows + '/' + data.total_rows + ')';
                                    progressStatus.textContent = 'Processando: ' + data.processed_rows + ' de ' + data.total_rows;
                                } else {
                                    progressBar.style.width = '50%';
                                    progressBar.textContent = 'Processando...';
                                }

                                if (data.status === 'completed') {
                                    progressBar.style.width = '100%';
                                    progressBar.textContent = '100%';
                                    progressStatus.textContent = 'Importação concluída.';
                                    setTimeout(function () { window.location.href = '{{ route('contatos.index') }}'; }, 600);
                                } else if (data.status === 'failed') {
                                    alert('Import failed: ' + (data.error || 'erro desconhecido'));
                                } else {
                                    // schedule next chunk
                                    setTimeout(drive, 300);
                                }
                            }).catch(function (err) {
                                alert('Erro ao processar chunk: ' + err.message);
                            });
                    };

                    // start
                    setTimeout(drive, 300);
                } else {
                    alert('Erro ao processar: ' + JSON.stringify(res.data));
                }
            }).catch(function (err) {
                alert('Erro: ' + (err.response?.data?.error || err.message));
            });
        });
    </script>
@endsection

@endsection
