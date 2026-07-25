<?php

use App\Http\Controllers\Api\V1\Master\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('master')->group(function () {
    Route::apiResource('users', UserController::class);
});
