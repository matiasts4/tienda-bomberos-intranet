<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detalle_cotizaciones', function (Blueprint $table) {
            // 1) drop FK actual (Laravel suele nombrarla detalle_cotizaciones_sku_foreign)
            $table->dropForeign(['sku']);
        });

        Schema::table('detalle_cotizaciones', function (Blueprint $table) {
            // 2) sku nullable
            $table->string('sku', 50)->nullable()->change();
        });

        Schema::table('detalle_cotizaciones', function (Blueprint $table) {
            // 3) vuelve a crear FK (ahora sku puede ser null y no falla)
            $table->foreign('sku')->references('sku')->on('pte_skus');
        });
    }

    public function down(): void
    {
        Schema::table('detalle_cotizaciones', function (Blueprint $table) {
            $table->dropForeign(['sku']);
        });

        Schema::table('detalle_cotizaciones', function (Blueprint $table) {
            $table->string('sku', 50)->nullable(false)->change();
        });

        Schema::table('detalle_cotizaciones', function (Blueprint $table) {
            $table->foreign('sku')->references('sku')->on('pte_skus');
        });
    }
};
