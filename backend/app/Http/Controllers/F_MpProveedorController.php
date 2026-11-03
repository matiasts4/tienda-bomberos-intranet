<?php

namespace App\Http\Controllers;

use App\Models\MpProveedor;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class F_MpProveedorController extends Controller
{
    /**
     * Mostrar lista de proveedores
     */
    public function index(Request $request): JsonResponse
    {
        $query = F_MpProveedor::with(['especificaciones']);

        // Filtros
        if ($request->has('nombre_empresa')) {
            $query->where('nombre_empresa', 'like', '%' . $request->nombre_empresa . '%');
        }

        if ($request->has('rut_empresa')) {
            $query->where('rut_empresa', 'like', '%' . $request->rut_empresa . '%');
        }

        // Paginación
        $perPage = $request->get('per_page', 15);
        $proveedores = $query->orderBy('nombre_empresa')->paginate($perPage);

        return response()->json($proveedores);
    }

    /**
     * Crear nuevo proveedor
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nombre_empresa' => 'required|string|max:150',
            'rut_empresa' => 'nullable|string|max:20|unique:mp_proveedores,rut_empresa',
            'fono' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:150',
        ]);

        $proveedor = F_MpProveedor::create($validated);

        return response()->json([
            'message' => 'Proveedor creado exitosamente',
            'data' => $proveedor
        ], 201);
    }

    /**
     * Mostrar proveedor específico
     */
    public function show(F_MpProveedor $mpProveedor): JsonResponse
    {
        $mpProveedor->load(['especificaciones.materiaPrima', 'especificaciones.stocks']);
        
        return response()->json($mpProveedor);
    }

    /**
     * Actualizar proveedor
     */
    public function update(Request $request, F_MpProveedor $mpProveedor): JsonResponse
    {
        $validated = $request->validate([
            'nombre_empresa' => 'sometimes|string|max:150',
            'rut_empresa' => 'nullable|string|max:20|unique:mp_proveedores,rut_empresa,' . $mpProveedor->id,
            'fono' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:150',
        ]);

        $mpProveedor->update($validated);

        return response()->json([
            'message' => 'Proveedor actualizado exitosamente',
            'data' => $mpProveedor
        ]);
    }

    /**
     * Eliminar proveedor
     */
    public function destroy(F_MpProveedor $mpProveedor): JsonResponse
    {
        // Verificar si tiene especificaciones asociadas
        if ($mpProveedor->especificaciones()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar el proveedor porque tiene especificaciones de materias primas asociadas'
            ], 422);
        }

        $mpProveedor->delete();

        return response()->json([
            'message' => 'Proveedor eliminado exitosamente'
        ]);
    }

    /**
     * Buscar proveedores
     */
    public function buscar(Request $request): JsonResponse
    {
        $query = F_MpProveedor::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nombre_empresa', 'like', "%{$search}%")
                  ->orWhere('rut_empresa', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $proveedores = $query->orderBy('nombre_empresa')->get();

        return response()->json($proveedores);
    }

    /**
     * Obtener materias primas por proveedor
     */
    public function materiasPrimas(F_MpProveedor $mpProveedor): JsonResponse
    {
        $especificaciones = $mpProveedor->especificaciones()
            ->with(['materiaPrima', 'stocks'])
            ->get();

        $materiasPrimas = $especificaciones->map(function ($especificacion) {
            return [
                'materia_prima' => $especificacion->materiaPrima,
                'especificacion' => $especificacion,
                'stock_total' => $especificacion->stocks->sum('cantidad_actual')
            ];
        });

        return response()->json([
            'proveedor' => $mpProveedor,
            'materias_primas' => $materiasPrimas
        ]);
    }
}
