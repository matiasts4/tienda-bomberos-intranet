<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Normalización PTE: Vincular pte_skus → pte_sku_genericos con FK formal.
     * 
     * 1. Agregar columnas faltantes a pte_sku_genericos (unidad_negocio_id, origen_id, grp_familia_id)
     * 2. Agregar sku_generico_id FK a pte_skus
     * 3. Limpiar datos huérfanos de dev (producción está vacío)
     * 4. Eliminar 8 columnas redundantes de pte_skus
     */
    public function up(): void
    {
        // ── 1. Agregar columnas faltantes a pte_sku_genericos ──────────────
        Schema::table('pte_sku_genericos', function (Blueprint $table) {
            if (!Schema::hasColumn('pte_sku_genericos', 'unidad_negocio_id')) {
                $table->unsignedBigInteger('unidad_negocio_id')->nullable()->after('precio_venta');
            }
            if (!Schema::hasColumn('pte_sku_genericos', 'origen_id')) {
                $table->unsignedBigInteger('origen_id')->nullable()->after('unidad_negocio_id');
            }
            if (!Schema::hasColumn('pte_sku_genericos', 'grp_familia_id')) {
                $table->unsignedBigInteger('grp_familia_id')->nullable()->after('origen_id');
            }
        });

        // ── 2. Limpiar datos de dev (producción está vacía, no importa) ────
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('pte_movimientos')->truncate();
        DB::table('pte_stocks')->truncate();
        DB::table('pte_skus')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // ── 3. Agregar FK sku_generico_id a pte_skus ───────────────────────
        Schema::table('pte_skus', function (Blueprint $table) {
            $table->unsignedBigInteger('sku_generico_id')->nullable()->after('sku');
            $table->foreign('sku_generico_id')
                  ->references('id')
                  ->on('pte_sku_genericos')
                  ->nullOnDelete();
        });

        // ── 4. Eliminar columnas redundantes de pte_skus ───────────────────
        Schema::table('pte_skus', function (Blueprint $table) {
            // Drop foreign keys first (if they exist)
            $fksToCheck = [
                'pte_skus_unidad_negocio_id_foreign',
                'pte_skus_origen_id_foreign',
                'pte_skus_grp_familia_id_foreign',
                'pte_skus_familia_id_foreign',
                'pte_skus_subfamilia_id_foreign',
                'pte_skus_familia_tipo_id_foreign',
                'pte_skus_familia_formato_id_foreign',
                'pte_skus_genero_id_foreign',
            ];

            foreach ($fksToCheck as $fk) {
                try {
                    $table->dropForeign($fk);
                } catch (\Exception $e) {
                    // FK might not exist as named constraint, skip
                }
            }
        });

        Schema::table('pte_skus', function (Blueprint $table) {
            $columnsToDrop = [
                'unidad_negocio_id',
                'origen_id',
                'grp_familia_id',
                'familia_id',
                'subfamilia_id',
                'familia_tipo_id',
                'familia_formato_id',
                'genero_id',
            ];

            foreach ($columnsToDrop as $col) {
                if (Schema::hasColumn('pte_skus', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Re-add the columns to pte_skus
        Schema::table('pte_skus', function (Blueprint $table) {
            $table->unsignedBigInteger('unidad_negocio_id')->nullable();
            $table->unsignedBigInteger('origen_id')->nullable();
            $table->unsignedBigInteger('grp_familia_id')->nullable();
            $table->unsignedBigInteger('familia_id')->nullable();
            $table->unsignedBigInteger('subfamilia_id')->nullable();
            $table->unsignedBigInteger('familia_tipo_id')->nullable();
            $table->unsignedBigInteger('familia_formato_id')->nullable();
            $table->unsignedBigInteger('genero_id')->nullable();
        });

        // Drop the FK column
        Schema::table('pte_skus', function (Blueprint $table) {
            $table->dropForeign(['sku_generico_id']);
            $table->dropColumn('sku_generico_id');
        });

        // Drop columns from genericos
        Schema::table('pte_sku_genericos', function (Blueprint $table) {
            if (Schema::hasColumn('pte_sku_genericos', 'unidad_negocio_id')) {
                $table->dropColumn('unidad_negocio_id');
            }
            if (Schema::hasColumn('pte_sku_genericos', 'origen_id')) {
                $table->dropColumn('origen_id');
            }
            if (Schema::hasColumn('pte_sku_genericos', 'grp_familia_id')) {
                $table->dropColumn('grp_familia_id');
            }
        });
    }
};
