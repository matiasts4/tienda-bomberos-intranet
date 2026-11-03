<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Agrega columnas para el seguimiento de Control de Calidad (QC) en paquetes.
     * - total_unidades: Total de unidades en el paquete (calculado de items)
     * - unidades_en_taller: Unidades actualmente en el taller de confección
     * - unidades_en_reparacion: Unidades que fueron rechazadas y están en reparación
     * - unidades_listas: Unidades que pasaron QC exitosamente
     */
    public function up(): void
    {
        Schema::table('paquetes', function (Blueprint $table) {
            $table->unsignedInteger('total_unidades')->default(0)->after('tracking_number')
                ->comment('Total de unidades en el paquete');
            $table->unsignedInteger('unidades_en_taller')->default(0)->after('total_unidades')
                ->comment('Unidades actualmente en confección');
            $table->unsignedInteger('unidades_en_reparacion')->default(0)->after('unidades_en_taller')
                ->comment('Unidades rechazadas en reparación');
            $table->unsignedInteger('unidades_listas')->default(0)->after('unidades_en_reparacion')
                ->comment('Unidades que pasaron QC exitosamente');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('paquetes', function (Blueprint $table) {
            $table->dropColumn([
                'total_unidades',
                'unidades_en_taller',
                'unidades_en_reparacion',
                'unidades_listas'
            ]);
        });
    }
};
