<?php

use App\Http\Controllers\Api\Laporan\PenjualanController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('/laporan/penjualan')->group(function () {
    Route::get('/', [PenjualanController::class, 'index']);
    Route::get('/excel', [PenjualanController::class, 'excel']);
    Route::get('/pdf', [PenjualanController::class, 'pdf']);
});
