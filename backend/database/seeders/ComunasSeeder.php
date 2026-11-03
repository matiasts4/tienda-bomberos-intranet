<?php

namespace Database\Seeders;

use App\Support\ComunasChile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Repuebla el catálogo de comunas. La migración que crea la tabla ya lo hace,
 * este seeder existe para poder recargarlo a mano (por ejemplo, después de
 * correr `app:fix-regiones`, que reescribe la tabla `regiones`).
 */
class ComunasSeeder extends Seeder
{
    public function run(): void
    {
        $regiones = DB::table('regiones')->pluck('id')->map(fn ($id) => (int) $id)->all();

        if (empty($regiones)) {
            $this->command?->warn('No hay regiones cargadas: se omite el catálogo de comunas.');
            return;
        }

        $provincias = DB::table('provincias')
            ->get(['id', 'nombre', 'region_id'])
            ->keyBy(fn ($p) => ((int) $p->region_id) . '|' . $this->normalizar($p->nombre));

        $now = now();
        $creadas = 0;

        foreach (ComunasChile::planas() as $comuna) {
            if (!in_array($comuna['region_id'], $regiones, true)) {
                continue;
            }

            $provinciaId = $provincias->get($comuna['region_id'] . '|' . $this->normalizar($comuna['provincia']))->id ?? null;

            $existente = DB::table('comunas')
                ->where('region_id', $comuna['region_id'])
                ->where('nombre', $comuna['nombre'])
                ->first(['id', 'provincia_id']);

            if (!$existente) {
                DB::table('comunas')->insert([
                    'region_id' => $comuna['region_id'],
                    'nombre' => $comuna['nombre'],
                    'provincia_id' => $provinciaId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $creadas++;
                continue;
            }

            if ($provinciaId && !$existente->provincia_id) {
                DB::table('comunas')->where('id', $existente->id)->update([
                    'provincia_id' => $provinciaId,
                    'updated_at' => $now,
                ]);
            }
        }

        $this->command?->info("Catálogo de comunas al día ({$creadas} nuevas, " . DB::table('comunas')->count() . ' en total).');
    }

    private function normalizar(?string $valor): string
    {
        $valor = mb_strtolower(trim((string) $valor));

        return strtr($valor, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);
    }
}
