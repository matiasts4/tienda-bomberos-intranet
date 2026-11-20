<?php

namespace App\Http\Controllers;

use App\Models\Compania;
use App\Models\Cuerpo;
use App\Rules\RutChileno;
use App\Services\InstitutionalOrderHistoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CuerpoController extends Controller
{
    /**
     * GET /api/fire-departments/{id}/orders
     *
     * Retorna el historial institucional unificado para un Cuerpo de Bomberos
     * (y todas sus compañías) o, si el identificador corresponde a una compañía,
     * el historial de dicha compañía.
     */
    public function orders(Request $request, string $id, InstitutionalOrderHistoryService $historyService)
    {
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

        $cuerpo = Cuerpo::find($id);
        if ($cuerpo) {
            return response()->json($historyService->forCuerpo($cuerpo, $filters));
        }

        $compania = Compania::find($id);
        if ($compania) {
            return response()->json($historyService->forCompania($compania, $filters));
        }

        return response()->json([
            'success' => false,
            'message' => 'Cuerpo o compañía no encontrada',
        ], 404);
    }

    public function index(Request $request)
    {
        // OJO: el modal de "Editar cliente" se alimenta de este listado, así que
        // tiene que traer TODOS los datos editables del cuerpo (rut, dirección,
        // fecha de fundación, giro, comuna...). Recortar el select acá deja el
        // formulario de edición en blanco.
        $query = Cuerpo::select([
            'id',
            'region_id',
            'comuna_id',
            'nombre',
            'numero_socio',
            'rut',
            'fecha_fundacion',
            'direccion',
            'giro',
            'telefono',
            'numero_companias',
            'logo_url',
            'ejecutivo_id',
        ])->with([
            'companias:id,cuerpo_id,nombre,numero,fecha_fundacion',
            'ejecutivo:id,name,codigo_vendedor',
            'region:id,nombre',
            'comuna:id,nombre,region_id',
        ]);

        if ($request->filled('region_id')) {
            $query->where('region_id', $request->query('region_id'));
        }
        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where('nombre', 'like', "%{$search}%");
        }

        return response()->json($query->orderBy('nombre')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'region_id' => 'required|exists:regiones,id',
            'nombre' => 'required|string|max:255',
            'rut' => ['nullable', 'string', 'max:20', new RutChileno()],
            'fecha_fundacion' => 'nullable|date',
            'direccion' => 'nullable|string|max:255',
            'giro' => 'nullable|string|max:255',
            'comuna_id' => 'nullable|exists:comunas,id',
            'telefono' => 'nullable|string|max:255',
            'numero_companias' => 'nullable|integer',
            'numero_socio' => 'nullable|integer',
            'ejecutivo_id' => 'nullable|exists:users,id',
        ]);

        return DB::transaction(function () use ($data) {
            $regionId = $data['region_id'];

            $logicRegionPrefix = $regionId;

            if (empty($data['numero_socio'])) {
                $maxSocio = Cuerpo::where('region_id', $regionId)->max('numero_socio') ?? 0;
                $data['numero_socio'] = $maxSocio + 1;
            }

            $numeroSocio = $data['numero_socio'];

            $cuerpoId = str_pad($logicRegionPrefix, 2, '0', STR_PAD_LEFT) .
                str_pad($numeroSocio, 3, '0', STR_PAD_LEFT) .
                '00';

            $data['id'] = $cuerpoId;
            $cuerpo = Cuerpo::create($data);

            return response()->json($cuerpo->load('region'), 201);
        });
    }

    public function show($id)
    {
        return response()->json(Cuerpo::with('region', 'comuna', 'companias')->findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $cuerpo = Cuerpo::findOrFail($id);

        $data = $request->validate([
            'nombre' => 'sometimes|required|string|max:255',
            'rut' => ['nullable', 'string', 'max:20', new RutChileno()],
            'fecha_fundacion' => 'nullable|date',
            'direccion' => 'nullable|string|max:255',
            'giro' => 'nullable|string|max:255',
            'comuna_id' => 'nullable|exists:comunas,id',
            'telefono' => 'nullable|string|max:255',
            'numero_companias' => 'nullable|integer',
            // Cartera: ejecutivo a cargo (null la deja sin asignar)
            'ejecutivo_id' => 'nullable|exists:users,id',
        ]);

        return DB::transaction(function () use ($cuerpo, $data) {
            $cuerpo->update($data);

            return response()->json($cuerpo->fresh()->load(['region', 'comuna', 'ejecutivo:id,name,codigo_vendedor']));
        });
    }

    public function destroy($id)
    {
        $cuerpo = Cuerpo::findOrFail($id);
        $cuerpo->delete();

        return response()->noContent();
    }

    public function uploadLogo(Request $request, Cuerpo $cuerpo)
    {
        $request->validate([
            'image' => ['required', 'image', 'max:5120'],
        ]);

        try {
            $file = $request->file('image');
            $path = $file->store('cuerpos/logos_' . $cuerpo->id, 'public');
            $url = \Illuminate\Support\Facades\Storage::url($path);

            $cuerpo->update(['logo_url' => $url]);

            return response()->json([
                'url' => $url,
                'message' => 'Logo actualizado correctamente',
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al subir el logo'], 500);
        }
    }
}
