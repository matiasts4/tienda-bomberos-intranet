<?php

namespace App\Http\Controllers;

use App\Models\MpMateriaPrima;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class F_MpMateriaPrimaController extends Controller
{
    /**
     * Mostrar lista de materias primas
     */
    public function index(Request $request): JsonResponse
    {
        $query = F_MpMateriaPrima::with(['especificaciones', 'stocks', 'fichasTecnicas']);

        // Filtros
        if ($request->has('tipo_material')) {
            $query->where('tipo_material', 'like', '%' . $request->tipo_material . '%');
        }

        if ($request->has('nombre')) {
            $query->where('nombre', 'like', '%' . $request->nombre . '%');
        }

        if ($request->has('unidad_medida')) {
            $query->where('unidad_medida', $request->unidad_medida);
        }

        if ($request->has('requiere_especificacion')) {
            $query->where('requiere_especificacion', $request->boolean('requiere_especificacion'));
        }

        // Paginación
        $perPage = $request->get('per_page', 15);
        $materiasPrimas = $query->orderBy('nombre')->paginate($perPage);

        return response()->json($materiasPrimas);
    }

    /**
     * Crear nueva materia prima
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tipo_material' => 'required|string|max:50',
            'nombre' => 'required|string|max:150',
            'unidad_medida' => 'required|string|max:30',
            'requiere_especificacion' => 'boolean',
            'ancho_estandar' => 'nullable|numeric|min:0',
        ]);

        $materiaPrima = F_MpMateriaPrima::create($validated);

        return response()->json([
            'message' => 'Materia prima creada exitosamente',
            'data' => $materiaPrima
        ], 201);
    }

    /**
     * Mostrar materia prima específica
     */
    public function show(F_MpMateriaPrima $mpMateriaPrima): JsonResponse
    {
        $mpMateriaPrima->load([
            'especificaciones.proveedor',
            'especificaciones.stocks',
            'fichasTecnicas.sku',
            'reparaciones.ordenProduccion'
        ]);
        
        return response()->json($mpMateriaPrima);
    }

    /**
     * Actualizar materia prima
     */
    public function update(Request $request, F_MpMateriaPrima $mpMateriaPrima): JsonResponse
    {
        $validated = $request->validate([
            'tipo_material' => 'sometimes|string|max:50',
            'nombre' => 'sometimes|string|max:150',
            'unidad_medida' => 'sometimes|string|max:30',
            'requiere_especificacion' => 'boolean',
            'ancho_estandar' => 'nullable|numeric|min:0',
        ]);

        $mpMateriaPrima->update($validated);

        return response()->json([
            'message' => 'Materia prima actualizada exitosamente',
            'data' => $mpMateriaPrima
        ]);
    }

    /**
     * Eliminar materia prima
     */
    public function destroy(F_MpMateriaPrima $mpMateriaPrima): JsonResponse
    {
        // Verificar si tiene dependencias
        if ($mpMateriaPrima->especificaciones()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar la materia prima porque tiene especificaciones asociadas'
            ], 422);
        }

        if ($mpMateriaPrima->fichasTecnicas()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar la materia prima porque está en fichas técnicas'
            ], 422);
        }

        $mpMateriaPrima->delete();

        return response()->json([
            'message' => 'Materia prima eliminada exitosamente'
        ]);
    }

    /**
     * Obtener materias primas por tipo
     */
    public function porTipo(string $tipo): JsonResponse
    {
        $materiasPrimas = F_MpMateriaPrima::where('tipo_material', 'like', '%' . $tipo . '%')
            ->orderBy('nombre')
            ->get();

        return response()->json($materiasPrimas);
    }

    /**
     * Obtener stock consolidado por materia prima
     */
    public function stockConsolidado(F_MpMateriaPrima $mpMateriaPrima): JsonResponse
    {
        $especificaciones = $mpMateriaPrima->especificaciones()->with(['stocks', 'proveedor'])->get();
        
        $stockTotal = 0;
        $especificacionesConStock = [];

        foreach ($especificaciones as $especificacion) {
            $stockEspecificacion = $especificacion->stocks->sum('cantidad_actual');
            $stockTotal += $stockEspecificacion;
            
            $especificacionesConStock[] = [
                'especificacion' => $especificacion,
                'stock_total' => $stockEspecificacion,
                'stocks_detalle' => $especificacion->stocks
            ];
        }

        return response()->json([
            'materia_prima' => $mpMateriaPrima,
            'stock_total_consolidado' => $stockTotal,
            'especificaciones' => $especificacionesConStock
        ]);
    }

    /**
     * Obtener materias primas que requieren especificación
     */
    public function queRequierenEspecificacion(): JsonResponse
    {
        $materiasPrimas = F_MpMateriaPrima::where('requiere_especificacion', true)
            ->orderBy('nombre')
            ->get();

        return response()->json($materiasPrimas);
    }

    /**
     * Búsqueda avanzada de materias primas
     */
    public function busqueda(Request $request): JsonResponse
    {
        $query = F_MpMateriaPrima::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                  ->orWhere('tipo_material', 'like', "%{$search}%");
            });
        }

        if ($request->has('unidades_medida')) {
            $query->whereIn('unidad_medida', $request->unidades_medida);
        }

        if ($request->has('con_stock')) {
            if ($request->boolean('con_stock')) {
                $query->whereHas('especificaciones.stocks', function ($q) {
                    $q->where('cantidad_actual', '>', 0);
                });
            }
        }

        $materiasPrimas = $query->orderBy('nombre')->get();

        return response()->json($materiasPrimas);
    }
}
