<?php

use App\Http\Controllers\CronController;
use App\Http\Controllers\ExcelController;
use App\Http\Controllers\HavanController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\FacebookController;
use App\Http\Controllers\WhatsappController;
use Illuminate\Support\Facades\Route;
require __DIR__ . '/auth.php';

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/
Route::get('/envioEmMassa', [\App\Http\Controllers\CronController::class, 'envioEmMassa']);
Route::get('/verificarInatividade', [\App\Http\Controllers\CronController::class, 'verificarInatividade']);
Route::prefix('whatsapp')->group(function () {
    Route::prefix('webhook')->group(function () {
        Route::post('verificaChat', [\App\Http\Controllers\WhatsappController::class, 'verificaChat']);
        Route::post('verificaCliente', [\App\Http\Controllers\WhatsappController::class, 'verificaCliente']);
        Route::post('verificaDividaOuAcordo', [\App\Http\Controllers\WhatsappController::class, 'verificaDividaOuAcordo']);
        Route::post('atualizaStep', [\App\Http\Controllers\WhatsappController::class, 'atualizaStepWebhook']);
        Route::post('verificaContratos', [\App\Http\Controllers\WhatsappController::class, 'verificaContratos']);
        Route::post('obterDocumentosAbertos', [\App\Http\Controllers\WhatsappController::class, 'obterDocumentosAbertos']);
        Route::post('obterContagemErros', [\App\Http\Controllers\WhatsappController::class, 'obterContagemErros']);
        Route::post('adicionarErroSessao', [\App\Http\Controllers\WhatsappController::class, 'adicionarErroSessao']);
        Route::post('atualizarContextoEStepSessao', [\App\Http\Controllers\WhatsappController::class, 'atualizarContextoEStepSessao']);
        Route::post('storeAcordo', [\App\Http\Controllers\WhatsappController::class, 'storeAcordo']);
        Route::post('storeAcordoParcelado', [\App\Http\Controllers\WhatsappController::class, 'storeAcordoParcelado']);
    });
});




Route::post('/whatsapp/webhook', [\App\Http\Controllers\WhatsappController::class, 'webhook']);
Route::get('/whatsapp/webhook', [\App\Http\Controllers\WhatsappController::class, 'webhook']);



