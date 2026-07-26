<?php

use App\Http\Controllers\Api\Penjualan\PembayaranPelangganController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('penjualan/pembayaran-pelanggan')->group(function () {
    Route::get('/', [PembayaranPelangganController::class, 'index']);
    Route::get('/preview/{pelangganId}', [PembayaranPelangganController::class, 'preview']);
    Route::get('/detail/{id}', [PembayaranPelangganController::class, 'show']);
    Route::post('/', [PembayaranPelangganController::class, 'store']);
});
