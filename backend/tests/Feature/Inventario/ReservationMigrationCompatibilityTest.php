<?php

namespace Tests\Feature\Inventario;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReservationMigrationCompatibilityTest extends TestCase
{
    public function test_historical_migration_then_repair_does_not_duplicate_columns(): void
    {
        $this->assertFalse(Schema::hasColumn('peticiones', 'referencia_externa'));
        $historical = require database_path('migrations/2026_08_26_150000_add_referencia_externa_to_peticiones_table.php');
        $repair = require database_path('migrations/2026_08_30_162519_restore_external_references_on_peticiones_table.php');
        $historical->up();
        $repair->up();
        $repair->up();
        $this->assertTrue(Schema::hasColumn('peticiones', 'referencia_externa'));
        $this->assertTrue(Schema::hasColumn('peticiones', 'cliente_externo'));
    }
}
