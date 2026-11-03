<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cotizacion;
use App\Models\Oportunidad;
use App\Models\User;
use App\Services\TraspasoResponsableService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TraspasoResponsableController extends Controller
{
    public function __construct(private TraspasoResponsableService $traspasos)
    {
    }

    /** POST /api/oportunidades/{oportunidad}/traspasar */
    public function oportunidad(Request $request, Oportunidad $oportunidad)
    {
        $this->traspasar($request, $oportunidad);

        return response()->json($oportunidad->fresh()->load(Oportunidad::RELACIONES_RESPONSABLE));
    }

    /** POST /api/cotizaciones/{cotizacion}/traspasar */
    public function cotizacion(Request $request, Cotizacion $cotizacion)
    {
        $this->traspasar($request, $cotizacion);

        return response()->json($cotizacion->fresh()->load(Cotizacion::RELACIONES_RESPONSABLE));
    }

    private function traspasar(Request $request, Oportunidad|Cotizacion $tarjeta): void
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'motivo' => ['nullable', 'string', 'max:255'],
        ]);

        $actor = $request->user();

        abort_unless(
            $this->traspasos->puedeTraspasar($actor, $tarjeta),
            403,
            'Solo el ejecutivo responsable o un administrador puede traspasar esta tarjeta.'
        );

        $this->traspasos->traspasar($tarjeta, User::findOrFail($data['user_id']), $actor, $data['motivo'] ?? null);
    }
}
