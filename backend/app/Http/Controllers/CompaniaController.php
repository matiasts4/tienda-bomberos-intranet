<?php

namespace App\Http\Controllers;

use App\Models\Compania;
use App\Models\Cuerpo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompaniaController extends Controller
{
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
