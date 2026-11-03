<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditProductCatalogCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'pte:audit-catalog {--detailed : Muestra el desglose completo de registros afectados}';

    /**
     * The console command description.
     */
    protected $description = 'Audita la integridad referencial y de datos del catálogo PTE (duplicados, huérfanos, stocks, SKUs no canónicos)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('===============================================================');
        $this->info('  AUDITORÍA DE INTEGRIDAD DE CATÁLOGO PTE (Laravel 12 / MySQL)  ');
        $this->info('===============================================================');
        $this->newLine();

        $detailed = $this->option('detailed');
        $hasErrors = false;

        // 1. Variantes duplicadas (sku_generico_id, color_id, talla_id)
        $duplicates = DB::table('pte_skus')
            ->select('sku_generico_id', 'color_id', 'talla_id', DB::raw('COUNT(*) as total'), DB::raw('GROUP_CONCAT(sku) as skus'))
            ->whereNotNull('sku_generico_id')
            ->groupBy('sku_generico_id', 'color_id', 'talla_id')
            ->having('total', '>', 1)
            ->get();

        // 2. Variantes sin producto genérico (huérfanas)
        $orphanVariants = DB::table('pte_skus')
            ->leftJoin('pte_sku_genericos', 'pte_skus.sku_generico_id', '=', 'pte_sku_genericos.id')
            ->whereNull('pte_sku_genericos.id')
            ->select('pte_skus.sku', 'pte_skus.sku_generico_id', 'pte_skus.nombre')
            ->get();

        // 3. Variantes sin registro de stock en pte_stocks
        $variantsWithoutStock = DB::table('pte_skus')
            ->leftJoin('pte_stocks', 'pte_skus.sku', '=', 'pte_stocks.sku')
            ->whereNull('pte_stocks.id')
            ->select('pte_skus.sku', 'pte_skus.nombre', 'pte_skus.activo')
            ->get();

        // 4. Genéricos archivados/inactivos con variantes activas
        $archivedWithActiveVariants = DB::table('pte_sku_genericos')
            ->join('pte_skus', 'pte_sku_genericos.id', '=', 'pte_skus.sku_generico_id')
            ->where(function ($q) {
                $q->where('pte_sku_genericos.estado_catalogo', 'archivado')
                    ->orWhere('pte_sku_genericos.activo', false);
            })
            ->where('pte_skus.activo', true)
            ->select('pte_sku_genericos.id as gen_id', 'pte_sku_genericos.sku_base', 'pte_sku_genericos.nombre as gen_nombre', 'pte_skus.sku', 'pte_skus.nombre as var_nombre')
            ->get();

        // 5. SKUs no canónicos (no conformes al patrón de 15 dígitos numéricos)
        $allSkus = DB::table('pte_skus')->select('sku', 'nombre')->get();
        $nonCanonicalSkus = $allSkus->filter(fn ($item) => ! preg_match('/^\d{15}$/', (string) $item->sku));

        // 6. Stock huérfano (en pte_stocks sin SKU existente en pte_skus)
        $orphanStocks = DB::table('pte_stocks')
            ->leftJoin('pte_skus', 'pte_stocks.sku', '=', 'pte_skus.sku')
            ->whereNull('pte_skus.sku')
            ->select('pte_stocks.id', 'pte_stocks.sku', 'pte_stocks.cantidad', 'pte_stocks.reserved_stock')
            ->get();

        // ── TABLA RESUMEN ─────────────────────────────────────────────────────
        $summary = [
            ['1. Variantes duplicadas (producto/color/talla)', $duplicates->count(), $duplicates->count() > 0 ? 'CRÍTICO' : 'OK'],
            ['2. Variantes huérfanas (sin genérico padre)', $orphanVariants->count(), $orphanVariants->count() > 0 ? 'CRÍTICO' : 'OK'],
            ['3. Variantes sin registro de stock', $variantsWithoutStock->count(), $variantsWithoutStock->count() > 0 ? 'ADVERTENCIA' : 'OK'],
            ['4. Genéricos archivados con variantes activas', $archivedWithActiveVariants->count(), $archivedWithActiveVariants->count() > 0 ? 'ADVERTENCIA' : 'OK'],
            ['5. SKUs con formato no canónico (legacy)', $nonCanonicalSkus->count(), $nonCanonicalSkus->count() > 0 ? 'INFORMATIVO' : 'OK'],
            ['6. Registros de stock huérfanos', $orphanStocks->count(), $orphanStocks->count() > 0 ? 'ADVERTENCIA' : 'OK'],
        ];

        $this->table(['Check de Integridad', 'Registros Afectados', 'Nivel'], $summary);
        $this->newLine();

        // ── DETALLES Y GUÍAS DE CONCILIACIÓN ─────────────────────────────────

        if ($duplicates->count() > 0) {
            $hasErrors = true;
            $this->error("ANOMALÍA CRÍTICA: Se detectaron {$duplicates->count()} combinaciones duplicadas de (sku_generico_id, color_id, talla_id).");
            $this->line('Instrucción: Antes de aplicar la restricción UNIQUE, concilia los siguientes SKUs duplicados fusionando stock o archivando el duplicado:');
            $sample = $duplicates->take($detailed ? 100 : 5)->map(fn ($d) => [
                'Genérico ID' => $d->sku_generico_id,
                'Color ID' => $d->color_id,
                'Talla ID' => $d->talla_id,
                'Total' => $d->total,
                'SKUs en conflicto' => $d->skus,
            ])->toArray();
            $this->table(['Genérico ID', 'Color ID', 'Talla ID', 'Total', 'SKUs en conflicto'], $sample);
            $this->newLine();
        }

        if ($orphanVariants->count() > 0) {
            $hasErrors = true;
            $this->error("ANOMALÍA CRÍTICA: Se detectaron {$orphanVariants->count()} variantes sin producto genérico asignado.");
            $this->line("Instrucción: Asigna el 'sku_generico_id' correspondiente a cada variante huérfana antes de migrar a NOT NULL.");
            $sample = $orphanVariants->take($detailed ? 100 : 5)->map(fn ($v) => [
                'SKU' => $v->sku,
                'Genérico ID' => $v->sku_generico_id ?? 'NULL',
                'Nombre' => $v->nombre,
            ])->toArray();
            $this->table(['SKU', 'Genérico ID', 'Nombre'], $sample);
            $this->newLine();
        }

        if ($variantsWithoutStock->count() > 0) {
            $this->warn("ADVERTENCIA: {$variantsWithoutStock->count()} variantes no tienen fila inicial en 'pte_stocks'.");
            $this->line('Instrucción: Ejecutar conciliación para generar pte_stocks con cantidad = 0 para cada SKU faltante.');
            $this->newLine();
        }

        if ($archivedWithActiveVariants->count() > 0) {
            $this->warn("ADVERTENCIA: {$archivedWithActiveVariants->count()} variantes activas pertenecen a productos base archivados o inactivos.");
            $this->line('Instrucción: Desactivar estas variantes para cumplir con la regla de coherencia del ciclo de vida.');
            $this->newLine();
        }

        if ($nonCanonicalSkus->count() > 0) {
            $this->line("INFO: Se registraron {$nonCanonicalSkus->count()} SKUs legacy que no siguen el formato canónico numérico de 15 dígitos.");
            $this->newLine();
        }

        if ($orphanStocks->count() > 0) {
            $this->warn("ADVERTENCIA: Se encontraron {$orphanStocks->count()} registros en 'pte_stocks' cuyo SKU no existe en 'pte_skus'.");
            $this->newLine();
        }

        if ($hasErrors) {
            $this->error('Fallo en la auditoría: existen inconsistencias críticas que deben conciliarse.');

            return Command::FAILURE;
        }

        $this->info('✓ Catálogo PTE íntegro: No se detectaron anomalías críticas de duplicación ni orfandad.');

        return Command::SUCCESS;
    }
}
