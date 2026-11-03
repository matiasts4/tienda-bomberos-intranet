<?php

namespace App\Http\Controllers;

use App\Models\MpEspecificacion;
use App\Models\MpMateriaPrima;
use App\Models\MpProveedor;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class F_MpEspecificacionController extends Controller
{
    /**
     * Mostrar lista de especificaciones
     */
    public function index(Request $request): JsonResponse
    {
        $query = F_MpEspecificacion::with(['materiaPrima', 'proveedor', 'stocks']);

        // Filtros
        if ($request->has('materia_prima_id')) {
            $query->where('materia_prima_id', $request->materia_prima_id);
        }

        if ($request->has('proveedor_id')) {
            $query->where('proveedor_id', $request->proveedor_id);
        }

        if ($request->has('lote_proveedor')) {
            $query->where('lote_proveedor', 'like', '%' . $request->lote_proveedor . '%');
        }

        // Paginación
        $perPage = $request->get('per_page', 15);
        $especificaciones = $query->orderBy('fecha_ingreso', 'desc')->paginate($perPage);

        return response()->json($especificaciones);
    }

    /**
     * Crear nueva especificación
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'materia_prima_id' => 'required|exists:mp_materias_primas,id',
            'proveedor_id' => 'required|exists:mp_proveedores,id',
            'fecha_ingreso' => 'nullable|date',
            'lote_proveedor' => 'nullable|string|max:80',
            'ancho_real' => 'nullable|numeric|min:0',
            'gramaje' => 'nullable|numeric|min:0',
        ]);

        $especificacion = F_MpEspecificacion::create($validated);
        $especificacion->load(['materiaPrima', 'proveedor']);

        return response()->json([
            'message' => 'Especificación creada exitosamente',
            'data' => $especificacion
        ], 201);
    }

    /**
     * Mostrar especificación específica
     */
    public function show(F_MpEspecificacion $mpEspecificacion): JsonResponse
    {
        $mpEspecificacion->load(['materiaPrima', 'proveedor', 'stocks']);
        
        return response()->json($mpEspecificacion);
    }

    /**
     * Actualizar especificación
     */
    public function update(Request $request, F_MpEspecificacion $mpEspecificacion): JsonResponse
    {
        $validated = $request->validate([
            'materia_prima_id' => 'sometimes|exists:mp_materias_primas,id',
            'proveedor_id' => 'sometimes|exists:mp_proveedores,id',
            'fecha_ingreso' => 'nullable|date',
            'lote_proveedor' => 'nullable|string|max:80',
            'ancho_real' => 'nullable|numeric|min:0',
            'gramaje' => 'nullable|numeric|min:0',
        ]);

        $mpEspecificacion->update($validated);
        $mpEspecificacion->load(['materiaPrima', 'proveedor']);

        return response()->json([
            'message' => 'Especificación actualizada exitosamente',
            'data' => $mpEspecificacion
        ]);
    }

    /**
     * Eliminar especificación
     */
    public function destroy(F_MpEspecificacion $mpEspecificacion): JsonResponse
    {
        // Verificar si tiene stocks asociados
        if ($mpEspecificacion->stocks()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar la especificación porque tiene stocks asociados'
            ], 422);
        }

        $mpEspecificacion->delete();

        return response()->json([
            'message' => 'Especificación eliminada exitosamente'
        ]);
    }

    /**
     * Obtener especificaciones por materia prima
     */
    public function porMateriaPrima(MpMateriaPrima $materiaPrima): JsonResponse
    {
        $especificaciones = F_MpEspecificacion::with(['proveedor', 'stocks'])
            ->where('materia_prima_id', $materiaPrima->id)
            ->get();

        return response()->json([
            'materia_prima' => $materiaPrima,
            'especificaciones' => $especificaciones
        ]);
    }

    /**
     * Obtener especificaciones por proveedor
     */
    public function porProveedor(MpProveedor $proveedor): JsonResponse
    {
        $especificaciones = F_MpEspecificacion::with(['materiaPrima', 'stocks'])
            ->where('proveedor_id', $proveedor->id)
            ->get();

        return response()->json([
            'proveedor' => $proveedor,
            'especificaciones' => $especificaciones
        ]);
    }

    /**
     * Obtener stock total por especificación
     */
    public function stockTotal(F_MpEspecificacion $mpEspecificacion): JsonResponse
    {
        $stockTotal = $mpEspecificacion->stocks()->sum('cantidad_actual');
        
        return response()->json([
            'especificacion' => $mpEspecificacion,
            'stock_total' => $stockTotal,
            'stocks_detalle' => $mpEspecificacion->stocks
        ]);
    }
}
