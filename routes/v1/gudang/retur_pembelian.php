<?php

use App\Http\Controllers\Api\Gudang\ReturPembelianController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('gudang/retur-pembelian')->group(function () {
    Route::get('/get-data', [ReturPembelianController::class, 'index']);
    Route::get('/penerimaan', [ReturPembelianController::class, 'penerimaan']);
    Route::get('/penerimaan/{id}', [ReturPembelianController::class, 'penerimaanDetail']);
    Route::get('/detail/{id}', [ReturPembelianController::class, 'show']);
    Route::post('/simpan', [ReturPembelianController::class, 'store']);
    Route::delete('/hapus/{id}', [ReturPembelianController::class, 'destroy']);
});
