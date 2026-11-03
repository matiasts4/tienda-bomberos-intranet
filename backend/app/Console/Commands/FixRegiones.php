<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FixRegiones extends Command
{
    protected $signature = 'app:fix-regiones';
    protected $description = 'MASTER REPAIR: Sincroniza regiones y cuerpos en cascada utilizando bomberos_mapped.json como Verdad Absoluta.';

    public function handle()
    {
        $this->info('Iniciando Reparación y Sincronización DB vs JSON...');

        // 1. Cargar el archivo JSON como fuente de la verdad
        $jsonPath = base_path('bomberos_mapped.json');
        if (!file_exists($jsonPath)) {
            $this->error('No se encontro el archivo bomberos_mapped.json en la raiz.');
            return;
        }

        $jsonData = json_decode(file_get_contents($jsonPath), true);
        if (!$jsonData) {
            $this->error('bomberos_mapped.json es invalido.');
            return;
        }

        // Construir mapa: Nombre Cuerpo => Region Oficial
        $cuerpoToRegion = [];
        foreach ($jsonData as $row) {
            $nombre = trim($row['Cuerpo de Bomberos']);
            $region = (int) $row['Region'];
            $cuerpoToRegion[$nombre] = $region;
        }

        $tablesWithClienteId = ['contactos', 'cotizacion_imports', 'cotizaciones', 'despachos', 'oportunidades'];

        DB::beginTransaction();
        try {
            Schema::disableForeignKeyConstraints();

            // 2. Restaurar ABSOLUTAMENTE los Nombres Legales de las Regiones
            $this->info('Restaurando nombres oficiales de Regiones en la BD...');
            $regionNames = [
                1 => 'Región de Tarapacá', 2 => 'Región de Antofagasta', 3 => 'Región de Atacama', 4 => 'Región de Coquimbo',
                5 => 'Región de Valparaíso', 6 => 'Región del Libertador Bernardo O\'higgins', 7 => 'Región del Maule',
                8 => 'Región del Bío Bío', 9 => 'Región de la Araucanía', 10 => 'Región de los Lagos',
                11 => 'Región de Aysén del General Carlos Ibañez del Campo', 12 => 'Región de Magallanes y la Antártica Chilena',
                13 => 'Región Metropolitana', 14 => 'Región de los Ríos', 15 => 'Región de Arica', 16 => 'Región de Ñuble'
            ];
            foreach ($regionNames as $id => $name) {
                DB::table('regiones')->updateOrInsert(['id' => $id], ['nombre' => $name]);
            }

            // 3. Iterar cada Cuerpo en la base de datos y auditar su Region + ID
            $this->info('Auditando Cuerpos de Bomberos y sincronizando Datos y FKs...');
            $cuerposDb = DB::table('cuerpos')->get();
            $updatedCount = 0;

            foreach ($cuerposDb as $cuerpo) {
                // Encontrar su region real en el JSON
                $nombreDb = trim($cuerpo->nombre);
                
                // Intento exacto
                $newRegionId = $cuerpoToRegion[$nombreDb] ?? null;

                // Si no es exacto, buscar ignorando mayusculas y tildes rudimentariamente
                if (!$newRegionId) {
                    foreach ($cuerpoToRegion as $nombreJson => $regJson) {
                        if (strtolower(str_replace(['á','é','í','ó','ú'], ['a','e','i','o','u'], $nombreJson)) === 
                            strtolower(str_replace(['á','é','í','ó','ú'], ['a','e','i','o','u'], $nombreDb))) {
                            $newRegionId = $regJson;
                            break;
                        }
                    }
                }

                if (!$newRegionId) {
                    $this->warn("Ignorando: $nombreDb (no encontrado en JSON)");
                    continue;
                }

                $socioNum = str_pad($cuerpo->numero_socio, 3, '0', STR_PAD_LEFT);
                $newCuerpoId = str_pad($newRegionId, 2, '0', STR_PAD_LEFT) . $socioNum . '00';
                $oldCuerpoId = (string)$cuerpo->id;

                if ($oldCuerpoId === $newCuerpoId && $cuerpo->region_id == $newRegionId) {
                    // ID e integridad ya correctas
                    continue;
                }

                $this->info("Corrigiendo: $nombreDb -> Region $newRegionId | ID: $oldCuerpoId -> $newCuerpoId");

                // Actualizar Compañías (hijas) de este Cuerpo
                $companias = DB::table('companias')->where('cuerpo_id', $oldCuerpoId)->get();
                foreach ($companias as $cia) {
                    $oldCiaId = (string)$cia->id;
                    $ciaSuffix = substr($oldCiaId, -2);
                    $newCiaId = str_pad($newRegionId, 2, '0', STR_PAD_LEFT) . $socioNum . $ciaSuffix;

                    // Update parent reference and self ID in Companias
                    DB::table('companias')->where('id', $oldCiaId)->update([
                        'id' => $newCiaId, 
                        'cuerpo_id' => $newCuerpoId
                    ]);

                    // Update in Clientes (poly-table)
                    DB::table('clientes')->where('id', $oldCiaId)->update([
                        'id' => $newCiaId, 
                        'region_id' => $newRegionId, 
                        'cuerpo_id' => $newCuerpoId, 
                        'compania_id' => $newCiaId
                    ]);

                    // Update all cross references
                    foreach ($tablesWithClienteId as $table) {
                        if (Schema::hasTable($table)) {
                            DB::table($table)->where('cliente_id', $oldCiaId)->update(['cliente_id' => $newCiaId]);
                        }
                    }
                }

                // Actualizar el Cuerpo en sí mismo
                DB::table('cuerpos')->where('id', $oldCuerpoId)->update([
                    'id' => $newCuerpoId, 
                    'region_id' => $newRegionId
                ]);

                DB::table('clientes')->where('id', $oldCuerpoId)->update([
                    'id' => $newCuerpoId, 
                    'region_id' => $newRegionId, 
                    'cuerpo_id' => $newCuerpoId
                ]);

                // Actualizar cross references de nivel cuerpo
                foreach ($tablesWithClienteId as $table) {
                    if (Schema::hasTable($table)) {
                        DB::table($table)->where('cliente_id', $oldCuerpoId)->update(['cliente_id' => $newCuerpoId]);
                    }
                }

                $updatedCount++;
            }

            Schema::enableForeignKeyConstraints();
            DB::commit();
            
            // Refrescar caché de aplicación internamente para que no queden basuras temporales
            $this->callSilent('cache:clear');
            
            $this->info("¡REPARACION MAESTRA COMPLETADA! {$updatedCount} Cuerpos (y sus dependencias) fueron realineados exitosamente.");

        } catch (\Exception $e) {
            DB::rollBack();
            Schema::enableForeignKeyConstraints();
            $this->error('ERROR FATAL: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        }
    }
}
