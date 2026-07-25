<?php

use App\Http\Controllers\Api\Penjualan\PenjualanController;
use App\Http\Controllers\Api\Penjualan\ReturPenjualanController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('penjualan')->group(function () {
    Route::get('/get-data', [PenjualanController::class, 'index']);
    Route::get('/detail/{id}', [PenjualanController::class, 'show']);
    Route::post('/simpan', [PenjualanController::class, 'store']);
});

Route::middleware('auth:sanctum')->prefix('penjualan/retur')->group(function () {
    Route::get('/get-data', [ReturPenjualanController::class, 'index']);
    Route::get('/detail/{id}', [ReturPenjualanController::class, 'show']);
    Route::get('/transaksi/{id}', [ReturPenjualanController::class, 'transaksi']);
    Route::post('/simpan', [ReturPenjualanController::class, 'store']);
    Route::delete('/hapus/{id}', [ReturPenjualanController::class, 'destroy']);
});
