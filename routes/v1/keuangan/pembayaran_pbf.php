<?php

use App\Http\Controllers\Api\Keuangan\PembayaranPbfController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('keuangan/pembayaran-hutang-pbf')->group(function () {
    Route::get('/', [PembayaranPbfController::class, 'index']);
    Route::get('/preview/{supplierId}', [PembayaranPbfController::class, 'preview']);
    Route::get('/detail/{id}', [PembayaranPbfController::class, 'show']);
    Route::post('/', [PembayaranPbfController::class, 'store']);
});
