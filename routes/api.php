<?php

use App\Http\Controllers\HavanController;
use App\Http\Controllers\CronController;
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
