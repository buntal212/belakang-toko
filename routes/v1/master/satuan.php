<?php

use App\Http\Controllers\Api\Master\SatuanController;
use Illuminate\Support\Facades\Route;

Route::group([
    'middleware' => 'auth:sanctum',
    'prefix' => 'master/satuan'
], function () {
    Route::get('/get-satuan', [SatuanController::class, 'index']);
    Route::get('/get-all', [SatuanController::class, 'getAll']);
    Route::post('/simpan', [SatuanController::class, 'store']);
    Route::delete('/hapus/{id}', [SatuanController::class, 'destroy']);
});
