<?php

namespace App\Http\Controllers;

use App\Models\MpStock;
use App\Models\MpEspecificacion;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class F_MpStockController extends Controller
{
    /**
     * Mostrar lista de stocks
     */
    public function index(Request $request): JsonResponse
    {
        $query = F_MpStock::with(['especificacion.materiaPrima', 'especificacion.proveedor']);

        // Filtros
        if ($request->has('especificacion_id')) {
            $query->where('especificacion_id', $request->especificacion_id);
        }

        if ($request->has('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->has('con_stock')) {
            if ($request->boolean('con_stock')) {
                $query->where('cantidad_actual', '>', 0);
            } else {
                $query->where('cantidad_actual', '=', 0);
            }
        }

        // Paginación
        $perPage = $request->get('per_page', 15);
        $stocks = $query->orderBy('cantidad_actual', 'desc')->paginate($perPage);

        return response()->json($stocks);
    }

    /**
     * Crear nuevo stock
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'especificacion_id' => 'required|exists:mp_especificaciones,id',
            'cantidad_actual' => 'required|numeric|min:0',
            'estado' => 'nullable|string|max:30',
        ]);

        $stock = F_MpStock::create($validated);
        $stock->load(['especificacion.materiaPrima', 'especificacion.proveedor']);

        return response()->json([
            'message' => 'Stock creado exitosamente',
            'data' => $stock
        ], 201);
    }

    /**
     * Mostrar stock específico
     */
    public function show(F_MpStock $mpStock): JsonResponse
    {
        $mpStock->load(['especificacion.materiaPrima', 'especificacion.proveedor']);
        
        return response()->json($mpStock);
    }

    /**
     * Actualizar stock
     */
    public function update(Request $request, F_MpStock $mpStock): JsonResponse
    {
        $validated = $request->validate([
            'especificacion_id' => 'sometimes|exists:mp_especificaciones,id',
            'cantidad_actual' => 'sometimes|numeric|min:0',
            'estado' => 'nullable|string|max:30',
        ]);

        $mpStock->update($validated);
        $mpStock->load(['especificacion.materiaPrima', 'especificacion.proveedor']);

        return response()->json([
            'message' => 'Stock actualizado exitosamente',
            'data' => $mpStock
        ]);
    }

    /**
     * Eliminar stock
     */
    public function destroy(F_MpStock $mpStock): JsonResponse
    {
        $mpStock->delete();

        return response()->json([
            'message' => 'Stock eliminado exitosamente'
        ]);
    }

    /**
     * Ajustar cantidad de stock
     */
    public function ajustarCantidad(Request $request, F_MpStock $mpStock): JsonResponse
    {
        $validated = $request->validate([
            'nueva_cantidad' => 'required|numeric|min:0',
            'motivo' => 'nullable|string|max:255',
        ]);

        $cantidadAnterior = $mpStock->cantidad_actual;
        $mpStock->update(['cantidad_actual' => $validated['nueva_cantidad']]);

        return response()->json([
            'message' => 'Cantidad de stock ajustada exitosamente',
            'data' => [
                'stock' => $mpStock,
                'cantidad_anterior' => $cantidadAnterior,
                'cantidad_nueva' => $validated['nueva_cantidad'],
                'diferencia' => $validated['nueva_cantidad'] - $cantidadAnterior,
                'motivo' => $validated['motivo'] ?? null
            ]
        ]);
    }

    /**
     * Consumir stock para producción
     */
    public function consumir(Request $request, F_MpStock $mpStock): JsonResponse
    {
        $validated = $request->validate([
            'cantidad_consumir' => 'required|numeric|min:0.001',
            'orden_produccion_id' => 'nullable|exists:ordenes_produccion,id',
        ]);

        if ($validated['cantidad_consumir'] > $mpStock->cantidad_actual) {
            return response()->json([
                'message' => 'No hay suficiente stock disponible',
                'stock_disponible' => $mpStock->cantidad_actual,
                'cantidad_solicitada' => $validated['cantidad_consumir']
            ], 422);
        }

        $cantidadAnterior = $mpStock->cantidad_actual;
        $nuevaCantidad = $cantidadAnterior - $validated['cantidad_consumir'];
        
        $mpStock->update(['cantidad_actual' => $nuevaCantidad]);

        return response()->json([
            'message' => 'Stock consumido exitosamente',
            'data' => [
                'stock' => $mpStock,
                'cantidad_anterior' => $cantidadAnterior,
                'cantidad_consumida' => $validated['cantidad_consumir'],
                'cantidad_restante' => $nuevaCantidad,
                'orden_produccion_id' => $validated['orden_produccion_id'] ?? null
            ]
        ]);
    }

    /**
     * Obtener stocks por especificación
     */
    public function porEspecificacion(F_MpEspecificacion $especificacion): JsonResponse
    {
        $stocks = F_MpStock::where('especificacion_id', $especificacion->id)->get();
        $stockTotal = $stocks->sum('cantidad_actual');

        return response()->json([
            'especificacion' => $especificacion,
            'stock_total' => $stockTotal,
            'stocks' => $stocks
        ]);
    }

    /**
     * Reporte de stocks bajos
     */
    public function stocksBajos(Request $request): JsonResponse
    {
        $limite = $request->get('limite', 10); // Cantidad mínima por defecto
        
        $stocks = F_MpStock::with(['especificacion.materiaPrima', 'especificacion.proveedor'])
            ->where('cantidad_actual', '<=', $limite)
            ->where('cantidad_actual', '>', 0)
            ->orderBy('cantidad_actual', 'asc')
            ->get();

        return response()->json([
            'limite_configurado' => $limite,
            'total_stocks_bajos' => $stocks->count(),
            'stocks_bajos' => $stocks
        ]);
    }

    /**
     * Reporte de stocks sin existencias
     */
    public function stocksAgotados(): JsonResponse
    {
        $stocks = F_MpStock::with(['especificacion.materiaPrima', 'especificacion.proveedor'])
            ->where('cantidad_actual', '=', 0)
            ->get();

        return response()->json([
            'total_stocks_agotados' => $stocks->count(),
            'stocks_agotados' => $stocks
        ]);
    }

    /**
     * Consolidado de stock por materia prima
     */
    public function consolidadoPorMateriaPrima(): JsonResponse
    {
        $consolidado = F_MpStock::join('mp_especificaciones', 'mp_stocks.especificacion_id', '=', 'mp_especificaciones.id')
            ->join('mp_materias_primas', 'mp_especificaciones.materia_prima_id', '=', 'mp_materias_primas.id')
            ->selectRaw('
                mp_materias_primas.id,
                mp_materias_primas.nombre,
                mp_materias_primas.tipo_material,
                mp_materias_primas.unidad_medida,
                SUM(mp_stocks.cantidad_actual) as stock_total,
                COUNT(mp_stocks.id) as cantidad_stocks
            ')
            ->groupBy('mp_materias_primas.id', 'mp_materias_primas.nombre', 'mp_materias_primas.tipo_material', 'mp_materias_primas.unidad_medida')
            ->having('stock_total', '>', 0)
            ->orderBy('stock_total', 'desc')
            ->get();

        return response()->json($consolidado);
    }
}
