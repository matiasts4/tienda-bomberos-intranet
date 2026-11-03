<?php
namespace App\Services;

use Illuminate\Validation\ValidationException;

/** Cálculo determinista sin ORM: kits comerciales y prendas físicas son cantidades distintas. */
class KitPlan
{
    public static function fail(string $message): never
    {
        throw ValidationException::withMessages(['kit' => $message]);
    }

    public static function normalize(array $catalog, array $compositions, int $quantity, bool $complete): array
    {
        if (!$catalog) self::fail('Define los componentes del kit en el catálogo.');
        if (count($compositions) > 100) self::fail('Máximo 100 composiciones por ítem.');
        $allowed = array_column($catalog, null, 'sku_generico_id');
        $result = []; $ids = []; $total = 0;
        foreach ($compositions as $composition) {
            if (!is_array($composition)) self::fail('Composición inválida.');
            if (isset($composition['nombre']) && !is_string($composition['nombre'])) self::fail('El nombre de la composición debe ser texto.');
            $id = $composition['id'] ?? '';
            if (!is_string($id) || !preg_match('/^[a-zA-Z0-9_-]{1,80}$/', $id) || isset($ids[$id])) self::fail('Cada composición necesita una identidad única.');
            $ids[$id] = true;
            $count = $composition['cantidad'] ?? null;
            if (!is_int($count) || $count < 1 || $count > 100000) self::fail('La cantidad de kits debe ser un entero positivo.');
            $total += $count;
            $children = $composition['componentes'] ?? [];
            if (!is_array($children) || count($children) > 100) self::fail('Composición de componentes inválida.');
            $selected = []; $alternatives = []; $clean = [];
            foreach ($children as $child) {
                if (!is_array($child)) self::fail('Componente inválido.');
                $childId = $child['sku_generico_id'] ?? null;
                if (!is_int($childId) || !isset($allowed[$childId]) || isset($selected[$childId])) self::fail('Componente duplicado o ajeno al catálogo del kit.');
                $selected[$childId] = true;
                $definition = $allowed[$childId];
                if ($definition['regla'] === 'alternativa') {
                    $key = $definition['grupo_alternativa'];
                    if (isset($alternatives[$key])) self::fail('Selecciona solo una alternativa por grupo.');
                    $alternatives[$key] = true;
                }
                if (!is_array($child['grupos'] ?? null)) self::fail('Falta la configuración del componente.');
                $clean[] = ['sku_generico_id' => $childId, 'grupos' => $child['grupos']];
            }
            if ($complete) {
                if (!$children) self::fail('Cada composición necesita al menos un componente.');
                foreach ($catalog as $definition) {
                    if ($definition['regla'] === 'obligatorio' && !isset($selected[$definition['sku_generico_id']])) self::fail('Falta un componente obligatorio.');
                    if ($definition['regla'] === 'alternativa' && !isset($alternatives[$definition['grupo_alternativa']])) self::fail('Falta elegir una alternativa del kit.');
                }
            }
            $result[] = ['id' => $id, 'nombre' => mb_substr(trim((string) ($composition['nombre'] ?? 'Composición')), 0, 100), 'cantidad' => $count, 'componentes' => $clean];
        }
        if ($total > $quantity || ($complete && $total !== $quantity)) self::fail('Las composiciones deben sumar exactamente la cantidad de kits cotizada.');
        return ['kit_version' => 1, 'catalogo' => array_values($catalog), 'composiciones' => $result];
    }

    public static function children(array $plan): array
    {
        $catalog = array_column($plan['catalogo'], null, 'sku_generico_id');
        $rows = [];
        foreach ($plan['composiciones'] as $composition) foreach ($composition['componentes'] as $child) {
            $definition = $catalog[$child['sku_generico_id']];
            $rows[] = $definition + [
                'key' => $composition['id'].':'.$child['sku_generico_id'],
                'composicion_id' => $composition['id'], 'composicion_nombre' => $composition['nombre'],
                'cantidad' => $composition['cantidad'] * $definition['cantidad_por_kit'], 'grupos' => $child['grupos'],
            ];
        }
        return $rows;
    }
}
