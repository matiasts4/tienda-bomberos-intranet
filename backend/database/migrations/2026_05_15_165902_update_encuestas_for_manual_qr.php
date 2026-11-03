<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First drop the foreign key and unique constraints. Note: The constraint names might depend on the table name.
        Schema::table('encuesta_satisfaccions', function (Blueprint $table) {
            $table->dropForeign(['cotizacion_id']);
            $table->dropUnique('encuesta_satisfaccions_cotizacion_id_unique');
        });

        Schema::table('encuesta_satisfaccions', function (Blueprint $table) {
            $table->foreignId('cotizacion_id')->nullable()->change();
            // Re-add foreign key constraint but allow nulls
            $table->foreign('cotizacion_id')->references('id')->on('cotizaciones')->onDelete('cascade');
            
            // New columns for manual mode
            $table->string('identificador_venta', 100)->nullable()->after('cotizacion_id');
            $table->foreignId('vendedor_id')->nullable()->after('identificador_venta')->constrained('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('encuesta_satisfaccions', function (Blueprint $table) {
            $table->dropForeign(['vendedor_id']);
            $table->dropColumn('vendedor_id');
            $table->dropColumn('identificador_venta');
            
            $table->dropForeign(['cotizacion_id']);
            $table->foreignId('cotizacion_id')->nullable(false)->change();
            $table->foreign('cotizacion_id')->references('id')->on('cotizaciones')->onDelete('cascade');
            $table->unique('cotizacion_id');
        });
    }
};
