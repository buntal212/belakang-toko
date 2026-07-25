<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Master\SupplierController;

Route::group([
    'middleware' => 'auth:sanctum',
    'prefix' => 'master/supplier'
], function () {

    Route::get('/get-data', [SupplierController::class, 'index']);

    Route::post('/simpan', [SupplierController::class, 'store']);

    Route::get('/detail/{id}', [SupplierController::class, 'show']);

    Route::delete('/hapus/{id}', [SupplierController::class, 'destroy']);

});