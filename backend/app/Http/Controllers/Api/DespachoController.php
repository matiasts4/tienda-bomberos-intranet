<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/** Legacy entry points delegate to the same guarded workflow as the current routes. */
class DespachoController extends Controller
{
    public function kanban(Request $request)
    {
        return app(\App\Http\Controllers\DespachoController::class)->getKanbanData();
    }

    public function updateEstadoByCotizacion(Request $request, $cotizacionId)
    {
        return app(\App\Http\Controllers\DespachoController::class)->updateEstado($request, $cotizacionId);
    }
}
