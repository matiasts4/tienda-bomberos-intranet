<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vincula cada movimiento con la solicitud (peticion) que lo originó, para
     * distinguir quién solicitó de quién aprobó o rechazó. Solo agrega columnas.
     */
    public function up(): void
    {
        Schema::table('pte_movimientos', function (Blueprint $table) {
            if (! Schema::hasColumn('pte_movimientos', 'peticion_id')) {
                $table->foreignId('peticion_id')->nullable()->after('cotizacion_id')
                    ->constrained('peticiones')->nullOnDelete();
            }
            if (! Schema::hasColumn('pte_movimientos', 'observacion')) {
                $table->text('observacion')->nullable()->after('id_lote');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pte_movimientos', function (Blueprint $table) {
            if (Schema::hasColumn('pte_movimientos', 'peticion_id')) {
                $table->dropConstrainedForeignId('peticion_id');
            }
        });
        // observacion no se elimina: puede contener texto de auditoría ya registrado.
    }
};