Route::get('/planilha', [ExcelController::class, 'index']);
Route::post('/excel/upload', [ExcelController::class, 'upload'])->name('excel.upload');
Route::post('/insert-upload', [ExcelController::class, 'insertUpload'])->name('insert.upload');


Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');


    Route::get('/', [UploadController::class, 'index'])->name(name: 'upload.index');
    Route::post('/uploads', [UploadController::class, 'upload'])->name('upload.upload'); // Processamento do upload
    Route::get('/getLotes', [UploadController::class, 'getLotes'])->name('lotes.get'); // Processamento do upload
    Route::get('/lotes/{loteId}/carteiras', [UploadController::class, 'getCarteirasByLote'])->name('lotes.carteiras');
    Route::get('/download-lotes/{loteId}', [UploadController::class, 'downloadExcel'])->name('download.lote');

    Route::get('/whatsapp/connect', [WhatsappController::class, 'showForm'])->name('whatsapp.connect');
    Route::post('/whatsapp/connect', [WhatsappController::class, 'save'])->name('whatsapp.save');

    // Telefones CRUD
    Route::resource('telefones', \App\Http\Controllers\TelefoneController::class)->except(['show'])->middleware('auth');

    // Campanhas menu routes (placeholders)
    Route::get('/campanhas', [\App\Http\Controllers\CampanhaController::class, 'campanhas'])->name('campanhas.index');
    Route::get('/campanhas/contatos', [\App\Http\Controllers\CampanhaController::class, 'contatos'])->name('campanhas.contatos');
    Route::get('/campanhas/relatorio', [\App\Http\Controllers\CampanhaController::class, 'relatorio'])->name('campanhas.relatorio');

    // Campanha CRUD
    Route::get('/campanhas/crud', [\App\Http\Controllers\CampanhaCrudController::class, 'index'])->name('campanhas.crud.index');
    Route::get('/campanhas/crud/create', [\App\Http\Controllers\CampanhaCrudController::class, 'create'])->name('campanhas.crud.create');
    Route::get('/campanhas/crud/templates', [\App\Http\Controllers\CampanhaCrudController::class, 'getTemplates'])->name('campanhas.crud.templates');
    Route::get('/campanhas/crud/phone-numbers', [\App\Http\Controllers\CampanhaCrudController::class, 'getPhoneNumbers'])->name('campanhas.crud.phone-numbers');
    Route::post('/campanhas/crud', [\App\Http\Controllers\CampanhaCrudController::class, 'store'])->name('campanhas.crud.store');
    Route::get('/campanhas/crud/{campanha}/edit', [\App\Http\Controllers\CampanhaCrudController::class, 'edit'])->name('campanhas.crud.edit');
    Route::put('/campanhas/crud/{campanha}', [\App\Http\Controllers\CampanhaCrudController::class, 'update'])->name('campanhas.crud.update');
    Route::delete('/campanhas/crud/{campanha}', [\App\Http\Controllers\CampanhaCrudController::class, 'destroy'])->name('campanhas.crud.destroy');
    Route::post('/campanhas/crud/{campanha}/play', [\App\Http\Controllers\CampanhaCrudController::class, 'play'])->name('campanhas.crud.play');
    Route::post('/campanhas/crud/{campanha}/pause', [\App\Http\Controllers\CampanhaCrudController::class, 'pause'])->name('campanhas.crud.pause');

    // Contatos upload CRUD
    Route::get('/contatos', [\App\Http\Controllers\ContatoController::class, 'index'])->name('contatos.index');
    Route::get('/contatos/create', [\App\Http\Controllers\ContatoController::class, 'create'])->name('contatos.create');
    Route::post('/contatos', [\App\Http\Controllers\ContatoController::class, 'store'])->name('contatos.store');
    Route::delete('/contatos/{contato}', [\App\Http\Controllers\ContatoController::class, 'destroy'])->name('contatos.destroy');
    Route::get('/contatos/imports/{import}', [\App\Http\Controllers\ContatoController::class, 'importStatus'])->name('contatos.import.status');
    Route::post('/contatos/imports/{import}/process', [\App\Http\Controllers\ContatoController::class, 'processChunk'])->name('contatos.import.process');

    // Agendamento CRUD
    Route::get('/agendamento', [\App\Http\Controllers\AvailableSlotController::class, 'index'])->name('agendamento.index');
    Route::post('/agendamento/update', [\App\Http\Controllers\AvailableSlotController::class, 'update'])->name('agendamento.update');

    // Acordos CRUD
    Route::get('/acordos', [\App\Http\Controllers\AcordoCrudController::class, 'index'])->name('acordos.index');
    Route::get('/acordos/create', [\App\Http\Controllers\AcordoCrudController::class, 'create'])->name('acordos.create');
    Route::post('/acordos', [\App\Http\Controllers\AcordoCrudController::class, 'store'])->name('acordos.store');
    Route::get('/acordos/{acordo}', [\App\Http\Controllers\AcordoCrudController::class, 'show'])->name('acordos.show');
    Route::get('/acordos/{acordo}/edit', [\App\Http\Controllers\AcordoCrudController::class, 'edit'])->name('acordos.edit');
    Route::put('/acordos/{acordo}', [\App\Http\Controllers\AcordoCrudController::class, 'update'])->name('acordos.update');
    Route::delete('/acordos/{acordo}', [\App\Http\Controllers\AcordoCrudController::class, 'destroy'])->name('acordos.destroy');
    Route::get('/acordos/export/excel', [\App\Http\Controllers\AcordoCrudController::class, 'exportExcel'])->name('acordos.export');

    // Imagens Campanha CRUD
    Route::post('/imagens-campanha/store', [\App\Http\Controllers\ImagemCampanhaController::class, 'store'])->name('imagens.store');
    Route::get('/imagens-campanha/list', [\App\Http\Controllers\ImagemCampanhaController::class, 'listAll'])->name('imagens.list');
    Route::delete('/imagens-campanha/{id}', [\App\Http\Controllers\ImagemCampanhaController::class, 'destroy'])->name('imagens.destroy');

    // Usuários CRUD
    Route::get('/usuarios', [\App\Http\Controllers\UsuarioController::class, 'index'])->name('usuarios.index');
    Route::get('/usuarios/listar', [\App\Http\Controllers\UsuarioController::class, 'listar'])->name('usuarios.listar');
    Route::post('/usuarios/salvar', [\App\Http\Controllers\UsuarioController::class, 'salvar'])->name('usuarios.salvar');
    Route::get('/usuarios/editar/{id}', [\App\Http\Controllers\UsuarioController::class, 'editar'])->name('usuarios.editar');
    Route::delete('/usuarios/deletar/{id}', [\App\Http\Controllers\UsuarioController::class, 'deletar'])->name('usuarios.deletar');

});

