<?php
// Prueba aislada: sin bootstrap Laravel, conexiones, migraciones ni registros reales.
namespace Illuminate\Support\Facades {
    class DB { public static function transaction($fn) { return $fn(); } }
}
namespace App\Models {
    class PteSku {
        public static array $rows = [];
        public array $writes = [];
        public function __construct(public string $sku, public int $sku_generico_id, public string $nombre) {}
        public static function query() { return new FakeQuery(); }
        public function update(array $fields) { $this->writes[] = $fields; $this->nombre = $fields['nombre']; }
    }
    class FakeQuery {
        private array $filters = [];
        public function where($field, $value) { $this->filters[$field] = $value; return $this; }
        public function lockForUpdate() { return $this; }
        public function firstOrFail() {
            foreach (PteSku::$rows as $row) {
                foreach ($this->filters as $key => $value) { if ($row->$key !== $value) continue 2; }
                return $row;
            }
            throw new \OutOfBoundsException('Variante fuera de la plantilla');
        }
    }
}
namespace {
    require __DIR__.'/../../app/Exceptions/ProductCatalogConflictException.php';
    require __DIR__.'/../../app/Services/SkuGeneratorService.php';
    require __DIR__.'/../../app/Services/ProductCatalogService.php';
    function check($condition, $message) { if (!$condition) throw new \RuntimeException($message); }
    $a = new \App\Models\PteSku('A', 10, 'Nombre A');
    $b = new \App\Models\PteSku('B', 11, 'Nombre B');
    \App\Models\PteSku::$rows = [$a, $b];
    $service = new \App\Services\ProductCatalogService(new \App\Services\SkuGeneratorService());
    $result = $service->renameVariant(10, 'A', 'Nuevo A', 'Nombre A');
    check($result === $a && $a->writes === [['nombre' => 'Nuevo A']], 'Solo debe escribir nombre');
    check($b->nombre === 'Nombre B' && !$b->writes, 'No modifica otras variantes');
    try { $service->renameVariant(10, 'B', 'Incorrecto', 'Nombre B'); throw new \RuntimeException('Falta protección de plantilla'); }
    catch (\OutOfBoundsException) {}
    try { $service->renameVariant(10, 'A', 'Viejo', 'Nombre A'); throw new \RuntimeException('Falta protección de concurrencia'); }
    catch (\App\Exceptions\ProductCatalogConflictException) {}
    check($a->nombre === 'Nuevo A' && count($a->writes) === 1, 'Conflicto no escribe');
    echo "OK: nombre aislado, variante correcta, plantilla correcta y conflicto protegido. Sin BD.\n";
}
