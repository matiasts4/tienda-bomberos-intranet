<?php

namespace App\Http\Controllers;

use App\Models\MpMaterial;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MpMaterialController extends Controller
{
    /**
     * Lista de materiales con su stock consolidado y relaciones.
     */
    public function index(Request $request): JsonResponse
    {
        $query = MpMaterial::with(['tipo', 'unidad', 'ancho', 'color']);

        // Filtros básicos
        if ($request->has('tipo_id')) {
            $query->where('tipo_id', $request->tipo_id);
        }

        if ($request->has('search')) {
            $query->where('nombre_base', 'like', '%' . $request->search . '%')
                  ->orWhere('codigo_interno', 'like', '%' . $request->search . '%');
        }

        $materiales = $query->get()->map(function ($material) {
            return [
                'id' => $material->id,
                'nombre' => $material->nombre_base,
                'sku' => $material->codigo_interno,
                'tipo' => $material->tipo->nombre,
                'color' => $material->color->nombre,
                'unidad' => $material->unidad->abreviacion,
                'ancho' => $material->ancho->medida ?? 'N/A',
                // Usamos el atributo virtual que definimos en el Modelo
                'stock_total' => $material->stock_total, 
                'stock_minimo' => $material->stock_minimo,
                'alerta_stock' => $material->stock_total <= $material->stock_minimo
            ];
        });

        return response()->json($materiales);
    }

    /**
     * Detalle de un material específico y sus rollos (lotes) disponibles.
     */
    public function show($id): JsonResponse
    {
        $material = MpMaterial::with(['tipo', 'unidad', 'ancho', 'color', 'lotes' => function($q) {
            $q->where('cantidad_actual', '>', 0)
              ->orderBy('fecha_ingreso', 'asc'); // PEPS: Primero en entrar, primero en salir
        }])->findOrFail($id);

        return response()->json($material);
    }
}