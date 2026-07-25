<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Master\MerkController;

Route::group([
    'middleware' => 'auth:sanctum',
    'prefix' => 'master/merk'
], function () {

    Route::get('/get-data', [MerkController::class, 'index']);

    Route::get('/get-all', [MerkController::class, 'getAll']);

    Route::post('/simpan', [MerkController::class, 'store']);

    Route::get('/detail/{id}', [MerkController::class, 'show']);

    Route::delete('/hapus/{id}', [MerkController::class, 'destroy']);

});
