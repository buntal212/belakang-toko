<?php

use App\Http\Controllers\Api\Master\PajakController;
use Illuminate\Support\Facades\Route;

Route::group([
    'middleware' => 'auth:sanctum',
    'prefix' => 'master/pajak'
], function () {

    Route::get('/get-data', [PajakController::class, 'index']);

});
