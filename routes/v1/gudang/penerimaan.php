<?php

use App\Http\Controllers\Api\Gudang\PenerimaanController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('gudang/penerimaan')->group(function () {
    Route::get('/get-data', [PenerimaanController::class, 'index']);
    Route::get('/detail/{id}', [PenerimaanController::class, 'show']);
    Route::post('/simpan', [PenerimaanController::class, 'store']);
    Route::post('/kirim-stok/{id}', [PenerimaanController::class, 'kirimStok']);
    Route::delete('/hapus/{id}', [PenerimaanController::class, 'destroy']);
});
