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
        Schema::table('ordenes_corte', function (Blueprint $table) {
            $table->integer('total_unidades')->default(0);
            $table->integer('unidades_en_taller')->default(0);
            $table->integer('unidades_en_reparacion')->default(0);
            $table->integer('unidades_listas')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ordenes_corte', function (Blueprint $table) {
            $table->dropColumn([
                'total_unidades',
                'unidades_en_taller',
                'unidades_en_reparacion',
                'unidades_listas'
            ]);
        });
    }
};
