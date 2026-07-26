<?php

use App\Http\Controllers\Api\Penjualan\SetoranBendaharaController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('penjualan/setoran-bendahara')->group(function () {
    Route::get('/preview', [SetoranBendaharaController::class, 'preview']);
    Route::get('/get-data', [SetoranBendaharaController::class, 'index']);
    Route::get('/detail/{id}', [SetoranBendaharaController::class, 'show']);
    Route::post('/simpan', [SetoranBendaharaController::class, 'store']);
});
