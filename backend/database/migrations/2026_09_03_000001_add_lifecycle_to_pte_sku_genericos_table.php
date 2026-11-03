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
     * Agrega soporte de ciclo de vida formal a pte_sku_genericos:
     * - estado_catalogo: 'borrador', 'publicado', 'archivado'
     * - archived_at, archived_by, archive_reason
     * Realiza un backfill determinista preservando variantes y stock:
     * - Activo con variantes activas -> 'publicado'
     * - Activo sin variantes activas -> 'borrador' (activo false)
     * - Inactivo -> 'archivado' (activo false)
     */
    public function up(): void
    {
        Schema::table('pte_sku_genericos', function (Blueprint $table) {
            $table->string('estado_catalogo', 20)->default('borrador')->after('activo');
            $table->timestamp('archived_at')->nullable()->after('estado_catalogo');
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete()->after('archived_at');
            $table->text('archive_reason')->nullable()->after('archived_by');
        });

        // ── BACKFILL SEGURO ───────────────────────────────────────────────────

        // 1. Genérico activo con variantes activas -> publicado
        DB::table('pte_sku_genericos')
            ->where('activo', true)
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('pte_skus')
                    ->whereColumn('pte_skus.sku_generico_id', 'pte_sku_genericos.id')
                    ->where('pte_skus.activo', true);
            })
            ->update([
                'estado_catalogo' => 'publicado',
                'activo' => true,
            ]);

        // 2. Genérico inactivo -> archivado. Debe ir antes del paso 3, que también
        // deja activo = false; si no, ambos casos quedan indistinguibles.
        DB::table('pte_sku_genericos')
            ->where('activo', false)
            ->update([
                'estado_catalogo' => 'archivado',
                'archived_at' => now(),
            ]);

        // 3. Genérico activo sin variantes activas -> borrador
        DB::table('pte_sku_genericos')
            ->where('activo', true)
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('pte_skus')
                    ->whereColumn('pte_skus.sku_generico_id', 'pte_sku_genericos.id')
                    ->where('pte_skus.activo', true);
            })
            ->update([
                'estado_catalogo' => 'borrador',
                'activo' => false,
            ]);
    }

    /**
     * Reverse the migrations.
     *
     * Elimina las columnas de ciclo de vida sin afectar datos históricos ni relaciones.
     */
    public function down(): void
    {
        Schema::table('pte_sku_genericos', function (Blueprint $table) {
            $table->dropForeign(['archived_by']);
            $table->dropColumn([
                'archive_reason',
                'archived_by',
                'archived_at',
                'estado_catalogo',
            ]);
        });
    }
};
