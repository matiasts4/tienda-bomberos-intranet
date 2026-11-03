<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ejecutivo;
use Illuminate\Http\Request;

class EjecutivoController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            Ejecutivo::query()->orderBy('nombre')->get()
        );
    }
}
