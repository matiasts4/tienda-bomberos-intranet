<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cuerpo;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard "Red de Bomberos": cada Cuerpo con sus compañías, la cartera a la
 * que pertenece y si Tienda Bomberos le ha vendido.
 *
 * Venta = cotización con fecha_venta que no terminó Desistida/Anulada (sí
 * cuenta la No Vigente "Finalizada", que es una venta cerrada). La participación
 * se mide como cobertura: Cuerpos (y Compañías) con al menos una venta en el
 * período sobre el total de la red.
 */
class ReporteRedBomberosController extends Controller
{
    public function index(Request $request)
    {
        $filtros = $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ]);

        $ventas = DB::table('cotizaciones as c')
            ->leftJoin('companias as co', 'co.id', '=', 'c.compania_id')
            ->leftJoin('estados as e', 'e.id', '=', 'c.estado_id')
            ->whereNotNull('c.fecha_venta')
            ->where(fn ($q) => $q->whereNull('c.motivo_no_vigente')->orWhere('c.motivo_no_vigente', 'finalizada'))
            ->where(fn ($q) => $q->whereNull('e.nombre')->orWhereNotIn('e.nombre', ['Anulado', 'Rechazado', 'Rechazada']))
            ->when($filtros['desde'] ?? null, fn ($q, $d) => $q->whereDate('c.fecha_venta', '>=', $d))
            ->when($filtros['hasta'] ?? null, fn ($q, $h) => $q->whereDate('c.fecha_venta', '<=', $h))
            ->selectRaw('COALESCE(c.cuerpo_id, co.cuerpo_id) as cuerpo_id, c.compania_id, c.total, c.fecha_venta')
            ->get()
            ->filter(fn ($v) => $v->cuerpo_id !== null)
            ->groupBy('cuerpo_id');

        $cuerpos = Cuerpo::query()
            ->select(['id', 'nombre', 'region_id', 'comuna_id', 'ejecutivo_id'])
            ->with('comuna:id,nombre')
            ->withCount('companias')
            ->orderBy('nombre')
            ->get()
            ->map(function (Cuerpo $cuerpo) use ($ventas) {
                $suyas = $ventas->get($cuerpo->id, collect());

                return [
                    'id' => (string) $cuerpo->id,
                    'nombre' => $cuerpo->nombre,
                    'region_id' => (int) $cuerpo->region_id,
                    'comuna' => $cuerpo->comuna?->nombre,
                    'ejecutivo_id' => $cuerpo->ejecutivo_id,
                    'companias' => $cuerpo->companias_count,
                    'companias_con_venta' => $suyas->pluck('compania_id')->filter()->unique()->count(),
                    'ventas' => $suyas->count(),
                    'monto' => (float) $suyas->sum('total'),
                    'ultima_venta' => $suyas->max('fecha_venta'),
                ];
            });

        $ejecutivos = User::query()
            ->whereIn('id', $cuerpos->pluck('ejecutivo_id')->filter()->unique())
            ->orWhereNotNull('codigo_vendedor')
            ->orderByRaw('codigo_vendedor IS NULL, codigo_vendedor')
            ->get(['id', 'name', 'codigo_vendedor']);

        return response()->json([
            'cuerpos' => $cuerpos->values(),
            'ejecutivos' => $ejecutivos,
        ]);
    }
}
