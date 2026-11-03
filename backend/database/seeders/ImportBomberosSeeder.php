<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use App\Models\Contacto;
use App\Models\Cuerpo;
use App\Models\Compania;
use Carbon\Carbon;

class ImportBomberosSeeder extends Seeder
{
    /**
     * Crea o actualiza un Contacto por cargo (Superintendente/Comandante) dentro de un Cuerpo.
     */
    private function upsertContactoDeCuerpo(string $cuerpoId, string $cargo, ?string $nombre): void
    {
        if (!$nombre) {
            return;
        }

        $contacto = Contacto::where('cargo', $cargo)
            ->whereHas('cuerpos', fn($q) => $q->where('cuerpos.id', $cuerpoId))
            ->first();

        if ($contacto) {
            $contacto->update(['nombre' => $nombre]);
            return;
        }

        $contacto = Contacto::create(['nombre' => $nombre, 'cargo' => $cargo]);
        $contacto->cuerpos()->syncWithoutDetaching([$cuerpoId]);
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jsonPath = base_path('bomberos_mapped.json');

        if (!File::exists($jsonPath)) {
            $this->command->error("Archivo JSON no encontrado en: {$jsonPath}");
            return;
        }

        $data = json_decode(File::get($jsonPath), true);

        DB::beginTransaction();
        try {
            foreach ($data as $row) {
                $logicRegionId = (int) $row['Region'];
                $numeroSocio = (int) (float) $row['Numero de Socio'];
                $cuerpoNombre = $row['Cuerpo de Bomberos'];
                $rut = $row['RUT'] === 'None' || $row['RUT'] === 'NaT' ? null : $row['RUT'];

                $dbRegionId = $logicRegionId;

                // Parse date
                $fechaFundacion = null;
                if (!empty($row['Fecha de Fundacion']) && $row['Fecha de Fundacion'] !== 'NaT' && $row['Fecha de Fundacion'] !== 'None') {
                    try {
                        $fechaFundacion = Carbon::createFromFormat('d-m-Y', trim($row['Fecha de Fundacion']))->format('Y-m-d');
                    } catch (\Exception $e) {
                        try {
                            $fechaFundacion = Carbon::parse(trim($row['Fecha de Fundacion']))->format('Y-m-d');
                        } catch (\Exception $e2) {
                            $fechaFundacion = null;
                        }
                    }
                }

                // Format ID: Region (2) + Socio (3) + 00 (2)
                $cuerpoId = str_pad($logicRegionId, 2, '0', STR_PAD_LEFT) .
                    str_pad($numeroSocio, 3, '0', STR_PAD_LEFT) .
                    '00';

                // 1. Create or Update Cuerpo
                Cuerpo::updateOrCreate(
                    ['id' => $cuerpoId],
                    [
                        'region_id' => $dbRegionId,
                        'nombre' => $cuerpoNombre,
                        'numero_socio' => $numeroSocio,
                        'rut' => $rut,
                        'fecha_fundacion' => $fechaFundacion,
                        'direccion' => $row['Direccion'] === 'None' ? null : $row['Direccion'],
                        'telefono' => $row['Telefono'] === 'None' ? null : $row['Telefono'],
                        'numero_companias' => $row['Numero de Companias'] ?? 0,
                        'logo_url' => $row['Logo Path'] === 'None' ? null : $row['Logo Path'],
                    ]
                );

                // 2. Create Contacts (Superintendente/Comandante) vinculados al Cuerpo
                $superName = $row['Superintendente'] !== 'None' ? trim($row['Superintendente']) : null;
                $this->upsertContactoDeCuerpo($cuerpoId, 'Superintendente', $superName);

                $comandName = $row['Comandante'] !== 'None' ? trim($row['Comandante']) : null;
                $this->upsertContactoDeCuerpo($cuerpoId, 'Comandante', $comandName);

                // 3. Create or Update Companias
                $numCompanias = (int) ($row['Numero de Companias'] ?? 0);
                if ($numCompanias > 0) {
                    for ($i = 1; $i <= $numCompanias; $i++) {
                        $companiaNumStr = str_pad($i, 2, '0', STR_PAD_LEFT);
                        $companiaId = str_pad($logicRegionId, 2, '0', STR_PAD_LEFT) .
                            str_pad($numeroSocio, 3, '0', STR_PAD_LEFT) .
                            $companiaNumStr;

                        Compania::updateOrCreate(
                            ['id' => $companiaId],
                            [
                                'cuerpo_id' => $cuerpoId,
                                'nombre' => $i . 'ª Compañía ' . $cuerpoNombre,
                                'numero' => $i,
                            ]
                        );
                    }

                    // Special Brigade (99)
                    $brigadaId = str_pad($logicRegionId, 2, '0', STR_PAD_LEFT) .
                        str_pad($numeroSocio, 3, '0', STR_PAD_LEFT) .
                        '99';

                    Compania::updateOrCreate(
                        ['id' => $brigadaId],
                        [
                            'cuerpo_id' => $cuerpoId,
                            'nombre' => 'Brigada Especializada ' . $cuerpoNombre,
                            'numero' => 99,
                        ]
                    );
                }
            }
            DB::commit();
            $this->command->info('Proceso completado correctamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('Error durante la importación: ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
        }
    }
}
