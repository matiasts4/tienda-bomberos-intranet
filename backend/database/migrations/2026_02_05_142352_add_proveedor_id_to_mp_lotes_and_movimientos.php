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
        Schema::table('mp_lotes', function (Blueprint $table) {
            $table->foreignId('proveedor_id')->nullable()->after('material_id')->constrained('mp_proveedores')->onDelete('restrict');
        });

        Schema::table('mp_movimientos', function (Blueprint $table) {
            $table->foreignId('proveedor_id')->nullable()->after('lote_id')->constrained('mp_proveedores')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mp_lotes', function (Blueprint $table) {
            $table->dropForeign(['proveedor_id']);
            $table->dropColumn('proveedor_id');
        });

        Schema::table('mp_movimientos', function (Blueprint $table) {
            $table->dropForeign(['proveedor_id']);
            $table->dropColumn('proveedor_id');
        });
    }
};
