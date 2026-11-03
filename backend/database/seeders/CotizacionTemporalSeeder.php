<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PteTalla;
use App\Models\PteColor;
use App\Models\PteGenero;
use Illuminate\Support\Facades\DB;

class CotizacionTemporalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Data extracted from Cotizacion 11717-CB MATRIZ-PRODUCTO.csv
        $tallas = [
            "3XL",
            "4XL",
            "ESPECIAL",
            "Generico",
            "L",
            "M",
            "S",
            "XL",
            "XS",
            "XXL",
            "XXS"
        ];

        $colores = [
            "Ama Flur/ Negro",
            "Amarillo",
            "Amarillo fluor",
            "Azul /Negro",
            "Azul Marino",
            "Azulino",
            "Blanco",
            "Generico",
            "Gris",
            "Nar Flur/ Negro",
            "Naranjo",
            "Naranjo Fluor",
            "Negro",
            "Ocre",
            "Ocre/Negro",
            "Rojo",
            "Rojo/Negro",
            "Rosado",
            "Verde",
            "Verde Fluor"
        ];

        $generos = [
            "Generico",
            "Hombre",
            "Infantil",
            "Mujer",
            "Unisex"
        ];

        // Disable foreign key checks to allow truncation if needed
        // DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        // PteTalla::truncate();
        // PteColor::truncate();
        // PteGenero::truncate();
        // DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // However, using firstOrCreate is safer to preserve existing IDs if referenced
        foreach ($tallas as $t) {
            PteTalla::firstOrCreate(['nombre' => $t], ['is_activo' => true]);
        }

        foreach ($colores as $c) {
            PteColor::firstOrCreate(['nombre' => $c], ['is_activo' => true]);
        }

        foreach ($generos as $g) {
            PteGenero::firstOrCreate(['nombre' => $g], ['is_activo' => true]);
        }
    }
}
