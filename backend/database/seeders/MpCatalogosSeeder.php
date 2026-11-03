<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MpTipo;
use App\Models\MpUnidad;
use App\Models\MpAncho;

class MpCatalogosSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Creando catálogos básicos de Materia Prima...');

        // 1. Tipos de Material
        $tipos = [
            'Tela',
            'Insumo',
            'Hilo',
            'Etiqueta',
            'Cierre'
        ];
        foreach ($tipos as $nombre) {
            MpTipo::firstOrCreate(['nombre' => $nombre]);
        }

        // 2. Unidades
        $unidades = [
            ['nombre' => 'Metros', 'abreviacion' => 'Mts'],
            ['nombre' => 'Kilos', 'abreviacion' => 'Kg'],
            ['nombre' => 'Unidades', 'abreviacion' => 'Ud'],
            ['nombre' => 'Litros', 'abreviacion' => 'Lts'],
        ];
        foreach ($unidades as $u) {
            MpUnidad::firstOrCreate(['nombre' => $u['nombre']], $u);
        }

        // 3. Anchos
        $anchos = ['1.50m', '1.60m', '1.80m', 'N/A'];
        foreach ($anchos as $medida) {
            MpAncho::firstOrCreate(['medida' => $medida]);
        }
    }
}
