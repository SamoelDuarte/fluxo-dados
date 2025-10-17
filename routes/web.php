
<?php

use App\Http\Controllers\CronController;
use App\Http\Controllers\ExcelController;
use App\Http\Controllers\HavanController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\FacebookController;
use App\Http\Controllers\WhatsappController;
use Illuminate\Support\Facades\Route;
require __DIR__.'/auth.php';

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

Route::get('/', function () {
    return view('welcome');
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


    Route::get('/', [UploadController::class, 'index'])->name('upload.index');
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

       

});

Route::get('/parcelamento', [CronController::class, 'obterOpcoesParcelamento']); // Processamento do upload
Route::middleware(['cors'])->post('/obterparcelamento', [CronController::class, 'obterParcelamento']);

Route::get('/parcelamento2', [CronController::class, 'obterOpcoesParcelamento2']); // Processamento do upload
Route::get('/parcelamento3', [CronController::class, 'obterOpcoesParcelamento3']); // Processamento do upload
Route::get('/parcelamento4', [CronController::class, 'obterOpcoesParcelamento4']); // Processamento do upload
Route::get('/dados', [CronController::class, 'obterDadosEAtualizarContratos']); // Processamento do upload
Route::get('/teste', [CronController::class, 'obterparcelamento']); // Processamento do upload
    Route::get('/whatsapp/auth-facebook', [WhatsappController::class, 'authFacebook'])->name('whatsapp.authFacebook');
    Route::get('/whatsapp/callback', [WhatsappController::class, 'callback'])->name('whatsapp.callback');

