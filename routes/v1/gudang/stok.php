<?php

use App\Http\Controllers\Api\Gudang\StokController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('gudang/stok')->group(function () {
    Route::get('/produk-tersedia', [StokController::class, 'produkTersedia']);
    Route::get('/get-data', [StokController::class, 'index']);
    Route::get('/detail/{id}', [StokController::class, 'show']);
});