Route::get('/211', [CronController::class, 'deletarAcordosFechadosApos211']); // Processamento do upload
Route::get('/parcelamento', [CronController::class, 'obterOpcoesParcelamento']); // Processamento do upload
Route::get('/gethavan', [CronController::class, 'getDadoHavan']); // Processamento do upload
Route::middleware(['cors'])->post('/obterparcelamento', [CronController::class, 'obterParcelamento']);

Route::get('/parcelamento2', [CronController::class, 'obterOpcoesParcelamento2']); // Processamento do upload
Route::get('/parcelamento3', [CronController::class, 'obterOpcoesParcelamento3']); // Processamento do upload
Route::get('/parcelamento4', [CronController::class, 'obterOpcoesParcelamento4']); // Processamento do upload
Route::get('/dados', [CronController::class, 'obterDadosEAtualizarContratos']); // Processamento do upload
Route::get('/teste', [CronController::class, 'obterparcelamento']); // Processamento do upload
Route::get('/whatsapp/auth-facebook', [WhatsappController::class, 'authFacebook'])->name('whatsapp.authFacebook');
Route::get('/whatsapp/callback', [WhatsappController::class, 'callback'])->name('whatsapp.callback');

// Deletar todos os WhatsApp templates
Route::get('/delete-all-templates', function () {
    $filePath = base_path('whatsapp_template_names.txt');
    
    if (!file_exists($filePath)) {
        return response()->json(['error' => 'Arquivo de templates não encontrado'], 404);
    }

    $templateNames = array_filter(array_map('trim', file($filePath)));
    $client = new \GuzzleHttp\Client();
    $token = 'EAAYOoqZC6d8sBPy91b3fXASkYQHbBScdIlZBlKttfWJql8v01SMwHCOFd6OWwW4EwZAu1Y8fCo8USr7H1nPpZAQhyS8gLByd2kRzIJhXoEXIVaMZBSceqKzEstvkDt9ZA4aqZC6Pg96M4DrZAy3tOL6BnknMRVwK93XcTZAlgySQfjMrSZA6XCS6ZB7L0hziMMKegZDZD';
    $businessId ='1349302486596641';
    
    $results = [
        'deleted' => [],
        'failed' => [],
        'total' => count($templateNames)
    ];

    foreach ($templateNames as $templateName) {
        try {
            $headers = [
                'Authorization' => "Bearer {$token}"
            ];
            $request = new \GuzzleHttp\Psr7\Request(
                'DELETE',
                "https://graph.facebook.com/v22.0/{$businessId}/message_templates?name={$templateName}",
                $headers
            );
            $res = $client->sendAsync($request)->wait();
            $statusCode = $res->getStatusCode();
            
            if ($statusCode == 200) {
                $results['deleted'][] = $templateName;
            } else {
                $results['failed'][] = [
                    'name' => $templateName,
                    'status' => $statusCode,
                    'response' => $res->getBody()->getContents()
                ];
            }
        } catch (\Exception $e) {
            $results['failed'][] = [
                'name' => $templateName,
                'error' => $e->getMessage()
            ];
        }
    }

    return response()->json($results);
});

