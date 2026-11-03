<?php
namespace App\Services;

use App\Models\{DetalleCotizacion, PteSku, PteSkuGenerico};
use Illuminate\Support\Facades\Validator;

class KitPersonalization
{
    /** Conserva IDs y snapshots cuando el editor comercial vuelve a enviar todas las líneas. */
    public static function commercialDetails(array $incoming, $existing): array
    {
        $byNumber = [];
        foreach ($existing as $detail) $byNumber[(int) $detail->n_item] = $detail;
        if (count($incoming) !== count($byNumber)) KitPlan::fail('Esta cotización tiene composiciones guardadas. No se pueden agregar o quitar líneas desde el editor comercial.');
        $matched = [];
        foreach ($incoming as $index => $row) {
            $number = (int) ($row['n_item'] ?? ($index + 1));
            $detail = $byNumber[$number] ?? null;
            if (!$detail || isset($matched[$number]) || (string) ($row['sku'] ?? '') !== (string) $detail->sku || (int) ($row['cantidad'] ?? 0) !== (int) $detail->cantidad) {
                KitPlan::fail('Esta cotización tiene composiciones guardadas. Conserva sus SKU, cantidades y números de ítem para no alterar las prendas configuradas.');
            }
            $matched[$number] = $detail;
        }
        return $matched;
    }

    public static function isKit(DetalleCotizacion $item): bool
    {
        if (($item->configuracion['kit_version'] ?? null) === 1) return true;
        // Configuraciones anteriores siguen siendo prendas individuales.
        if (!empty($item->configuracion)) return false;
        return (bool) (QuotePersonalization::generic($item)?->modo_kit ?? false);
    }

    public static function validateCatalog(array $definitions, ?int $parentId): array
    {
        Validator::make(['componentes' => $definitions], [
            'componentes' => 'array|max:100',
            'componentes.*.sku_generico_id' => 'required|integer|distinct',
            'componentes.*.cantidad_por_kit' => 'required|integer|min:1|max:100',
            'componentes.*.regla' => 'required|in:obligatorio,opcional,alternativa',
            'componentes.*.grupo_alternativa' => 'nullable|string|max:80',
        ])->validate();
        $children = PteSkuGenerico::whereIn('id', array_column($definitions, 'sku_generico_id'))->with('variantes')->get()->keyBy('id');
        $clean = [];
        foreach ($definitions as $definition) {
            $child = $children->get($definition['sku_generico_id']);
            if (!$child || $child->id === $parentId || $child->modo_kit || $child->es_informativo_cotizacion || !$child->activo || $child->estado_catalogo !== 'publicado') KitPlan::fail('Elige productos hijos publicados y activos, sin kits anidados ni ítems informativos.');
            if (!$child->variantes->contains(fn ($variant) => $variant->activo)) KitPlan::fail('Cada componente necesita al menos una variante activa.');
            $group = trim($definition['grupo_alternativa'] ?? '');
            if ($definition['regla'] === 'alternativa' && $group === '') KitPlan::fail('Las alternativas necesitan un nombre de grupo.');
            $clean[] = ['sku_generico_id' => (int) $child->id, 'cantidad_por_kit' => (int) $definition['cantidad_por_kit'], 'regla' => $definition['regla'], 'grupo_alternativa' => $definition['regla'] === 'alternativa' ? $group : ''];
        }
        return $clean;
    }

    public static function catalog(DetalleCotizacion $item): array
    {
        if (($item->configuracion['kit_version'] ?? null) === 1) return $item->configuracion['catalogo'];
        $generic = QuotePersonalization::generic($item);
        $definitions = $generic?->componentes_kit ?? [];
        $children = PteSkuGenerico::whereIn('id', array_column($definitions, 'sku_generico_id'))->with(['genero', 'variantes.color', 'variantes.talla'])->get()->keyBy('id');
        return array_map(function ($definition) use ($children) {
            $child = $children->get($definition['sku_generico_id']);
            if (!$child) KitPlan::fail('Un componente del kit no existe en catálogo.');
            $variants = $child->variantes->filter(fn ($v) => $v->activo);
            return $definition + ['sku_base' => $child->sku_base, 'nombre' => $child->nombre,
                'permite_personalizar' => (bool) ($child->permite_personalizar ?? true),
                'disponible' => (bool) $child->activo && !$child->modo_kit && $child->estado_catalogo === 'publicado',
                'variantes' => $variants->map(fn ($v) => ['sku' => $v->sku, 'color' => $v->color?->nombre, 'talla' => $v->talla?->nombre, 'genero' => $child->genero?->nombre ?? 'Unisex'])->values()->all(),
            ];
        }, $definitions);
    }

