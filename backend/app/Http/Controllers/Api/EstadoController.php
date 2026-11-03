<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Estado;
use Illuminate\Http\Request;


class EstadoController extends Controller
{
    public function index(\Illuminate\Http\Request $request)
    {
        $q = \App\Models\Estado::query();

        // Si mandan scope, filtra. Si no, trae todos.
        if ($request->filled('scope')) {
            $q->where('scope', $request->string('scope')->toString());
        }

        return response()->json(
            $q->orderBy('scope')->orderBy('orden')->orderBy('id')->get()
        );
    }

}
