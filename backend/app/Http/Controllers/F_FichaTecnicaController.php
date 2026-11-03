<?php

namespace App\Http\Controllers;

use App\Models\FichaTecnica;
use App\Models\PteSku;
use App\Models\MpMateriaPrima;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class F_FichaTecnicaController extends Controller
{
    /**
     * Mostrar lista de fichas técnicas
     */
    public function index(Request $request): JsonResponse
    {
        $query = F_FichaTecnica::with(['sku', 'materiaPrima']);

        // Filtros
        if ($request->has('sku')) {
            $query->where('sku', 'like', '%' . $request->sku . '%');
        }

        if ($request->has('materia_prima_id')) {
            $query->where('materia_prima_id', $request->materia_prima_id);
        }

        // Paginación
        $perPage = $request->get('per_page', 15);
        $fichasTecnicas = $query->orderBy('sku')->paginate($perPage);

        return response()->json($fichasTecnicas);
    }

    /**
     * Crear nueva ficha técnica
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sku' => 'required|string|max:50|exists:pte_skus,sku',
            'materia_prima_id' => 'required|exists:mp_materias_primas,id',
            'cantidad_requerida' => 'required|numeric|min:0',
        ]);

        // Verificar si ya existe una ficha técnica para este SKU y materia prima
        $existente = F_FichaTecnica::where('sku', $validated['sku'])
            ->where('materia_prima_id', $validated['materia_prima_id'])
            ->first();

        if ($existente) {
            return response()->json([
                'message' => 'Ya existe una ficha técnica para este SKU y materia prima'
            ], 422);
        }

        $fichaTecnica = F_FichaTecnica::create($validated);
        $fichaTecnica->load(['sku', 'materiaPrima']);

        return response()->json([
            'message' => 'Ficha técnica creada exitosamente',
            'data' => $fichaTecnica
        ], 201);
    }

    /**
     * Mostrar ficha técnica específica
     */
    public function show(F_FichaTecnica $fichaTecnica): JsonResponse
    {
        $fichaTecnica->load(['sku', 'materiaPrima']);
        
        return response()->json($fichaTecnica);
    }

    /**
     * Actualizar ficha técnica
     */
    public function update(Request $request, F_FichaTecnica $fichaTecnica): JsonResponse
    {
        $validated = $request->validate([
            'sku' => 'sometimes|string|max:50|exists:pte_skus,sku',
            'materia_prima_id' => 'sometimes|exists:mp_materias_primas,id',
            'cantidad_requerida' => 'sometimes|numeric|min:0',
        ]);

        // Si se están actualizando SKU o materia prima, verificar duplicados
        if (isset($validated['sku']) || isset($validated['materia_prima_id'])) {
            $sku = $validated['sku'] ?? $fichaTecnica->sku;
            $materiaPrimaId = $validated['materia_prima_id'] ?? $fichaTecnica->materia_prima_id;

            $existente = F_FichaTecnica::where('sku', $sku)
                ->where('materia_prima_id', $materiaPrimaId)
                ->where('id', '!=', $fichaTecnica->id)
                ->first();

            if ($existente) {
                return response()->json([
                    'message' => 'Ya existe una ficha técnica para este SKU y materia prima'
                ], 422);
            }
        }

        $fichaTecnica->update($validated);
        $fichaTecnica->load(['sku', 'materiaPrima']);

        return response()->json([
            'message' => 'Ficha técnica actualizada exitosamente',
            'data' => $fichaTecnica
        ]);
    }

    /**
     * Eliminar ficha técnica
     */
    public function destroy(F_FichaTecnica $fichaTecnica): JsonResponse
    {
        $fichaTecnica->delete();

        return response()->json([
            'message' => 'Ficha técnica eliminada exitosamente'
        ]);
    }

    /**
     * Obtener ficha técnica por SKU
     */
    public function porSku(string $sku): JsonResponse
    {
        $fichasTecnicas = F_FichaTecnica::with(['materiaPrima'])
            ->where('sku', $sku)
            ->get();

        $totalMateriasPrimas = $fichasTecnicas->count();
        $cantidadTotalRequerida = $fichasTecnicas->sum('cantidad_requerida');

        return response()->json([
            'sku' => $sku,
            'total_materias_primas' => $totalMateriasPrimas,
            'cantidad_total_requerida' => $cantidadTotalRequerida,
            'fichas_tecnicas' => $fichasTecnicas
        ]);
    }

    /**
     * Obtener fichas técnicas por materia prima
     */
    public function porMateriaPrima(F_MpMateriaPrima $materiaPrima): JsonResponse
    {
        $fichasTecnicas = F_FichaTecnica::with(['sku'])
            ->where('materia_prima_id', $materiaPrima->id)
            ->get();

        return response()->json([
            'materia_prima' => $materiaPrima,
            'productos_que_usan' => $fichasTecnicas->count(),
            'fichas_tecnicas' => $fichasTecnicas
        ]);
    }

    /**
     * Calcular requerimientos para producción
     */
    public function calcularRequerimientos(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sku' => 'required|string|exists:pte_skus,sku',
            'cantidad_a_producir' => 'required|integer|min:1',
        ]);

        $fichasTecnicas = F_FichaTecnica::with(['materiaPrima'])
            ->where('sku', $validated['sku'])
            ->get();

        if ($fichasTecnicas->isEmpty()) {
            return response()->json([
                'message' => 'No se encontraron fichas técnicas para este SKU'
            ], 404);
        }

        $requerimientos = $fichasTecnicas->map(function ($ficha) use ($validated) {
            $cantidadRequerida = $ficha->cantidad_requerida * $validated['cantidad_a_producir'];
            
            return [
                'materia_prima' => $ficha->materiaPrima,
                'cantidad_unitaria' => $ficha->cantidad_requerida,
                'cantidad_total_requerida' => $cantidadRequerida,
                'unidad_medida' => $ficha->materiaPrima->unidad_medida
            ];
        });

        return response()->json([
            'sku' => $validated['sku'],
            'cantidad_a_producir' => $validated['cantidad_a_producir'],
            'requerimientos' => $requerimientos
        ]);
    }

    /**
     * Clonar ficha técnica a otro SKU
     */
    public function clonar(Request $request, F_FichaTecnica $fichaTecnica): JsonResponse
    {
        $validated = $request->validate([
            'nuevo_sku' => 'required|string|max:50|exists:pte_skus,sku',
        ]);

        // Verificar si ya existe
        $existente = F_FichaTecnica::where('sku', $validated['nuevo_sku'])
            ->where('materia_prima_id', $fichaTecnica->materia_prima_id)
            ->first();

        if ($existente) {
            return response()->json([
                'message' => 'Ya existe una ficha técnica para el nuevo SKU y esta materia prima'
            ], 422);
        }

        $nuevaFicha = F_FichaTecnica::create([
            'sku' => $validated['nuevo_sku'],
            'materia_prima_id' => $fichaTecnica->materia_prima_id,
            'cantidad_requerida' => $fichaTecnica->cantidad_requerida,
        ]);

        $nuevaFicha->load(['sku', 'materiaPrima']);

        return response()->json([
            'message' => 'Ficha técnica clonada exitosamente',
            'data' => $nuevaFicha
        ], 201);
    }
}
