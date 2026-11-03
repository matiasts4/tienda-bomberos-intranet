<?php

use Illuminate\Database\Migrations\Migration;

// Nombre de la cotización externa en reservas manuales. referencia_externa/cliente_externo
// no se agregan aquí: ReservationMigrationCompatibilityTest necesita el esquema sin ellos.
return new class extends Migration
{
    public function up(): void
    {
        (require __DIR__.'/../../../database/migrations/2026_10_02_000001_add_nombre_cotizacion_to_peticiones_table.php')->up();
    }
};
