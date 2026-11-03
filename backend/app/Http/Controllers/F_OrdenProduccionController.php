<?php

namespace App\Http\Controllers;

use App\Models\F_OrdenProduccion;
use App\Models\Peticion;
use App\Models\PteSku;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class F_OrdenProduccionController extends Controller
{
    /**
     * Mostrar lista de órdenes de producción
     */
    public function index(Request $request): JsonResponse
    {
        $query = F_OrdenProduccion::with(['peticion', 'sku', 'controlesCalidad', 'reparaciones']);

        // Filtros
        if ($request->has('estado_produccion')) {
            $query->where('estado_produccion', $request->estado_produccion);
        }

        if ($request->has('peticion_id')) {
            $query->where('peticion_id', $request->peticion_id);
        }

        if ($request->has('sku')) {
            $query->where('sku', 'like', '%' . $request->sku . '%');
        }

        // Paginación
        $perPage = $request->get('per_page', 15);
        $ordenes = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($ordenes);
    }

    /**
     * Crear nueva orden de producción
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'peticion_id' => 'required|exists:peticiones,id',
            'sku' => 'required|string|max:50|exists:pte_skus,sku',
            'cantidad_a_producir' => 'required|integer|min:1',
            'estado_produccion' => 'nullable|string|max:30',
        ]);

        $orden = F_OrdenProduccion::create($validated);
        $orden->load(['peticion', 'sku']);

        return response()->json([
            'message' => 'Orden de producción creada exitosamente',
            'data' => $orden
        ], 201);
    }

    /**
     * Mostrar orden de producción específica
     */
    public function show(F_OrdenProduccion $ordenProduccion): JsonResponse
    {
        $ordenProduccion->load(['peticion', 'sku', 'controlesCalidad.inspector', 'reparaciones.materiaPrima']);
        
        return response()->json($ordenProduccion);
    }

    /**
     * Actualizar orden de producción
     */
    public function update(Request $request, F_OrdenProduccion $ordenProduccion): JsonResponse
    {
        $validated = $request->validate([
            'peticion_id' => 'sometimes|exists:peticiones,id',
            'sku' => 'sometimes|string|max:50|exists:pte_skus,sku',
            'cantidad_a_producir' => 'sometimes|integer|min:1',
            'estado_produccion' => 'nullable|string|max:30',
        ]);

        $ordenProduccion->update($validated);
        $ordenProduccion->load(['peticion', 'sku']);

        return response()->json([
            'message' => 'Orden de producción actualizada exitosamente',
            'data' => $ordenProduccion
        ]);
    }

    /**
     * Eliminar orden de producción
     */
    public function destroy(F_OrdenProduccion $ordenProduccion): JsonResponse
    {
        $ordenProduccion->delete();

        return response()->json([
            'message' => 'Orden de producción eliminada exitosamente'
        ]);
    }

    /**
     * Obtener órdenes por estado de producción
     */
    public function porEstado(string $estado): JsonResponse
    {
        $ordenes = F_OrdenProduccion::with(['peticion', 'sku'])
            ->where('estado_produccion', $estado)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($ordenes);
    }

    /**
     * Cambiar estado de producción
     */
    public function cambiarEstado(Request $request, F_OrdenProduccion $ordenProduccion): JsonResponse
    {
        $validated = $request->validate([
            'estado_produccion' => 'required|string|max:30',
        ]);

        $ordenProduccion->update($validated);

        return response()->json([
            'message' => 'Estado de producción actualizado exitosamente',
            'data' => $ordenProduccion
        ]);
    }
}
