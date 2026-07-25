<?php

use App\Http\Controllers\Api\V1\Master\MenuController;
use Illuminate\Support\Facades\Route;

Route::group([
    'middleware' => 'auth:sanctum',
    'prefix' => 'master/menus'
], function () {
    Route::get('/menus', [MenuController::class, 'index']);
});
