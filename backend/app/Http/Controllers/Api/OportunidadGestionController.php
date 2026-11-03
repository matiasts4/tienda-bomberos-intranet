<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Oportunidad;
use App\Models\OportunidadGestion;
use Illuminate\Http\Request;

class OportunidadGestionController extends Controller
{
    public function index(Oportunidad $oportunidad)
    {
        $gestiones = OportunidadGestion::where('oportunidad_id', $oportunidad->id)
            ->with('user:id,name,last_name')
            ->orderBy('fecha_gestion', 'desc')
            ->get();

        return response()->json($gestiones);
    }

    public function store(Request $request, Oportunidad $oportunidad)
    {
        $request->validate([
            'tipo_contacto' => 'required|string',
            'glosa' => 'required|string',
            'fecha_gestion' => 'required|date',
            'fecha_vencimiento_nueva' => 'nullable|date',
        ]);

        $gestion = OportunidadGestion::create([
            'oportunidad_id' => $oportunidad->id,
            'user_id' => $request->user()->id,
            'tipo_contacto' => $request->tipo_contacto,
            'glosa' => $request->glosa,
            'fecha_gestion' => $request->fecha_gestion,
            'fecha_vencimiento_nueva' => $request->fecha_vencimiento_nueva,
        ]);

        return response()->json([
            'message' => 'Gestión registrada correctamente',
            'gestion' => $gestion,
            'oportunidad_id' => $oportunidad->id,
        ]);
    }
}
