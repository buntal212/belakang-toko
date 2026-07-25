<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Master\BarangController;

Route::group([
    'middleware' => 'auth:sanctum',
    'prefix' => 'master/barang'
], function () {

    Route::get('/get-data', [BarangController::class, 'index']);

    Route::post('/simpan', [BarangController::class, 'store']);

    Route::get('/detail/{id}', [BarangController::class, 'show']);

    Route::delete('/hapus/{id}', [BarangController::class, 'destroy']);

});