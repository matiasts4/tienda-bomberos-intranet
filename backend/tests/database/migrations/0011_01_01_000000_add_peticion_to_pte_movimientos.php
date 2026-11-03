<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Completar peticiones con las columnas reales: la bitácora ahora las consulta.
        Schema::table('peticiones', function (Blueprint $table) {
            foreach ([
                'cantidad' => fn () => $table->unsignedInteger('cantidad')->nullable(),
                'cotizacion_id' => fn () => $table->unsignedBigInteger('cotizacion_id')->nullable(),
                'user_id' => fn () => $table->unsignedBigInteger('user_id')->nullable(),
                'fecha_creacion' => fn () => $table->date('fecha_creacion')->nullable(),
                'observacion' => fn () => $table->text('observacion')->nullable(),
                // referencia_externa / cliente_externo los agrega su propia migración
                // (ReservationMigrationCompatibilityTest verifica ese recorrido).
            ] as $column => $add) {
                if (! Schema::hasColumn('peticiones', $column)) {
                    $add();
                }
            }
        });

        (require __DIR__.'/../../../database/migrations/2026_09_30_000001_add_peticion_and_observacion_to_pte_movimientos.php')->up();
    }
};
