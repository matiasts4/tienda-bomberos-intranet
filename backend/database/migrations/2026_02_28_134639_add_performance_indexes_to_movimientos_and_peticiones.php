<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pte_movimientos', function (Blueprint $table) {
            // Composite index for historialGeneral queries (date filtering + grouping)
            $table->index(['fecha_hora', 'tipo_movimiento', 'usuario_id'], 'idx_mov_fecha_tipo_usuario');
            // Index for product history lookups
            $table->index('sku', 'idx_mov_sku');
        });

        Schema::table('peticiones', function (Blueprint $table) {
            // Index for counting pending requests by status
            $table->index('estado_id', 'idx_pet_estado');
            // Index for looking up requests by quote
            $table->index('cotizacion_id', 'idx_pet_cotizacion');
        });
    }

    public function down(): void
    {
        Schema::table('pte_movimientos', function (Blueprint $table) {
            $table->dropIndex('idx_mov_fecha_tipo_usuario');
            $table->dropIndex('idx_mov_sku');
        });

        Schema::table('peticiones', function (Blueprint $table) {
            $table->dropIndex('idx_pet_estado');
            $table->dropIndex('idx_pet_cotizacion');
        });
    }
};
