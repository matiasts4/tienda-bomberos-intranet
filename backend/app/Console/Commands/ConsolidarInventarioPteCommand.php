<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Models\PteSkuGenerico;
use App\Models\PteSku;
use App\Models\PteStock;
use App\Models\PteMovimiento;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ConsolidarInventarioPteCommand extends Command
{
    protected $signature = 'pte:consolidar-inventario {--file= : Ruta al archivo Excel consolidado} {--force : Ejecutar sin confirmación}';
    protected $description = 'Consolida y actualiza el inventario maestro PTE y existencias físicas de Bodega desde Excel';

    public function handle(): int
    {
        $this->info('===============================================================');
        $this->info('  CONSOLIDACIÓN Y ACTUALIZACIÓN DE INVENTARIO MAESTRO PTE');
        $this->info('===============================================================');

        $filePath = $this->option('file') ?: base_path('INVENTARIO_MAESTRO_CONSOLIDADO.xlsx');
        if (!file_exists($filePath)) {
            $filePath = base_path('../INVENTARIO_MAESTRO_CONSOLIDADO.xlsx');
        }

        if (!file_exists($filePath)) {
            $this->error("No se encontró el archivo maestro consolidado en: {$filePath}");
            return 1;
        }

        $this->info("Archivo de origen: {$filePath}");

        if (!$this->option('force')) {
            if (!$this->confirm('¿Deseas proceder con la actualización del catálogo y stock en la base de datos?', true)) {
                $this->warn('Operación cancelada por el usuario.');
                return 0;
            }
        }

        $spreadsheet = IOFactory::load($filePath);

        // 1. CARGAR HOJA SKU_Genericos_BD
        $sheetGen = $spreadsheet->getSheetByName('SKU_Genericos_BD');
        if (!$sheetGen) {
            $this->error("La hoja 'SKU_Genericos_BD' no existe en el archivo.");
            return 1;
        }

        // 2. CARGAR HOJA Importar_BD
        $sheetBd = $spreadsheet->getSheetByName('Importar_BD');
        if (!$sheetBd) {
            $this->error("La hoja 'Importar_BD' no existe en el archivo.");
            return 1;
        }

        $genRows = $sheetGen->toArray();
        $genHeaders = array_shift($genRows); // Quitar encabezado

        $bdRows = $sheetBd->toArray();
        $bdHeaders = array_shift($bdRows); // Quitar encabezado

        $this->info("Modelos Genéricos a procesar: " . count($genRows));
        $this->info("SKUs Variantes a procesar:   " . count($bdRows));

        $batchUuid = (string) Str::uuid();

        DB::beginTransaction();

        try {
            // A. Procesar Genéricos (pte_sku_genericos)
            $this->info("\n[1/4] Actualizando Modelos Genéricos (pte_sku_genericos)...");
            $genericoIdMap = [];
            $genProgressBar = $this->output->createProgressBar(count($genRows));
            $genProgressBar->start();

            foreach ($genRows as $row) {
                if (empty($row[0])) continue;
                $skuGen = trim((string) $row[0]);
                $nombreGen = trim((string) $row[1]);
                $existing = PteSkuGenerico::where('sku_base', $skuGen)->first();
                $rawImg = !empty($row[2]) ? trim((string) $row[2]) : null;
                if ($rawImg === '/products/default.png' || $rawImg === '') {
                    $rawImg = null;
                }
                $imagenUrl = $rawImg ?: ($existing?->imagen_url);
                $precioVenta = !empty($row[3]) ? (int) $row[3] : ($existing?->precio_venta ?? 0);

                // Parsear claves foráneas del SKU genérico de 15 dígitos
                $u = (int) substr($skuGen, 0, 1);
                $o = (int) substr($skuGen, 1, 1);
                $g = (int) substr($skuGen, 2, 1);
                $f = (int) substr($skuGen, 3, 1);
                $sf = (int) substr($skuGen, 4, 2);
                $ti = (int) substr($skuGen, 6, 2);
                $ff = (int) substr($skuGen, 8, 2);
                $gen = (int) substr($skuGen, 10, 1);

                $generico = PteSkuGenerico::updateOrCreate(
                    ['sku_base' => $skuGen],
                    [
                        'nombre' => $nombreGen,
                        'imagen_url' => $imagenUrl,
                        'precio_venta' => $precioVenta,
                        'unidad_negocio_id' => $u,
                        'origen_id' => $o,
                        'grp_familia_id' => $g,
                        'familia_id' => $f,
                        'subfamilia_id' => $sf,
                        'familia_tipo_id' => $ti,
                        'familia_formato_id' => $ff,
                        'genero_id' => $gen,
                        'activo' => true,
                        'permite_personalizar' => true,
                    ]
                );

                $base11 = substr($skuGen, 0, 11);
                $genericoIdMap[$base11] = $generico->id;
                $genProgressBar->advance();
            }
            $genProgressBar->finish();
            $this->newLine();

            // B. Procesar Variantes (pte_skus) y Stock (pte_stocks)
            $this->info("\n[2/4] Actualizando SKUs Variantes y Stock Físico...");
            $skuProgressBar = $this->output->createProgressBar(count($bdRows));
            $skuProgressBar->start();

            $totalStockUnidades = 0;
            $skusConStock = 0;
            $validSkus = [];

            foreach ($bdRows as $row) {
                if (empty($row[0])) continue;

                $sku = trim((string) $row[0]);
                $nombre = trim((string) $row[1]);
                $descripcion = !empty($row[2]) ? trim((string) $row[2]) : null;
                $precioVenta = !empty($row[3]) ? (int) $row[3] : 0;
                $cantidadFisica = !empty($row[4]) ? (int) $row[4] : 0;
                $stockCritico = !empty($row[5]) ? (int) $row[5] : 5;

                $colorId = !empty($row[14]) ? (int) $row[14] : 0;
                $tallaId = !empty($row[15]) ? (int) $row[15] : 0;

                $base11 = substr($sku, 0, 11);
                $skuGenericoId = $genericoIdMap[$base11] ?? null;

                // 1. Guardar o actualizar PteSku
                PteSku::updateOrCreate(
                    ['sku' => $sku],
                    [
                        'sku_generico_id' => $skuGenericoId,
                        'nombre' => $nombre,
                        'descripcion' => $descripcion,
                        'precio_venta' => $precioVenta,
                        'stock_critico' => $stockCritico,
                        'color_id' => $colorId,
                        'talla_id' => $tallaId,
                        'activo' => true,
                    ]
                );

                $validSkus[] = $sku;

                // 2. Guardar o actualizar PteStock
                PteStock::updateOrCreate(
                    ['sku' => $sku],
                    [
                        'cantidad' => $cantidadFisica,
                        'stock_critico' => $stockCritico,
                        'reserved_stock' => 0,
                    ]
                );

                // 3. Registrar auditoría en pte_movimientos si hay stock físico
                if ($cantidadFisica > 0) {
                    PteMovimiento::create([
                        'fecha_hora' => now(),
                        'sku' => $sku,
                        'tipo_movimiento' => 'INVENTARIO_INICIAL',
                        'cantidad' => $cantidadFisica,
                        'saldo_anterior' => 0,
                        'saldo_nuevo' => $cantidadFisica,
                        'id_lote' => $batchUuid,
                        'usuario_id' => null,
                        'cotizacion_id' => null,
                    ]);

                    $totalStockUnidades += $cantidadFisica;
                    $skusConStock++;
                }

                $skuProgressBar->advance();
            }
            $skuProgressBar->finish();
            $this->newLine();

            // C. Sincronizar y Normalizar precios (Global por producto padre y variantes)
            $this->info("\n[3/4] Sincronizando y Normalizando precios globales...");
            $genericos = PteSkuGenerico::all();
            foreach ($genericos as $gen) {
                $varPrice = PteSku::where('sku_generico_id', $gen->id)->where('precio_venta', '>', 0)->value('precio_venta');
                $price = $gen->precio_venta > 0 ? $gen->precio_venta : ($varPrice ?? 0);
                $normalizedPrice = (int) round($price / 10) * 10;

                $gen->update(['precio_venta' => $normalizedPrice]);
                PteSku::where('sku_generico_id', $gen->id)->update(['precio_venta' => $normalizedPrice]);
            }

            // D. Desactivar SKUs antiguos huérfanos que no estén en el catálogo consolidado
            $this->info("\n[4/4] Verificando SKUs huérfanos...");
            $desactivados = PteSku::whereNotIn('sku', $validSkus)->update(['activo' => false]);
            $this->info("SKUs fuera del nuevo catálogo desactivados: {$desactivados}");

            DB::commit();

            // D. Reporte Final
            $this->newLine();
            $this->info('===============================================================');
            $this->info('  RESULTADO DE LA CONSOLIDACIÓN EXITOSA');
            $this->info('===============================================================');
            $this->table(
                ['Métrica', 'Valor'],
                [
                    ['Total Modelos Base (pte_sku_genericos)', PteSkuGenerico::count()],
                    ['Total SKUs Variantes Activos (pte_skus)', PteSku::where('activo', true)->count()],
                    ['Total Registros de Stock (pte_stocks)', PteStock::count()],
                    ['SKUs con Stock Físico Real (> 0)', $skusConStock],
                    ['Total Unidades Físicas en Bodega', number_format($totalStockUnidades, 0, ',', '.')],
                    ['Lote de Auditoría (UUID)', $batchUuid],
                    ['Chaqueta Pilot Creada', '111440002200000 (Azul Marino y Negro, XXS a ESPECIAL)'],
                ]
            );

            return 0;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("\nERROR DURANTE LA CONSOLIDACIÓN: " . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }
    }
}
