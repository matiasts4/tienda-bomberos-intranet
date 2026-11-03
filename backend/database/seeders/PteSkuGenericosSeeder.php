<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PteSkuGenericosSeeder extends Seeder
{
    public function run(): void
    {
        // Leer el Excel generado
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load(base_path('GENERICOS_NUEVOS.xlsx'));
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestRow();

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('pte_sku_genericos')->truncate();

        $now = now();
        $rows = [];

        for ($row = 2; $row <= $highestRow; $row++) {
            $sku = $sheet->getCell('A' . $row)->getValue();
            $nombre = $sheet->getCell('B' . $row)->getValue();
            $imagen = $sheet->getCell('C' . $row)->getValue();
            $precio = $sheet->getCell('D' . $row)->getValue();

            if (!$sku || !$nombre) continue;

            // Parsear catálogos desde el SKU (15 dígitos)
            // U(1) O(1) G(1) F(1) SS(2) TT(2) FF(2) G(1) CC(2) TT(2)
            $familiaId = (int) substr($sku, 3, 1);
            $subfamiliaId = (int) substr($sku, 4, 2);
            $tipoId = (int) substr($sku, 6, 2);
            $formatoId = (int) substr($sku, 8, 2);
            $generoId = (int) substr($sku, 10, 1);

            $rows[] = [
                'sku_base' => $sku,
                'nombre' => $nombre,
                'imagen_url' => $imagen,
                'precio_venta' => is_numeric($precio) ? $precio : null,
                'familia_id' => $familiaId,
                'subfamilia_id' => $subfamiliaId,
                'familia_tipo_id' => $tipoId,
                'familia_formato_id' => $formatoId,
                'genero_id' => $generoId,
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 50) as $chunk) {
            DB::table('pte_sku_genericos')->insert($chunk);
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->command->info(count($rows) . " SKUs genéricos insertados en pte_sku_genericos.");
    }
}
