<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MpColoresSeeder extends Seeder
{
    public function run()
    {
        Schema::disableForeignKeyConstraints();
        DB::table('mp_colores')->truncate();
        Schema::enableForeignKeyConstraints();

        $colores = [
            ['nombre' => '00 - Generico',              'hex' => '#CCCCCC'],
            ['nombre' => '10 - Blanco',                'hex' => '#FFFFFF'],
            ['nombre' => '20 - Amarillo Fluor / Negro','hex' => '#DFFF00'],
            ['nombre' => '21 - Amar. Fluor / Azul Mar','hex' => '#DFFF00'], 
            ['nombre' => '30 - Azul',                  'hex' => '#0000FF'],
            ['nombre' => '31 - Azul Marino',           'hex' => '#000080'],
            ['nombre' => '32 - Azulino',               'hex' => '#4169E1'],
            ['nombre' => '33 - Azulino / Negro',       'hex' => '#4169E1'],
            ['nombre' => '35 - Celeste',               'hex' => '#87CEEB'],
            ['nombre' => '36 - Azul Mar EST. Naranjo', 'hex' => '#000080'],
            ['nombre' => '37 - Azul Mar EST. Rojo',    'hex' => '#000080'],
            ['nombre' => '40 - Beige',                 'hex' => '#F5F5DC'],
            ['nombre' => '50 - Verde',                 'hex' => '#008000'],
            ['nombre' => '60 - Rojo',                  'hex' => '#FF0000'],
            ['nombre' => '61 - Rojo fluor',            'hex' => '#FF4500'],
            ['nombre' => '62 - Rojo fluor / Negro',    'hex' => '#FF4500'],
            ['nombre' => '64 - Ocre',                  'hex' => '#CC7722'],
            ['nombre' => '65 - Naranjo',               'hex' => '#FFA500'],
            ['nombre' => '66 - Naranjo Fluor',         'hex' => '#FF5E00'],
            ['nombre' => '67 - Naranjo Fluor / Negro', 'hex' => '#FF5E00'],
            ['nombre' => '70 - Gris',                  'hex' => '#808080'],
            ['nombre' => '80 - Grafito',               'hex' => '#36454F'],
            ['nombre' => '90 - Negro',                 'hex' => '#000000'],
            ['nombre' => '91 - Negro / Azulino',       'hex' => '#000000'],
            ['nombre' => '92 - Negro / Blanco',        'hex' => '#000000'],
            ['nombre' => '93 - Negro / Naranjo fluor', 'hex' => '#000000'],
            ['nombre' => '94 - Negro EST. Naranjo',    'hex' => '#000000'],
            ['nombre' => '95 - Negro EST. Verde',      'hex' => '#000000'],
        ];

        foreach ($colores as $c) {
            $partes = explode('-', $c['nombre'], 2);
            
            $nombreLimpio = count($partes) > 1 ? trim($partes[1]) : trim($c['nombre']);

            DB::table('mp_colores')->insert([
                'nombre' => $nombreLimpio, 
                'codigo_hex' => $c['hex'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}