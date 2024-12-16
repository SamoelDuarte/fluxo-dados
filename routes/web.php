<?php

use App\Http\Controllers\CronController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UploadController;
use Illuminate\Support\Facades\Route;

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




});

Route::get('/parcelamento', [CronController::class, 'obterOpcoesParcelamento']); // Processamento do upload
Route::middleware(['cors'])->post('/obterparcelamento', [CronController::class, 'obterParcelamento']);

Route::get('/parcelamento2', [CronController::class, 'obterOpcoesParcelamento2']); // Processamento do upload
Route::get('/parcelamento3', [CronController::class, 'obterOpcoesParcelamento3']); // Processamento do upload
Route::get('/parcelamento4', [CronController::class, 'obterOpcoesParcelamento4']); // Processamento do upload
Route::get('/dados', [CronController::class, 'obterDadosEAtualizarContratos']); // Processamento do upload

Route::get('/teste', function () {

});


require __DIR__ . '/auth.php';
