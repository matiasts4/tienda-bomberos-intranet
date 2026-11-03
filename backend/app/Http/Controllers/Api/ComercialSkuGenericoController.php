<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PteSkuGenerico;
use Illuminate\Http\Request;

class ComercialSkuGenericoController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');

        // Ahora consulta pte_sku_genericos publicados con al menos una variante activa
        // Mapea sku_base → sku para mantener compatibilidad con el frontend
        $query = PteSkuGenerico::query()
            ->where('estado_catalogo', 'publicado')
            ->where('activo', true)
            ->where('permite_cotizar', true)
            ->where(fn ($q) => $q->where('modo_kit', true)->orWhereHas('variantes', fn ($v) => $v->where('activo', true)));

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('sku_base', 'like', "%{$search}%")
                    ->orWhere('nombre', 'like', "%{$search}%");
            });
        }

        $results = $query->orderBy('nombre', 'asc')->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'sku' => $item->sku_base,        // Compatibilidad con frontend
                'nombre' => $item->nombre,
                'imagen_url' => $item->imagen_url,
                'imagen_back_url' => $item->imagen_back_url,
                'imagen_side_url' => $item->imagen_side_url,
                'precio_venta' => $item->precio_venta,
                'permite_personalizar' => (bool) ($item->permite_personalizar ?? true),
                'permite_cotizar' => (bool) $item->permite_cotizar,
                'modo_kit' => (bool) $item->modo_kit,
            ];
        });

        return response()->json($results);
    }
}
