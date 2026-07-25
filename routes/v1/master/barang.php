<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Master\BarangController;

Route::group([
    'middleware' => 'auth:sanctum',
    'prefix' => 'master/barang'
], function () {

    Route::get('/get-data', [BarangController::class, 'index']);
    Route::get('/get-data-all', [BarangController::class, 'indexall']);
    Route::post('/simpan', [BarangController::class, 'store']);
    Route::post('/hapus', [BarangController::class, 'destroy']);



});
