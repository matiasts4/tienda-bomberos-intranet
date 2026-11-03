<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nombre descriptivo de la cotización externa de una reserva manual
     * (equivale a cotizaciones.nombre en las cotizaciones del sistema).
     */
    public function up(): void
    {
        if (! Schema::hasColumn('peticiones', 'nombre_cotizacion')) {
            Schema::table('peticiones', function (Blueprint $table) {
                $table->string('nombre_cotizacion', 255)->nullable()->after('referencia_externa');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('peticiones', 'nombre_cotizacion')) {
            Schema::table('peticiones', function (Blueprint $table) {
                $table->dropColumn('nombre_cotizacion');
            });
        }
    }
};
