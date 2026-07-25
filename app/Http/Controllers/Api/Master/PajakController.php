<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Models\Master\PajakSekarang;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PajakController extends Controller
{
    public function index()
    {
        $data = PajakSekarang::all();

        return new JsonResponse($data);
    }
}
