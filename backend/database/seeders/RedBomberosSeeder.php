<?php

namespace Database\Seeders;

use App\Imports\RedBomberosImport;
use Illuminate\Database\Seeder;
use Maatwebsite\Excel\Facades\Excel;

class RedBomberosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $filePath = base_path('RedBomberos.xlsx'); // File is in backend dir

        // Verify path
        if (!file_exists($filePath)) {
            $this->command->error("File not found at: $filePath");
            return;
        }

        $this->command->info("Importing from $filePath...");
        Excel::import(new RedBomberosImport, $filePath);
        $this->command->info("Import completed.");

        // --- Process CSV for Enrichment ---
        $csvPath = base_path('bomberos_chile.csv');
        if (!file_exists($csvPath)) {
            $this->command->warn("CSV file not found at: $csvPath. Skipping enrichment.");
            return;
        }

        $this->command->info("Enriching data from $csvPath...");

        // Open the file
        if (($handle = fopen($csvPath, "r")) !== FALSE) {
            // Get comments/headers
            $header = fgetcsv($handle, 1000, ",");

            // Map headers to indices for easier access if order changes (optional, but robust)
            // Expected: ID,Cuerpo de Bomberos,URL,Logo Path,RUT,Numero de Socio,Fecha de Fundacion,Direccion,Telefono,Superintendente,Comandante,Numero de Companias,status

            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                // Adjust indices based on the CSV provided by user
                // 0: ID
                // 1: Cuerpo de Bomberos (e.g., "Arica")
                // 2: URL
                // 3: Logo Path

                $nombreCuerpo = $data[1] ?? null;
                $logoPath = trim($data[3] ?? '');
                $rut = $data[4] ?? null;
                $fechaFundacionRaw = $data[6] ?? null;
                $direccion = $data[7] ?? null;
                $telefono = $data[8] ?? null;
                $superintendente = $data[9] ?? null;
                $comandante = $data[10] ?? null;
                $numCompanias = $data[11] ?? null; // Can be numeric string

                if (!$nombreCuerpo)
                    continue;

                // Normalize Name to match DB
                // DB naming convention from Import: "CB " + clean name
                // CSV name: "Arica", "Iquique", etc.
                // So verify if "CB Arica" exists.

                $dbName = 'CB ' . trim($nombreCuerpo);

                $cliente = \App\Models\Cliente::where('nombre_empresa', $dbName)->first();

                if ($cliente) {
                    // Update fields
                    // Parse Date: 12-04-1912 (d-m-Y) -> Y-m-d
                    $fechaFundacion = null;
                    if ($fechaFundacionRaw) {
                        try {
                            $dateObj = \DateTime::createFromFormat('d-m-Y', $fechaFundacionRaw);
                            if ($dateObj) {
                                $fechaFundacion = $dateObj->format('Y-m-d');
                            }
                        } catch (\Exception $e) {
                            // ignore invalid date
                        }
                    }

                    // Prepare update array for Cliente
                    $updateData = [];
                    if ($rut)
                        $updateData['rut_empresa'] = $rut;
                    if ($fechaFundacion)
                        $updateData['fecha_fundacion'] = $fechaFundacion;
                    if ($direccion)
                        $updateData['direccion'] = $direccion;
                    if ($numCompanias && is_numeric($numCompanias))
                        $updateData['numero_companias'] = (int) $numCompanias;

                    if ($telefono)
                        $updateData['telefono'] = $telefono;

                    if ($logoPath && filter_var($logoPath, FILTER_VALIDATE_URL) !== false) {
                        $updateData['logo_url'] = $logoPath;
                    }

                    try {
                        $cliente->update($updateData);

                        // Update Contacts in the new normalized table
                        if ($superintendente) {
                            \App\Models\Contacto::updateOrCreate(
                                ['cliente_id' => $cliente->id, 'cargo' => 'Superintendente'],
                                ['nombre' => trim($superintendente)]
                            );
                        }
                        if ($comandante) {
                            \App\Models\Contacto::updateOrCreate(
                                ['cliente_id' => $cliente->id, 'cargo' => 'Comandante'],
                                ['nombre' => trim($comandante)]
                            );
                        }
                    } catch (\Exception $e) {
                        $this->command->error("Failed to update {$cliente->nombre_empresa}: " . $e->getMessage());
                    }
                } else {
                    $this->command->warn("Cuerpo de Bomberos not found in DB: $dbName");
                }
            }
            fclose($handle);
        }
        $this->command->info("CSV Enrichment completed.");
    }
}
