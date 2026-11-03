<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pte_sku_genericos', function (Blueprint $table) {
            $table->boolean('es_informativo_cotizacion')->default(false);
        });
        // Identidades verificadas: no clasificar por precio ni por nombre.
        DB::table('pte_sku_genericos')->whereIn('sku_base', [
            '117700000100000', '117700000200000', '117700000300000',
            '117700000400000', '117700000500000', '117700000600000',
            '117700000700000', '117700000800000', '117700000900000',
        ])->update(['es_informativo_cotizacion' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pte_sku_genericos', function (Blueprint $table) {
            $table->dropColumn('es_informativo_cotizacion');
        });
    }
};
