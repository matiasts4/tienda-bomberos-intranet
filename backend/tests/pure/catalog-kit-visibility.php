<?php
// Ejecuta el index real con consultas en memoria. No carga Laravel, PDO ni una BD.
namespace Illuminate\Http {
    class Request {
        public function __construct(private array $values = []) {}
        public function input($key, $default = null) { return $this->values[$key] ?? $default; }
    }
}
namespace App\Http\Controllers { class Controller {} }
namespace App\Services { class ProductCatalogService {} }
namespace App\Models {
    class PteSkuGenerico {
        public static array $rows = [];
        public static function query() { return new MemoryCatalogQuery(); }
    }
    class MemoryCatalogQuery {
        private array $conditions = [];
        public function select($fields) { return $this; }
        public function orderBy($field, $direction) { return $this; }
        private function add($boolean, $field, $operator = null, $value = null) {
            if ($field instanceof \Closure) {
                $nested = new self(); $field($nested);
                $predicate = fn ($row) => $nested->matches($row);
            } else {
                if ($value === null) { $value = $operator; $operator = '='; }
                $predicate = static function ($row) use ($field, $operator, $value) {
                    $actual = $row[$field] ?? null;
                    if ($operator === '=') return $actual !== null && $actual == $value;
                    if ($actual === null) return false;
                    $pattern = '/^'.str_replace(['%', '_'], ['.*', '.'], preg_quote((string) $value, '/')).'$/iu';
                    return $operator === 'like' ? (bool) preg_match($pattern, $actual) : !preg_match($pattern, $actual);
                };
            }
            $this->conditions[] = [$boolean, $predicate];
            return $this;
        }
        public function where($field, $operator = null, $value = null) { return $this->add('and', $field, $operator, $value); }
        public function orWhere($field, $operator = null, $value = null) { return $this->add('or', $field, $operator, $value); }
        public function orWhereNull($field) { $this->conditions[] = ['or', fn ($row) => ($row[$field] ?? null) === null]; return $this; }
        public function whereNotIn($field, $values) { $this->conditions[] = ['and', fn ($row) => !in_array($row[$field], $values, true)]; return $this; }
        public function matches($row) {
            $result = null;
            foreach ($this->conditions as [$boolean, $predicate]) {
                $match = $predicate($row);
                $result = $result === null ? $match : ($boolean === 'or' ? $result || $match : $result && $match);
            }
            return $result ?? true;
        }
        public function paginate($perPage) { return ['data' => array_values(array_filter(PteSkuGenerico::$rows, fn ($row) => $this->matches($row))), 'per_page' => $perPage]; }
    }
}
namespace {
    function response() { return new class { public function json($data) { return $data; } }; }
    function check($condition, $message) { if (!$condition) throw new \RuntimeException($message); }
    require __DIR__.'/../../app/Http/Controllers/Api/SkuGenericoAdminController.php';
    $base = ['id' => 96, 'nombre' => 'Kit Cuartel', 'sku_base' => '111600600000000', 'modo_kit' => false,
        'es_informativo_cotizacion' => false, 'estado_catalogo' => 'publicado', 'activo' => true, 'permite_cotizar' => true];
    \App\Models\PteSkuGenerico::$rows = [
        $base,
        array_replace($base, ['id' => 97, 'nombre' => 'Kit Cuartel - 3 prendas', 'modo_kit' => true]),
        array_replace($base, ['id' => 98, 'nombre' => 'Kit Cuartel borrador', 'estado_catalogo' => 'borrador', 'activo' => false]),
        array_replace($base, ['id' => 99, 'nombre' => 'Kit Cuartel archivado', 'estado_catalogo' => 'archivado', 'activo' => false, 'permite_cotizar' => false]),
        array_replace($base, ['id' => 100, 'nombre' => 'Polera Piqué', 'es_informativo_cotizacion' => null]),
        array_replace($base, ['id' => 101, 'nombre' => 'NOMBRE', 'es_informativo_cotizacion' => true]),
        array_replace($base, ['id' => 102, 'nombre' => 'LOGO']),
        array_replace($base, ['id' => 103, 'nombre' => 'PARCHE 2.5 X 10']),
        array_replace($base, ['id' => 104, 'nombre' => 'Kit Cuartel informativo', 'es_informativo_cotizacion' => true]),
    ];
    $before = \App\Models\PteSkuGenerico::$rows;
    $controller = new \App\Http\Controllers\Api\SkuGenericoAdminController(new \App\Services\ProductCatalogService());
    $all = $controller->index(new \Illuminate\Http\Request());
    check(array_column($all['data'], 'id') === [96,97,98,99,100], 'El catálogo debe mostrar Kit Cuartel sin modo kit, conservar borradores/archivados y excluir informativos/decorativos');
    foreach (['Kit Cuartel', '111600600000000'] as $search) {
        $result = $controller->index(new \Illuminate\Http\Request(['search' => $search]));
        check(in_array(96, array_column($result['data'], 'id'), true), 'Se debe poder buscar el registro original por nombre o SKU');
    }
    foreach (['publicado' => [96,97,100], 'borrador' => [98], 'archivado' => [99]] as $estado => $ids) {
        check(array_column($controller->index(new \Illuminate\Http\Request(['estado' => $estado]))['data'], 'id') === $ids, 'Conservar filtro de estado '.$estado);
    }
    check($all['per_page'] === 24, 'Conservar paginación');
    check(\App\Models\PteSkuGenerico::$rows === $before, 'Listar no debe activar modo kit ni modificar productos');
    echo "OK: Kit Cuartel visible y buscable sin activar modo kit, estados y exclusiones conservados. Sin BD.\n";
}
