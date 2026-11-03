<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CargosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $cargosTarget = [
            0 => 'BOMBERO',
            1 => 'SUPERINTENDENTE',
            2 => 'VICE SUPERINTENDENTE',
            3 => 'COMANDANTE',
            4 => 'COMANDANTE 1ro',
            5 => 'COMANDANTE 2do',
            6 => 'COMANDANTE 3ro',
            7 => 'COMANDANTE 4to',
            8 => 'CAPITAN',
            9 => 'CAPITAN 1ro',
            10 => 'CAPITAN 2do',
            11 => 'CAPITAN 3ro',
            12 => 'CAPITAN 4to',
            13 => 'DIRECTOR(A)',
            14 => 'DIRECTOR(A) 1ro',
            15 => 'DIRECTOR(A) 2do',
            16 => 'DIRECTOR(A) 3ro',
            17 => 'DIRECTOR(A) 4to',
            18 => 'TENIENTE',
            19 => 'TENIENTE 1ro',
            20 => 'TENIENTE 2do',
            21 => 'TENIENTE 3ro',
            22 => 'TENIENTE 4to',
            23 => 'TESORERO',
            24 => 'SECRETARIA',
            25 => 'AYUDANTES',
            26 => 'OTROS',
        ];

        // 1. Permitir ID = 0 en MySQL temporalmente
        \Illuminate\Support\Facades\DB::statement("SET SESSION sql_mode='NO_AUTO_VALUE_ON_ZERO';");
        
        // 2. Desactivar validación de FK temporalmente
        \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // 3. Rescatar todos los cargos antiguos
        $oldCargos = \App\Models\Cargo::all();

        // Mapa reverso para buscar el ID nuevo según su nombre
        $nameToNewId = array_flip($cargosTarget);

        // 4. Mapear cada viejo ID -> nuevo ID
        $mapping = [];
        foreach ($oldCargos as $old) {
            $nombreUpper = strtoupper(trim($old->nombre));
            
            if (isset($nameToNewId[$nombreUpper])) {
                $newId = $nameToNewId[$nombreUpper];
            } else {
                // Si había uno raro, lo pasamos a "OTROS" (26)
                $newId = 26; 
            }
            $mapping[$old->id] = $newId;
        }

        // 5. Actualizar los IDs en la tabla contactos
        // (Movemos primero a IDs temporales negativos para evitar choques en Unique/Primary temporalmente si aplicara, 
        // pero como es la tabla contactos, no tiene PRIMARY KEY en cargo_id)
        foreach ($mapping as $oldId => $newId) {
            if ($oldId !== $newId) {
                // Solo actualizamos contactos
                \Illuminate\Support\Facades\DB::table('contactos')
                    ->where('cargo_id', $oldId)
                    ->update(['cargo_id' => $newId]);
            }
        }

        // 6. Eliminar todos los cargos viejos
        \App\Models\Cargo::truncate();

        // 7. Insertar los nuevos cargos exactamente como pidieron
        foreach ($cargosTarget as $id => $cargo) {
            \Illuminate\Support\Facades\DB::table('cargos')->insert([
                'id' => $id,
                'nombre' => $cargo,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 8. Restaurar checks
        \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
}
