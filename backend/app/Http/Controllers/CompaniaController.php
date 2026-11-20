<?php

namespace App\Http\Controllers;

use App\Models\Compania;
use App\Models\Cuerpo;
use App\Services\InstitutionalOrderHistoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompaniaController extends Controller
{
    /**
     * GET /api/companies/{id}/orders
     *
     * Retorna el historial cronológico consolidado de cotizaciones aprobadas,
     * órdenes de taller y pedidos entregados para una compañía bomberil,
     * con filtros opcionales por año, estado de producción y tipo de prenda.
     */
    public function orders(Request $request, string $id, InstitutionalOrderHistoryService $historyService)
    {
        $compania = Compania::with('cuerpo')->find($id);

        if (! $compania) {
            return response()->json([
                'success' => false,
                'message' => 'Compañía no encontrada',
            ], 404);
        }

        $validated = $request->validate([
            'year' => 'nullable|integer|min:2000|max:2100',
            'anio' => 'nullable|integer|min:2000|max:2100',
            'status' => 'nullable|string|max:80',
            'stage' => 'nullable|string|max:80',
            'estado' => 'nullable|string|max:80',
            'estado_produccion' => 'nullable|string|max:80',
            'garment_type' => 'nullable|string|max:120',
            'tipo_prenda' => 'nullable|string|max:120',
            'product_type' => 'nullable|string|max:120',
        ]);

        $filters = [
            'year' => $validated['year'] ?? $validated['anio'] ?? null,
            'status' => $validated['status']
                ?? $validated['stage']
                ?? $validated['estado_produccion']
                ?? $validated['estado']
                ?? null,
            'garment_type' => $validated['garment_type']
                ?? $validated['tipo_prenda']
                ?? $validated['product_type']
                ?? null,
        ];

        return response()->json($historyService->forCompania($compania, $filters));
    }

    /**
     * GET /api/cuerpos/{cuerpo}/companias
     *
     * Compañías de un cuerpo con cuántos contactos tiene cada una, en una sola
     * consulta: la ficha de prospección lista las compañías hermanas sin tener
     * que pedir los contactos de cada una.
     */
    public function porCuerpo(Cuerpo $cuerpo)
    {
        $companias = $cuerpo->companias()
            ->withCount('contactos')
            ->orderBy('numero')
            ->orderBy('nombre')
            ->get(['id', 'cuerpo_id', 'nombre', 'numero']);

        return response()->json($companias);
    }

    public function index(Request $request)
    {
        $query = Compania::with(['cuerpo.region:id,nombre', 'cuerpo.comuna:id,nombre,region_id']);

        if ($request->filled('cuerpo_id')) {
            $query->where('cuerpo_id', $request->query('cuerpo_id'));
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where('nombre', 'like', "%{$search}%");
        }

        return response()->json($query->orderBy('numero')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'cuerpo_id' => 'required|exists:cuerpos,id',
            'nombre' => 'required|string|max:255',
            'numero' => 'required|integer',
            // Opcional a propósito: se puede crear una compañía sin conocer su
            // fecha de fundación y completarla después.
            'fecha_fundacion' => 'nullable|date',
        ]);

        // Normalizamos "" a null para que un input date vacío no reviente el insert.
        if (($data['fecha_fundacion'] ?? null) === '') {
            $data['fecha_fundacion'] = null;
        }

        return DB::transaction(function () use ($data) {
            $cuerpo = Cuerpo::findOrFail($data['cuerpo_id']);

            $cuerpoPrefix = substr($cuerpo->id, 0, 5);
            $companiaNumStr = str_pad($data['numero'], 2, '0', STR_PAD_LEFT);
            $companiaId = $cuerpoPrefix . $companiaNumStr;

            if (Compania::find($companiaId)) {
                return response()->json(['error' => 'Ya existe una compañía con este número en este cuerpo.'], 422);
            }

            $data['id'] = $companiaId;
            $compania = Compania::create($data);

            return response()->json($compania->load('cuerpo'), 201);
        });
    }

    public function show($id)
    {
        return response()->json(Compania::with('cuerpo.region', 'cuerpo.comuna')->findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $compania = Compania::findOrFail($id);

        $data = $request->validate([
            'nombre' => 'sometimes|required|string|max:255',
            'numero' => 'sometimes|required|integer',
            'fecha_fundacion' => 'sometimes|nullable|date',
        ]);

        return DB::transaction(function () use ($compania, $data) {
            $updateData = [];
            if (isset($data['nombre']))
                $updateData['nombre'] = $data['nombre'];
            if (isset($data['numero']))
                $updateData['numero'] = $data['numero'];
            if (array_key_exists('fecha_fundacion', $data))
                $updateData['fecha_fundacion'] = $data['fecha_fundacion'] ?: null;
            if (!empty($updateData)) {
                $compania->update($updateData);
            }

            return response()->json($compania->fresh()->load('cuerpo'));
        });
    }

    public function destroy($id)
    {
        $compania = Compania::findOrFail($id);
        $compania->delete();

        return response()->noContent();
    }
}
