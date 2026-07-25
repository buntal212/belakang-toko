<?php

use App\Http\Controllers\Api\Master\SupplierController;
use Illuminate\Support\Facades\Route;

Route::group([
    'middleware' => 'auth:sanctum',
    'prefix' => 'master/supplier'
], function () {

    Route::get('/get-data', [SupplierController::class, 'index']);
    Route::get('/get-data-all', [SupplierController::class, 'indexall']);
    Route::post('/simpan', [SupplierController::class, 'store']);

    Route::get('/detail/{id}', [SupplierController::class, 'show']);

    Route::delete('/hapus/{id}', [SupplierController::class, 'destroy']);

});
