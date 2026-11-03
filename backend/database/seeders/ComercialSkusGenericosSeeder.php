<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use Illuminate\Support\Facades\DB;

class ComercialSkusGenericosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [
            // BLUSAS: Femenino (género 2) -> 1111113301 + 2 + 0000
            ["sku" => "111111330120000", "nombre" => "Blusa Oxford ML", "imagen_url" => "/products/blusa-oxford-mujer-white-front.png"],
            ["sku" => "111111330120000", "nombre" => "Blusa Oxford MC", "imagen_url" => "/products/blusa-oxford-mujer-white-front.png"],
            // CAMISAS: Masculino (género 1) -> 1111123302 + 1 + 0000
            ["sku" => "111112330210000", "nombre" => "Camisa Oxford ML", "imagen_url" => "/products/camisa-oxford-white-front.png"],
            ["sku" => "111112330210000", "nombre" => "Camisa Oxford MC", "imagen_url" => "/products/camisa-oxford-white-front.png"],
            
            // POLERAS: Unisex (género 0) -> 1112203xxx + 0 + 0000
            ["sku" => "111220300100000", "nombre" => "Polera Polo MC",    "imagen_url" => "/products/polera-white-front.png"],
            ["sku" => "111220300200000", "nombre" => "Polera Polo ML",    "imagen_url" => "/products/polera-polo-mangalarga-blanca-front.png"],
            ["sku" => "111220310100000", "nombre" => "Polera Casual MC",  "imagen_url" => "/products/polera-casual-white-front.png"],
            ["sku" => "111220310200000", "nombre" => "Polera Casual ML",  "imagen_url" => "/products/polera-casual-white-front.png"],
            ["sku" => "111220320100000", "nombre" => "Polera Pique MC",   "imagen_url" => "/products/polera-pique-white-front.png"],
            ["sku" => "111220320200000", "nombre" => "Polera Pique ML",   "imagen_url" => "/products/polera-pique-white-front.png"],
            ["sku" => "111220340100000", "nombre" => "Polera Urbana MC",  "imagen_url" => "/products/polera-white-front.png"],
            ["sku" => "111220340200000", "nombre" => "Polera Urbana ML",  "imagen_url" => "/products/polera-white-front.png"],
            
            // POLARES / POLERONES: Unisex (género 0)
            ["sku" => "111330310200000", "nombre" => "Polar Clasico",    "imagen_url" => "/products/polar-white-front.png"],
            ["sku" => "111330840200000", "nombre" => "Polar Tactico",    "imagen_url" => "/products/polar-white-front.png"],
            ["sku" => "111331310200000", "nombre" => "Poleron Capucha",  "imagen_url" => "/products/poleron-canguro-white-front.png"],
            ["sku" => "111331600200000", "nombre" => "Poleron Cuartel",  "imagen_url" => "/products/poleron-white-front.png"],
            ["sku" => "111331300200000", "nombre" => "Poleron Polo",     "imagen_url" => "/products/poleron-white-front.png"],
            ["sku" => "111331340200000", "nombre" => "Poleron Urbano",   "imagen_url" => "/products/poleron-urbano-white-front.png"],
            
            // CHAQUETAS: Unisex (género 0) salvo Comandante (Masculino=2)
            ["sku" => "111440700200000", "nombre" => "Chaqueta Antiacido",          "imagen_url" => "/products/chaqueta-cortaviento-front-white.png"],
            ["sku" => "111440500200000", "nombre" => "Chaqueta Pluma",              "imagen_url" => "/products/chaqueta-pluma-front-white.png"],
            ["sku" => "111440850200000", "nombre" => "Chaqueta Softshell",          "imagen_url" => "/products/chaqueta-softshell-white-front.png"],
            ["sku" => "111440870220000", "nombre" => "Chaqueta Comandante",         "imagen_url" => "/products/chqueta-comandante-front-white.png"],
            ["sku" => "111444000000000", "nombre" => "Chaqueta Geologo",            "imagen_url" => "/products/chaqueta-geologo-front-white.png"],
            ["sku" => "111441210200000", "nombre" => "Cortaviento",                 "imagen_url" => "/products/chaqueta-cortaviento-front-white.png"],
            ["sku" => "111442210200000", "nombre" => "Chaqueta Reversible",         "imagen_url" => "/products/chaqueta-softshell-white-front.png"],
            ["sku" => "111443230200000", "nombre" => "Parka 3 en 1",               "imagen_url" => "/products/chaqueta-softshell-white-front.png"],
            ["sku" => "111443210200000", "nombre" => "Parka Impermeable",           "imagen_url" => "/products/chaqueta-softshell-white-front.png"],
            ["sku" => "111440720200000", "nombre" => "Chaqueta Rescate Vehicular",  "imagen_url" => "/products/chaqueta-rescate-vehicular-white-front.png"],
            ["sku" => "111440800200000", "nombre" => "Chaqueta Rescate Forestal",   "imagen_url" => "/products/chaqueta-rescate-forestal-front.png"],
            
            // PANTALONES: Unisex (género 0)
            ["sku" => "111550310400000", "nombre" => "Pantalon Cargo",     "imagen_url" => "/products/pantalon-white-front.png"],
            ["sku" => "111550700400000", "nombre" => "Pantalon Antiacido", "imagen_url" => "/products/pantalon-white-front.png"],
            ["sku" => "111550810400000", "nombre" => "Pantalon Gersa",     "imagen_url" => "/products/pantalon-uniforme-front.png"],
            ["sku" => "111550820400000", "nombre" => "Pantalon Hazmat",    "imagen_url" => "/products/pantalon-uniforme-front.png"],
            ["sku" => "111550710400000", "nombre" => "Pantalon Multirol",  "imagen_url" => "/products/pantalon-uniforme-front.png"],
            ["sku" => "111550840400000", "nombre" => "Pantalon Tactico",   "imagen_url" => "/products/pantalon-white-front.png"],
            ["sku" => "111550830400000", "nombre" => "Pantalon Usar",      "imagen_url" => "/products/pantalon-uniforme-front.png"],
            ["sku" => "111550600400000", "nombre" => "Pantalon Uniforme",  "imagen_url" => "/products/pantalon-uniforme-front.png"],
            
            // KITS / TRAJES / ACCESORIOS: Unisex (género 0)
            ["sku" => "111660600000000", "nombre" => "Kit Cuartel - 3 prendas",  "imagen_url" => "/products/poleron-white-front.png"],
            ["sku" => "111660610000000", "nombre" => "Kit Cuartel full-5 prendas","imagen_url" => "/products/poleron-white-front.png"],
            ["sku" => "111661700000000", "nombre" => "Traje Antiacido",          "imagen_url" => "/products/uniforme-usar-white-front.png"],
            ["sku" => "111770400000000", "nombre" => "Quepi",                     "imagen_url" => "/products/quepi white front.png"],
            ["sku" => "111770400220000", "nombre" => "Quepi Comandante",          "imagen_url" => "/products/quepi white front.png"],
            ["sku" => "111771451900000", "nombre" => "Banano Institucional",      "imagen_url" => "/products/banano_white_front.png"],
            ["sku" => "111771461900000", "nombre" => "Mochila Institucional",     "imagen_url" => "/products/mochila-white-front.png"],
        ];

        // Formatear timestamps
        $dataToInsert = array_map(function ($item) {
            $item['created_at'] = now();
            $item['updated_at'] = now();
            return $item;
        }, $data);

        // Limpieza previa
        DB::table('comercial_skus_genericos')->truncate();

        // Inserta en pequeños lotes
        foreach (array_chunk($dataToInsert, 100) as $chunk) {
            DB::table('comercial_skus_genericos')->upsert($chunk, ['sku'], ['nombre', 'imagen_url', 'updated_at']);
        }

        $this->command->info(count($dataToInsert) . " SKUs Genéricos insertados.");
    }
}
