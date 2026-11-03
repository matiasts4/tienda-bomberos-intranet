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
        Schema::table('pte_sku_genericos', function (Blueprint $table) {
            $table->string('modelo_3d_url')->nullable()->after('imagen_url');
            $table->string('material_3d_url')->nullable()->after('modelo_3d_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pte_sku_genericos', function (Blueprint $table) {
            $table->dropColumn(['modelo_3d_url', 'material_3d_url']);
        });
    }
};
