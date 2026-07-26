<?php

use App\Http\Controllers\Api\Master\PelangganController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('master/pelanggan')->group(function () {
    Route::get('/get-data', [PelangganController::class, 'index']);
    Route::get('/detail/{id}', [PelangganController::class, 'show']);
    Route::post('/simpan', [PelangganController::class, 'store']);
    Route::delete('/hapus/{id}', [PelangganController::class, 'destroy']);
});
