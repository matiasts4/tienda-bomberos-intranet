<?php
// Dobles en memoria: este archivo nunca inicia Laravel ni abre una conexión a BD.
namespace Illuminate\Database\Migrations { abstract class Migration {} }
namespace Illuminate\Database\Schema {
    class Blueprint {
        public function boolean($name) { \Illuminate\Support\Facades\Schema::$changes['column'] = $name; return $this; }
        public function default($value) { \Illuminate\Support\Facades\Schema::$changes['default'] = $value; return $this; }
        public function after($name) { \Illuminate\Support\Facades\Schema::$changes['after'] = $name; return $this; }
    }
}
namespace Illuminate\Support\Facades {
    class DB { public static function transaction($fn) { return $fn(); } }
    class Schema {
        public static array $changes = [];
        public static function table($name, $callback) { self::$changes['table'] = $name; $callback(new \Illuminate\Database\Schema\Blueprint()); }
    }
}
namespace Illuminate\Http { class Request { public function input($name) { return null; } } }
namespace App\Http\Controllers { class Controller {} }
namespace App\Models {
    #[\AllowDynamicProperties]
    class PteSkuGenerico {
        public static array $rows = [];
        public array $writes = [];
        public function __construct(array $attributes) { foreach ($attributes as $key => $value) $this->$key = $value; }
        public static function query() { return new FakeQuery(); }
        public static function create(array $attributes) { $attributes['id'] = count(self::$rows) + 1; $row = new self($attributes); self::$rows[] = $row; return $row; }
        public function update(array $attributes) { $this->writes[] = $attributes; foreach ($attributes as $key => $value) $this->$key = $value; }
        public function refresh() { return $this; }
    }
    class PteGenero { public static function find($id) { return null; } }
    class FakeQuery {
        private array $filters = [];
        private bool $activeVariant = false;
        private bool $kitOrVariant = false;
        public function where($field, $value = null) { if ($field instanceof \Closure) { $nested = new self(); $field($nested); $this->kitOrVariant = true; return $this; } $this->filters[$field] = $value; return $this; }
        public function orWhereHas($relation, $callback) { return $this->whereHas($relation, $callback); }
        public function lockForUpdate() { return $this; }
        public function first() { return null; }
        public function findOrFail($id) { foreach (PteSkuGenerico::$rows as $row) if ($row->id === $id) return $row; throw new \RuntimeException('No existe'); }
        public function whereHas($relation, $callback) { if ($relation !== 'variantes') throw new \RuntimeException('Relación inesperada'); $query = new self(); $callback($query); $this->activeVariant = ($query->filters['activo'] ?? false) === true; return $this; }
        public function orderBy($field, $direction) { return $this; }
        public function get() {
            $rows = array_filter(PteSkuGenerico::$rows, function ($row) {
                foreach ($this->filters as $field => $value) if ($row->$field !== $value) return false;
                return (!$this->activeVariant || $row->hasActiveVariant) && (!$this->kitOrVariant || $row->modo_kit || $row->hasActiveVariant);
            });
            return new FakeCollection(array_values($rows));
        }
    }
    class FakeCollection {
        public function __construct(private array $rows) {}
        public function map($callback) { return array_map($callback, $this->rows); }
    }
}
namespace {
    function response() { return new class { public function json($value) { return $value; } }; }
    function check($condition, $message) { if (!$condition) throw new \RuntimeException($message); }
    require __DIR__.'/../../app/Exceptions/ProductCatalogConflictException.php';
    require __DIR__.'/../../app/Services/SkuGeneratorService.php';
    require __DIR__.'/../../app/Services/ProductCatalogService.php';
    require __DIR__.'/../../app/Http/Controllers/Api/ComercialSkuGenericoController.php';

    $migration = require __DIR__.'/../../database/migrations/2026_10_12_030000_add_permite_cotizar_to_pte_sku_genericos_table.php';
    $migration->up();
    check(\Illuminate\Support\Facades\Schema::$changes === ['table' => 'pte_sku_genericos', 'column' => 'permite_cotizar', 'default' => true, 'after' => 'permite_personalizar'], 'Solo agregar flag con default true a la tabla autorizada');
    try { $migration->down(); throw new \LogicException('Rollback destructivo'); } catch (\RuntimeException $e) { check(str_contains($e->getMessage(), 'conserva'), 'Rollback debe conservar datos'); }

    $service = new \App\Services\ProductCatalogService(new \App\Services\SkuGeneratorService());
    $attributes = ['nombre' => 'Blusa', 'unidad_negocio_id' => 1, 'origen_id' => 1, 'grp_familia_id' => 1, 'familia_id' => 1, 'subfamilia_id' => 0, 'familia_tipo_id' => 0, 'familia_formato_id' => 0, 'genero_id' => 0];
    $enabled = $service->createTemplate($attributes)['creados'][0];
    check($enabled->permite_cotizar === true, 'Plantilla cotizable por defecto');
    check($enabled->modo_kit === false && $enabled->componentes_kit === [], 'Plantillas nuevas no son kits por defecto');
    $kitCreated = $service->createTemplate($attributes + ['modo_kit' => true])['creados'][0];
    check($kitCreated->modo_kit === true && $kitCreated->componentes_kit === [], 'Kit nuevo aún requiere definir componentes');
    $disabled = $service->createTemplate($attributes + ['permite_cotizar' => false])['creados'][0];
    check($disabled->permite_cotizar === false && $disabled->permite_personalizar === true, 'Desactivación independiente de personalización');
    $service->updateGeneric($enabled->id, ['permite_cotizar' => false]);
    $service->updateGeneric($enabled->id, ['permite_cotizar' => true]);
    check($enabled->writes === [['permite_cotizar' => false], ['permite_cotizar' => true]], 'Edición del switch escribe exclusivamente el flag');

    $base = ['estado_catalogo' => 'publicado', 'activo' => true, 'permite_cotizar' => true, 'modo_kit' => false, 'hasActiveVariant' => true, 'sku_base' => '111100000000000', 'nombre' => 'Producto', 'imagen_url' => null, 'imagen_back_url' => null, 'imagen_side_url' => null, 'precio_venta' => 20000];
    \App\Models\PteSkuGenerico::$rows = [];
    foreach ([[], ['permite_cotizar' => false], ['estado_catalogo' => 'borrador'], ['activo' => false], ['hasActiveVariant' => false], ['permite_cotizar' => true], ['modo_kit' => true, 'hasActiveVariant' => false]] as $i => $overrides) {
        \App\Models\PteSkuGenerico::$rows[] = new \App\Models\PteSkuGenerico(array_merge($base, ['id' => $i + 1], $overrides));
    }
    $result = (new \App\Http\Controllers\Api\ComercialSkuGenericoController())->index(new \Illuminate\Http\Request());
    check(array_column($result, 'id') === [1, 6, 7], 'Solo cotizables publicados/activos con variantes activas o kits comerciales');
    check($result[0]['permite_cotizar'] === true && $result[0]['sku'] === $base['sku_base'], 'Contrato SKU genérico compatible');
    echo "OK: migración acotada y conservadora, default true, crear/editar ambos valores y filtro comercial. Sin BD.\n";
}
