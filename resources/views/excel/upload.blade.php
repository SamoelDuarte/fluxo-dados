<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload e Processamento de Planilha</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-5">
    <h2 class="mb-4">Upload de Planilha (.xlsx)</h2>
    <form action="{{ route('excel.upload') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="mb-3">
            <label for="file" class="form-label">Selecione a Planilha</label>
            <input type="file" name="file" id="file" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary">Processar Planilha</button>
    </form>

    <h2 class="mt-5 mb-4">Upload para Inserção</h2>
    <form action="{{ route('insert.upload') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="mb-3">
            <label for="insert_into" class="form-label">Selecione o Arquivo para Inserção</label>
            <input type="file" name="insert_into" id="insert_into" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-success">Inserir Dados</button>
    </form>
</div>
</body>
</html>
