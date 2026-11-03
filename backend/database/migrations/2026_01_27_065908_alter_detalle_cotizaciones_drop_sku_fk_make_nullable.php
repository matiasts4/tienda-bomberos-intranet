<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detalle_cotizaciones', function (Blueprint $table) {
            // intenta dropear FK por columna (Laravel genera el nombre automáticamente)
            try { $table->dropForeign(['sku']); } catch (\Throwable $e) {}

            // dejar sku nullable
            $table->string('sku', 50)->nullable()->change();

            // recomendado: index para búsquedas (no FK)
            $table->index('sku');
        });
    }

    public function down(): void
    {
        Schema::table('detalle_cotizaciones', function (Blueprint $table) {
            $table->dropIndex(['sku']);
            $table->string('sku', 50)->nullable(false)->change();

            // si quisieras volver al FK (ojo: fallará si hay filas con sku NULL o sku inexistente)
            $table->foreign('sku')->references('sku')->on('pte_skus');
        });
    }
};
