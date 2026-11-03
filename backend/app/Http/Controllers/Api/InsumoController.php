<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class InsumoController extends Controller
{
    public function index(): \Illuminate\Http\JsonResponse
    {
        $insumos = \App\Models\Insumo::orderBy('nombre')->get();
        return response()->json([
            'success' => true,
            'data' => $insumos
        ]);
    }
}
