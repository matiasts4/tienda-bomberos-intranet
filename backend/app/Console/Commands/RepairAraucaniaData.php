<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RepairAraucaniaData extends Command
{
    protected $signature = 'app:repair-araucania';
    protected $description = 'Fix region IDs for Araucania bodies incorrectly assigned to Coquimbo';

    public function handle()
    {
        $this->info('Starting database inconsistency repair for Araucanía...');

        $corrections = [
            'Temuco' => 9, 'Lautaro' => 9, 'Gorbea' => 9, 'Loncoche' => 9, 'Nueva Imperial' => 9,
            'Pitrufquén' => 9, 'Vilcún' => 9, 'Cherquenco' => 9, 'Cunco' => 9, 'Freire' => 9,
            'Villarrica' => 9, 'Angol' => 9, 'Los Sauces' => 9, 'Capitán Pastene' => 9,
            'Pucón' => 9, 'Traiguén' => 9, 'Carahue' => 9, 'Victoria' => 9, 'Galvarino' => 9,
            'Toltén' => 9, 'Renaico' => 9, 'Puerto Saavedra' => 9, 'Curacautín' => 9,
            'Quitratúe' => 9, 'Collipulli' => 9, 'Purén' => 9, 'Lastarria' => 9, 'Ercilla' => 9,
            'Lumaco' => 9, 'LicanRay' => 9, 'Curarrehue' => 9, 'Lonquimay' => 9, 'Perquenco' => 9,
            'Teodoro Schmidt' => 9, 'Melipeuco' => 9, 'Chol-Chol' => 9,
            'San Fabián de Alico' => 16,
            'Hualañé' => 7,
        ];

        $tablesWithClienteId = ['contactos', 'cotizacion_imports', 'cotizaciones', 'despachos', 'oportunidades'];

        DB::beginTransaction();
        try {
            Schema::disableForeignKeyConstraints();

            foreach ($corrections as $name => $newRegionId) {
                $cuerpo = DB::table('cuerpos')->where('nombre', $name)->first();
                if (!$cuerpo) {
                    $this->warn("Body not found: $name. Skipping.");
                    continue;
                }

                $oldCuerpoId = (string)$cuerpo->id;
                $socioNum = substr($oldCuerpoId, 2, 3);
                $newCuerpoId = str_pad($newRegionId, 2, '0', STR_PAD_LEFT) . $socioNum . '00';

                if ($oldCuerpoId === $newCuerpoId) {
                    // Update only region if IDs match (unlikely here but safe)
                    DB::table('cuerpos')->where('id', $oldCuerpoId)->update(['region_id' => $newRegionId]);
                    DB::table('clientes')->where('id', $oldCuerpoId)->update(['region_id' => $newRegionId]);
                    continue;
                }

                $this->info("Moving $name (ID $oldCuerpoId) to Region $newRegionId (ID $newCuerpoId)");

                // 1. Update Companias
                $companias = DB::table('companias')->where('cuerpo_id', $oldCuerpoId)->get();
                foreach ($companias as $cia) {
                    $oldCiaId = (string)$cia->id;
                    $ciaNum = substr($oldCiaId, 5, 2);
                    $newCiaId = str_pad($newRegionId, 2, '0', STR_PAD_LEFT) . $socioNum . $ciaNum;

                    DB::table('companias')->where('id', $oldCiaId)->update(['id' => $newCiaId, 'cuerpo_id' => $newCuerpoId]);
                    DB::table('clientes')->where('id', $oldCiaId)->update([
                        'id' => $newCiaId, 'region_id' => $newRegionId, 'cuerpo_id' => $newCuerpoId, 'compania_id' => $newCiaId
                    ]);

                    foreach ($tablesWithClienteId as $table) {
                        if (Schema::hasTable($table)) {
                            DB::table($table)->where('cliente_id', $oldCiaId)->update(['cliente_id' => $newCiaId]);
                        }
                    }
                }

                // 2. Update Cuerpo and Cliente
                DB::table('cuerpos')->where('id', $oldCuerpoId)->update(['id' => $newCuerpoId, 'region_id' => $newRegionId]);
                DB::table('clientes')->where('id', $oldCuerpoId)->update(['id' => $newCuerpoId, 'region_id' => $newRegionId, 'cuerpo_id' => $newCuerpoId]);

                foreach ($tablesWithClienteId as $table) {
                    if (Schema::hasTable($table)) {
                        DB::table($table)->where('cliente_id', $oldCuerpoId)->update(['cliente_id' => $newCuerpoId]);
                    }
                }
            }

            Schema::enableForeignKeyConstraints();
            DB::commit();
            $this->info('Repair completed successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Schema::enableForeignKeyConstraints();
            $this->error('Error: ' . $e->getMessage());
        }
    }
}
