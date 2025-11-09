<?php

use App\Http\Controllers\HavanController;
use App\Http\Controllers\CronController;
use App\Http\Controllers\AcordoController;
use App\Http\Controllers\WhatsappController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Rotas da API Havan - SEM proteção CSRF
Route::prefix('havan')->group(function () {
    Route::get('/teste', [HavanController::class, 'testeConectividade']);
    Route::post('/obterparcelamento', [HavanController::class, 'obterParcelamento']);
    Route::post('/contratarenegociacao', [HavanController::class, 'contratarRenegociacao']);
    Route::post('/gravarocorrencia', [HavanController::class, 'gravarOcorrencia']);
    Route::post('/obterboletos', [HavanController::class, 'obterBoletos']);
});

Route::get('/gethavan', [CronController::class, 'getDadoHavan']);

// Rota para envio em massa de campanhas
Route::get('/envio-em-massa', [CronController::class, 'envioEmMassa']);
Route::post('/envio-em-massa', [CronController::class, 'envioEmMassa']);

// Rotas de Acordos
Route::prefix('acordos')->group(function () {
    Route::get('/', [AcordoController::class, 'index']); // Listar todos
    Route::post('/', [AcordoController::class, 'store']); // Criar novo
    Route::post('/store', [AcordoController::class, 'storeAcordo']); // Criar novo (alias)
    Route::get('/ativos', [AcordoController::class, 'ativos']); // Listar ativos
    Route::get('/pendentes', [AcordoController::class, 'pendentes']); // Listar pendentes
    Route::get('/documento/{documento}', [AcordoController::class, 'porDocumento']); // Buscar por documento
    Route::get('/{id}', [AcordoController::class, 'show']); // Buscar por ID
    Route::put('/{id}', [AcordoController::class, 'update']); // Atualizar
    Route::patch('/{id}/status', [AcordoController::class, 'atualizarStatus']); // Atualizar status
    Route::delete('/{id}', [AcordoController::class, 'destroy']); // Deletar
});

// Rotas do WhatsApp
Route::prefix('whatsapp')->group(function () {
    Route::post('/store-acordo', [WhatsappController::class, 'storeAcordo']); // Criar acordo via WhatsApp
});
