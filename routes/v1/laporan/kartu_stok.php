<?php

use App\Http\Controllers\Api\Laporan\KartuStokController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('laporan/kartu-stok')->group(function () {
    Route::get('/', [KartuStokController::class, 'index']);
});
