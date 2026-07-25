<?php

namespace App\Http\Controllers\Api\V1\Master;

use App\Http\Controllers\Controller;
use App\Models\Master\MenusHed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    public function index()
    {
        $menus = MenusHed::with(
            [
                'rincian'
            ]
        )->get();
        return new JsonResponse($menus);
    }
}
