<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Agrega índices de rendimiento y restricción de integridad única para pte_skus.
     */
    public function up(): void
    {
        // ── 1. ÍNDICES DE RENDIMIENTO ─────────────────────────────────────────
        Schema::table('pte_sku_genericos', function (Blueprint $table) {
            $table->index(['estado_catalogo', 'nombre'], 'pte_sku_gen_estado_nombre_idx');
        });

        Schema::table('pte_skus', function (Blueprint $table) {
            $table->index(['sku_generico_id', 'activo'], 'pte_skus_generico_activo_idx');
        });

        // ── 2. AUDITORÍA PREVIA Y RESTRICCIÓN ÚNICA ───────────────────────────
        // Verificamos si existen duplicados antes de intentar crear la restricción UNIQUE
        $duplicatesCount = DB::table('pte_skus')
            ->select('sku_generico_id', 'color_id', 'talla_id', DB::raw('COUNT(*) as total'))
            ->whereNotNull('sku_generico_id')
            ->groupBy('sku_generico_id', 'color_id', 'talla_id')
            ->having('total', '>', 1)
            ->count();

        if ($duplicatesCount > 0) {
            throw new RuntimeException(
                "No se puede aplicar la restricción UNIQUE(sku_generico_id, color_id, talla_id): " .
                "Existen {$duplicatesCount} combinaciones duplicadas en la tabla pte_skus. " .
                "Ejecuta 'php artisan pte:audit-catalog' para auditar y conciliar las anomalías antes de continuar."
            );
        }

        Schema::table('pte_skus', function (Blueprint $table) {
            $table->unique(['sku_generico_id', 'color_id', 'talla_id'], 'pte_skus_generico_color_talla_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pte_skus', function (Blueprint $table) {
            $table->dropUnique('pte_skus_generico_color_talla_unique');
            $table->dropIndex('pte_skus_generico_activo_idx');
        });

        Schema::table('pte_sku_genericos', function (Blueprint $table) {
            $table->dropIndex('pte_sku_gen_estado_nombre_idx');
        });
    }
};
