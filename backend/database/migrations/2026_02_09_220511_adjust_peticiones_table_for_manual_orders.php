<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('peticiones', function (Blueprint $table) {
            $table->foreignId('detalle_cotizacion_id')->nullable()->change();
            $table->string('sku', 50)->nullable()->after('detalle_cotizacion_id');
            $table->unsignedInteger('cantidad')->nullable()->after('sku');
            $table->unsignedBigInteger('cotizacion_id')->nullable()->after('cantidad');

            $table->foreign('sku')->references('sku')->on('pte_skus');
            $table->foreign('cotizacion_id')->references('id')->on('cotizaciones');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('peticiones', function (Blueprint $table) {
            $table->dropForeign(['sku']);
            $table->dropForeign(['cotizacion_id']);
            $table->dropColumn(['sku', 'cantidad', 'cotizacion_id']);
            $table->foreignId('detalle_cotizacion_id')->nullable(false)->change();
        });
    }
};
