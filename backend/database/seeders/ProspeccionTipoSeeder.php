<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProspeccionTipoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Mismos nombres que la migración 2026_10_21_100000_enriquecer_gestiones_de_prospeccion.
        $tipos = [
            ['slug' => 'no_contesta', 'nombre' => 'No contesta', 'color' => 'slate', 'icono' => 'phone-off', 'es_final' => false, 'score' => 0],
            ['slug' => 'buzon', 'nombre' => 'Dejé mensaje', 'color' => 'amber', 'icono' => 'phone-missed', 'es_final' => false, 'score' => 0],
            ['slug' => 'llamar_luego', 'nombre' => 'Reagendado sin contacto', 'color' => 'blue', 'icono' => 'clock', 'es_final' => false, 'score' => 5],
            ['slug' => 'agendar_seguimiento', 'nombre' => 'Seguimiento acordado', 'color' => 'orange', 'icono' => 'calendar', 'es_final' => false, 'score' => 20],
            ['slug' => 'derivado', 'nombre' => 'Derivado a otro contacto', 'color' => 'violet', 'icono' => 'forward', 'es_final' => true, 'score' => 10],
            ['slug' => 'numero_equivocado', 'nombre' => 'Número equivocado', 'color' => 'zinc', 'icono' => 'phone-off', 'es_final' => true, 'score' => 0],
            ['slug' => 'no_califica', 'nombre' => 'No interesado', 'color' => 'red', 'icono' => 'x-circle', 'es_final' => true, 'score' => -1],
            ['slug' => 'calificado', 'nombre' => 'Calificado', 'color' => 'emerald', 'icono' => 'star', 'es_final' => true, 'score' => 100],
        ];

        foreach ($tipos as $tipo) {
            DB::table('prospeccion_tipos')->updateOrInsert(
                ['slug' => $tipo['slug']],
                [
                    'nombre' => $tipo['nombre'],
                    'color' => $tipo['color'],
                    'icono' => $tipo['icono'],
                    'es_final' => $tipo['es_final'],
                    'score' => $tipo['score'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
