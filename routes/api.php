<?php

use App\Helpers\Routes\RouteHelper;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    RouteHelper::includeRouteFiles(base_path('routes/v1'));

});
