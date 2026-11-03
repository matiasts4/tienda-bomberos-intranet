<?php

namespace App\Http\Controllers;

use App\Models\Comuna;
use App\Models\Provincia;
use App\Models\Region;
use App\Models\Zona;
use Illuminate\Http\Request;

class GeografiaController extends Controller
{
    public function zonas()
    {
        return response()->json(Zona::all());
    }

    public function regiones(Request $request)
    {
        $query = Region::query();

        if ($request->filled('zona_id')) {
            $query->where('zona_id', $request->query('zona_id'));
        }

        return response()->json($query->orderBy('nombre')->get());
    }

    public function provincias(Request $request)
    {
        $query = Provincia::query();

        if ($request->filled('region_id')) {
            $query->where('region_id', $request->query('region_id'));
        }

        return response()->json($query->orderBy('nombre')->get());
    }

    /**
     * Catálogo completo de comunas de Chile (346), filtrable por región o provincia.
     */
    public function comunas(Request $request)
    {
        $query = Comuna::query()->select(['id', 'nombre', 'region_id', 'provincia_id']);

        if ($request->filled('region_id')) {
            $query->where('region_id', $request->query('region_id'));
        }

        if ($request->filled('provincia_id')) {
            $query->where('provincia_id', $request->query('provincia_id'));
        }

        if ($request->filled('search')) {
            $query->where('nombre', 'like', '%' . $request->query('search') . '%');
        }

        return response()->json($query->orderBy('nombre')->get());
    }
}
