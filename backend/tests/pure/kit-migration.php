<?php
// Dobles de Schema: ninguna conexión ni sentencia SQL.
namespace Illuminate\Database\Migrations { class Migration {} }
namespace Illuminate\Database\Schema {
    class Blueprint {
        public array $columns = [];
        public function boolean($name) { $this->columns[$name] = ['type' => 'boolean']; return new Column($this, $name); }
        public function json($name) { $this->columns[$name] = ['type' => 'json']; return new Column($this, $name); }
    }
    class Column {
        public function __construct(private Blueprint $schema, private string $name) {}
        public function default($value) { $this->schema->columns[$this->name]['default'] = $value; return $this; }
        public function nullable() { $this->schema->columns[$this->name]['nullable'] = true; return $this; }
    }
}
namespace Illuminate\Support\Facades {
    class Schema {
        public static array $changes = [];
        public static function table($table, $callback) {
            $blueprint = new \Illuminate\Database\Schema\Blueprint();
            $callback($blueprint); self::$changes[] = ['table' => $table, 'columns' => $blueprint->columns];
        }
    }
}
namespace {
    $migration = require __DIR__.'/../../database/migrations/2026_10_12_040000_add_kit_mode_to_pte_sku_genericos_table.php';
    $migration->up();
    if (\Illuminate\Support\Facades\Schema::$changes !== [['table' => 'pte_sku_genericos', 'columns' => ['modo_kit' => ['type' => 'boolean', 'default' => false], 'componentes_kit' => ['type' => 'json', 'nullable' => true]]]]) throw new \RuntimeException('Esquema inesperado');
    try { $migration->down(); throw new \LogicException('Rollback destructivo'); } catch (\RuntimeException $e) {
        if (!str_contains($e->getMessage(), 'conserva')) throw $e;
    }
    echo "OK: modo kit DEFAULT false, solo dos columnas en pte_sku_genericos y rollback conservador. Sin BD.\n";
}
