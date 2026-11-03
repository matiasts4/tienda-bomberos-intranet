<?php

namespace App\Http\Controllers;

use App\Models\ControlCalidad;
use App\Models\OrdenProduccion;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ControlCalidadController extends Controller
{
    /**
     * Mostrar lista de controles de calidad
     */
    public function index(Request $request): JsonResponse
    {
        $query = ControlCalidad::with(['ordenProduccion.peticion', 'ordenProduccion.sku', 'inspector', 'reparaciones']);

        // Filtros
        if ($request->has('orden_produccion_id')) {
            $query->where('orden_produccion_id', $request->orden_produccion_id);
        }

        if ($request->has('inspector_id')) {
            $query->where('inspector_id', $request->inspector_id);
        }

        if ($request->has('fecha_desde')) {
            $query->whereDate('fecha_inspeccion', '>=', $request->fecha_desde);
        }

        if ($request->has('fecha_hasta')) {
            $query->whereDate('fecha_inspeccion', '<=', $request->fecha_hasta);
        }

        // Paginación
        $perPage = $request->get('per_page', 15);
        $controles = $query->orderBy('fecha_inspeccion', 'desc')->paginate($perPage);

        return response()->json($controles);
    }

    /**
     * Crear nuevo control de calidad
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'orden_produccion_id' => 'required|exists:ordenes_produccion,id',
            'fecha_inspeccion' => 'required|date',
            'cantidad_aprobada' => 'required|integer|min:0',
            'cantidad_rechazada' => 'required|integer|min:0',
            'inspector_id' => 'nullable|exists:users,id',
            'observaciones' => 'nullable|string',
        ]);

        $control = ControlCalidad::create($validated);
        $control->load(['ordenProduccion', 'inspector']);

        return response()->json([
            'message' => 'Control de calidad creado exitosamente',
            'data' => $control
        ], 201);
    }

    /**
     * Mostrar control de calidad específico
     */
    public function show(ControlCalidad $controlCalidad): JsonResponse
    {
        $controlCalidad->load([
            'ordenProduccion.peticion', 
            'ordenProduccion.sku', 
            'inspector', 
            'reparaciones.materiaPrima'
        ]);
        
        return response()->json($controlCalidad);
    }

    /**
     * Actualizar control de calidad
     */
    public function update(Request $request, ControlCalidad $controlCalidad): JsonResponse
    {
        $validated = $request->validate([
            'orden_produccion_id' => 'sometimes|exists:ordenes_produccion,id',
            'fecha_inspeccion' => 'sometimes|date',
            'cantidad_aprobada' => 'sometimes|integer|min:0',
            'cantidad_rechazada' => 'sometimes|integer|min:0',
            'inspector_id' => 'nullable|exists:users,id',
            'observaciones' => 'nullable|string',
        ]);

        $controlCalidad->update($validated);
        $controlCalidad->load(['ordenProduccion', 'inspector']);

        return response()->json([
            'message' => 'Control de calidad actualizado exitosamente',
            'data' => $controlCalidad
        ]);
    }

    /**
     * Eliminar control de calidad
     */
    public function destroy(ControlCalidad $controlCalidad): JsonResponse
    {
        $controlCalidad->delete();

        return response()->json([
            'message' => 'Control de calidad eliminado exitosamente'
        ]);
    }

    /**
     * Obtener estadísticas de calidad por orden de producción
     */
    public function estadisticasPorOrden(OrdenProduccion $ordenProduccion): JsonResponse
    {
        $controles = $ordenProduccion->controlesCalidad;
        
        $estadisticas = [
            'total_inspecciones' => $controles->count(),
            'cantidad_total_aprobada' => $controles->sum('cantidad_aprobada'),
            'cantidad_total_rechazada' => $controles->sum('cantidad_rechazada'),
            'porcentaje_aprobacion_promedio' => $controles->avg('porcentaje_aprobacion'),
            'ultima_inspeccion' => $controles->sortByDesc('fecha_inspeccion')->first(),
        ];

        return response()->json($estadisticas);
    }

    /**
     * Obtener controles por inspector
     */
    public function porInspector(User $inspector): JsonResponse
    {
        $controles = ControlCalidad::with(['ordenProduccion.sku'])
            ->where('inspector_id', $inspector->id)
            ->orderBy('fecha_inspeccion', 'desc')
            ->get();

        return response()->json($controles);
    }

    /**
     * Reporte de calidad por rango de fechas
     */
    public function reporteCalidad(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fecha_desde' => 'required|date',
            'fecha_hasta' => 'required|date|after_or_equal:fecha_desde',
        ]);

        $controles = ControlCalidad::with(['ordenProduccion.sku', 'inspector'])
            ->whereDate('fecha_inspeccion', '>=', $validated['fecha_desde'])
            ->whereDate('fecha_inspeccion', '<=', $validated['fecha_hasta'])
            ->get();

        $reporte = [
            'periodo' => [
                'desde' => $validated['fecha_desde'],
                'hasta' => $validated['fecha_hasta']
            ],
            'total_inspecciones' => $controles->count(),
            'cantidad_total_aprobada' => $controles->sum('cantidad_aprobada'),
            'cantidad_total_rechazada' => $controles->sum('cantidad_rechazada'),
            'porcentaje_aprobacion_general' => $controles->count() > 0 ? 
                ($controles->sum('cantidad_aprobada') / ($controles->sum('cantidad_aprobada') + $controles->sum('cantidad_rechazada'))) * 100 : 0,
            'inspectores_activos' => $controles->pluck('inspector')->whereNotNull()->unique('id')->count(),
            'controles' => $controles
        ];

        return response()->json($reporte);
    }
}