    public static function normalize(DetalleCotizacion $item, array $input, bool $complete): array
    {
        $compositions = $input['composiciones'] ?? [];
        if (!is_array($compositions)) KitPlan::fail('Las composiciones deben ser una lista.');
        $plan = KitPlan::normalize(self::catalog($item), $compositions, (int) $item->cantidad, $complete);
        foreach (self::operationalItems($item, $plan) as $child) {
            if ($complete && !($child->kit_definition['disponible'] ?? true)) KitPlan::fail('El componente elegido ya no está disponible. Revisa la composición.');
            QuotePersonalization::validateConfig($child, $child->configuracion, $complete);
            $total = 0;
            foreach ($child->configuracion as $group) foreach ($group['tallas'] ?? [] as $size) {
                $total += (int) $size['cantidad'];
                if ($complete && !self::variant($child->kit_definition, $size)) KitPlan::fail($child->producto.': elige una variante real de talla/color del catálogo.');
            }
            if ($complete && $total !== (int) $child->cantidad) KitPlan::fail($child->producto.': las tallas deben sumar la cantidad de prendas del componente, incluso en entrega directa.');
        }
        return $plan;
    }

    public static function operationalItems(DetalleCotizacion $parent, ?array $plan = null): array
    {
        if ($plan === null && !self::isKit($parent)) return [$parent];
        $plan ??= $parent->configuracion;
        if (($plan['kit_version'] ?? null) !== 1) KitPlan::fail('Configura la composición del kit antes de continuar.');
        return array_map(function ($row) use ($parent) {
            $child = new DetalleCotizacion();
            $child->forceFill(['id' => $parent->id, 'cotizacion_id' => $parent->cotizacion_id, 'n_item' => $parent->n_item,
                'sku' => $row['sku_base'], 'producto' => $row['nombre'], 'cantidad' => $row['cantidad'],
                'configuracion' => $row['grupos'], 'tipo_personalizacion' => 'none',
                'kit_key' => $row['key'], 'kit_definition' => $row]);
            $child->setRelation('personalizationGeneric', new PteSkuGenerico(['permite_personalizar' => $row['permite_personalizar'], 'es_informativo_cotizacion' => false]));
            return $child;
        }, KitPlan::children($plan));
    }

    public static function variant(array $definition, array $size): ?string
    {
        $matches = array_filter($definition['variantes'], fn ($v) => $v['talla'] === ($size['talla'] ?? null) && $v['color'] === ($size['color'] ?? null) && $v['genero'] === ($size['genero'] ?? 'Unisex'));
        return count($matches) === 1 ? array_values($matches)[0]['sku'] : null;
    }

    public static function manifest(DetalleCotizacion $parent): array
    {
        if (($parent->configuracion['kit_version'] ?? null) !== 1) return [];
        $rows = [];
        foreach (KitPlan::children($parent->configuracion) as $child) foreach ($child['grupos'] as $group) foreach ($group['tallas'] ?? [] as $size) {
            $rows[] = ['item_id' => $parent->id, 'numero_item' => $parent->n_item, 'kit' => $parent->producto, 'composicion' => $child['composicion_nombre'],
                'sku' => self::variant($child, $size), 'sku_base' => $child['sku_base'], 'producto' => $child['nombre'],
                'talla' => $size['talla'], 'color' => $size['color'] ?? null, 'cantidad' => $size['cantidad']];
        }
        return $rows;
    }
}
