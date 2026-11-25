<?php

namespace Database\Seeders;

use App\Support\RegionesChile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Deja cargadas las 16 regiones (y sus macrozonas) en una base nueva.
 * Sin ellas, ComunasSeeder se omite e ImportBomberosSeeder falla por la
 * llave foránea cuerpos.region_id.
 *
 * Solo inserta lo que falta: no modifica regiones ni zonas que ya existan
 * (por ejemplo, las cargadas desde la Red de Bomberos).
 */
class RegionesSeeder extends Seeder
{
    public function run(): void
    {
        $ahora = now();

        foreach (RegionesChile::ZONAS as $id => [$nombre, $codigo]) {
            if (! DB::table('zonas')->where('id', $id)->exists()) {
                DB::table('zonas')->insert([
                    'id' => $id, 'nombre' => $nombre, 'codigo' => $codigo,
                    'created_at' => $ahora, 'updated_at' => $ahora,
                ]);
            }
        }

        foreach (RegionesChile::NOMBRES as $id => $nombre) {
            if (! DB::table('regiones')->where('id', $id)->exists()) {
                DB::table('regiones')->insert([
                    'id' => $id, 'nombre' => $nombre, 'zona_id' => RegionesChile::ZONA_DE_REGION[$id],
                    'created_at' => $ahora, 'updated_at' => $ahora,
                ]);
            }
        }
    }
}
