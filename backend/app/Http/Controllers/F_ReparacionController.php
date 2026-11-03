<?php

namespace App\Http\Controllers;

use App\Models\Merma;
use App\Models\ControlCalidad;
use App\Models\OrdenProduccion;
use App\Models\MpMateriaPrima;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class F_MermaController extends Controller
{
    /**
     * Mostrar lista de reparaciones
     */
    public function index(Request $request): JsonResponse
    {
        $query = F_Merma::with([
            'controlCalidad.ordenProduccion.sku', 
            'ordenProduccion.sku', 
            'materiaPrima'
        ]);

        // Filtros
        if ($request->has('orden_produccion_id')) {
            $query->where('orden_produccion_id', $request->orden_produccion_id);
        }

        if ($request->has('control_calidad_id')) {
            $query->where('control_calidad_id', $request->control_calidad_id);
        }

        if ($request->has('materia_prima_id')) {
            $query->where('materia_prima_id', $request->materia_prima_id);
        }

        if ($request->has('motivo_merma')) {
            $query->where('motivo_merma', 'like', '%' . $request->motivo_merma . '%');
        }

        // Paginación
        $perPage = $request->get('per_page', 15);
        $reparaciones = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($reparaciones);
    }

    /**
     * Crear nueva merma
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'control_calidad_id' => 'required|exists:controles_calidad,id',
            'orden_produccion_id' => 'required|exists:ordenes_produccion,id',
            'materia_prima_id' => 'required|exists:mp_materias_primas,id',
            'cantidad_perdida' => 'required|integer|min:0',
            'motivo_merma' => 'nullable|string|max:120',
        ]);

        $merma = F_Merma::create($validated);
        $merma->load(['controlCalidad', 'ordenProduccion', 'materiaPrima']);

        return response()->json([
            'message' => 'Merma registrada exitosamente',
            'data' => $merma
        ], 201);
    }

    /**
     * Mostrar merma específica
     */
    public function show(Merma $merma): JsonResponse
    {
        $merma->load([
            'controlCalidad.ordenProduccion.sku',
            'controlCalidad.inspector', 
            'ordenProduccion.sku', 
            'materiaPrima'
        ]);
        
        return response()->json($merma);
    }

    /**
     * Actualizar merma
     */
    public function update(Request $request, Merma $merma): JsonResponse
    {
        $validated = $request->validate([
            'control_calidad_id' => 'sometimes|exists:controles_calidad,id',
            'orden_produccion_id' => 'sometimes|exists:ordenes_produccion,id',
            'materia_prima_id' => 'sometimes|exists:mp_materias_primas,id',
            'cantidad_perdida' => 'sometimes|integer|min:0',
            'motivo_merma' => 'nullable|string|max:120',
        ]);

        $merma->update($validated);
        $merma->load(['controlCalidad', 'ordenProduccion', 'materiaPrima']);

        return response()->json([
            'message' => 'Merma actualizada exitosamente',
            'data' => $merma
        ]);
    }

    /**
     * Eliminar merma
     */
    public function destroy(Merma $merma): JsonResponse
    {
        $merma->delete();

        return response()->json([
            'message' => 'Merma eliminada exitosamente'
        ]);
    }

    /**
     * Obtener reparaciones por orden de producción
     */
    public function porOrdenProduccion(F_OrdenProduccion $ordenProduccion): JsonResponse
    {
        $reparaciones = F_Merma::with(['controlCalidad', 'materiaPrima'])
            ->where('orden_produccion_id', $ordenProduccion->id)
            ->get();

        $resumen = [
            'orden_produccion' => $ordenProduccion,
            'total_reparaciones' => $reparaciones->count(),
            'cantidad_total_perdida' => $reparaciones->sum('cantidad_perdida'),
            'reparaciones_por_material' => $reparaciones->groupBy('materia_prima_id')->map(function ($reparacionesPorMaterial) {
                return [
                    'material' => $reparacionesPorMaterial->first()->materiaPrima,
                    'cantidad_perdida' => $reparacionesPorMaterial->sum('cantidad_perdida'),
                    'cantidad_reparaciones' => $reparacionesPorMaterial->count()
                ];
            })->values(),
            'reparaciones' => $reparaciones
        ];

        return response()->json($resumen);
    }

    /**
     * Obtener reparaciones por materia prima
     */
    public function porMateriaPrima(F_MpMateriaPrima $materiaPrima): JsonResponse
    {
        $reparaciones = F_Merma::with(['controlCalidad', 'ordenProduccion.sku'])
            ->where('materia_prima_id', $materiaPrima->id)
            ->get();

        $resumen = [
            'materia_prima' => $materiaPrima,
            'total_reparaciones' => $reparaciones->count(),
            'cantidad_total_perdida' => $reparaciones->sum('cantidad_perdida'),
            'reparaciones' => $reparaciones
        ];

        return response()->json($resumen);
    }

    /**
     * Reporte de reparaciones por rango de fechas
     */
    public function reporteReparaciones(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fecha_desde' => 'required|date',
            'fecha_hasta' => 'required|date|after_or_equal:fecha_desde',
        ]);

        $reparaciones = F_Merma::with(['controlCalidad', 'ordenProduccion.sku', 'materiaPrima'])
            ->whereDate('created_at', '>=', $validated['fecha_desde'])
            ->whereDate('created_at', '<=', $validated['fecha_hasta'])
            ->get();

        $reporte = [
            'periodo' => [
                'desde' => $validated['fecha_desde'],
                'hasta' => $validated['fecha_hasta']
            ],
            'total_reparaciones' => $reparaciones->count(),
            'cantidad_total_perdida' => $reparaciones->sum('cantidad_perdida'),
            'reparaciones_por_material' => $reparaciones->groupBy('materia_prima_id')->map(function ($reparacionesPorMaterial) {
                return [
                    'material' => $reparacionesPorMaterial->first()->materiaPrima,
                    'cantidad_perdida' => $reparacionesPorMaterial->sum('cantidad_perdida'),
                    'cantidad_reparaciones' => $reparacionesPorMaterial->count()
                ];
            })->values(),
            'motivos_frecuentes' => $reparaciones->whereNotNull('motivo_merma')
                ->groupBy('motivo_merma')
                ->map(function ($reparacionesPorMotivo) {
                    return [
                        'motivo' => $reparacionesPorMotivo->first()->motivo_merma,
                        'cantidad_reparaciones' => $reparacionesPorMotivo->count(),
                        'cantidad_perdida' => $reparacionesPorMotivo->sum('cantidad_perdida')
                    ];
                })
                ->sortByDesc('cantidad_reparaciones')
                ->values(),
            'reparaciones' => $reparaciones
        ];

        return response()->json($reporte);
    }
}
