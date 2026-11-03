<?php

namespace App\Imports;

use App\Models\Cliente;
use App\Models\Provincia;
use App\Models\Region;
use App\Models\Zona;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class RedBomberosImport implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            // Debug:
            // \Illuminate\Support\Facades\Log::info('Row:', $row->toArray());

            // Normalize keys (sometimes headings have spaces or special chars)
            // Expected headers: CodZonal, Zona, Región, Provincia, NomCuerpo

            $codZonal = $row['codzonal'] ?? null;
            $nombreZona = $row['zona'] ?? null;
            $nombreRegion = $row['region'] ?? null;
            $nombreProvincia = $row['provincia'] ?? null;
            $nombreCuerpo = $row['nomcuerpo'] ?? null;
            $nomCia = $row['nomcia'] ?? null;

            if (!$nombreZona || !$nombreRegion || !$nombreProvincia || !$nombreCuerpo) {
                continue;
            }

            // --- Naming Logic ---
            $nombreFantasia = $nombreCuerpo; // Default

            // Normalize nomCia (trim, lowercase)
            $tipoEntidad = strtolower(trim((string) $nomCia));

            if ($tipoEntidad === 'cuerpo' || $tipoEntidad === 'cuerpo de bomberos') {
                // "CB Melipilla"
                // Avoid double "Cuerpo de Bomberos" if it's already in nomCuerpo?
                // Usually nomCuerpo is like "Cuerpo de Bomberos de Santiago" or just "Santiago"?
                // Let's assume nomCuerpo is the full name or location.
                // If nomCuerpo starts with "Cuerpo de Bomberos", we might want to shorten it to "CB"?
                // User said: "los cuerpo tengan el prefijo CB, por ejemplo CB Los Vilos"
                // If excel says "Los Vilos" -> "CB Los Vilos".
                // If excel says "Cuerpo de Bomberos de Los Vilos" -> "CB Los Vilos".

                $cleanName = str_ireplace('Cuerpo de Bomberos de ', '', $nombreCuerpo);
                $cleanName = str_ireplace('Cuerpo de Bomberos ', '', $cleanName);
                $nombreFantasia = 'CB ' . trim($cleanName);

            } elseif (is_numeric($tipoEntidad)) {
                // It's a company number
                $numero = (int) $tipoEntidad;
                $ordinal = $this->getOrdinal($numero);

                // "Cuarta compañia de Melipilla"
                // Again, clean the body name if needed
                $cleanName = str_ireplace('Cuerpo de Bomberos de ', '', $nombreCuerpo);
                $cleanName = str_ireplace('Cuerpo de Bomberos ', '', $cleanName);

                $nombreFantasia = "{$ordinal} Compañía de " . trim($cleanName);
            } else {
                // It's text like "Primera", "Segunda", etc. OR "Brigada..."

                // Fix potential encoding issues in NomCIA (e.g. Ã-a -> ía)
                // If it looks like double UTF-8 encoded, try to fix it.
                $nomCiaFixed = $this->fixEncoding($nomCia);

                $cleanName = str_ireplace('Cuerpo de Bomberos de ', '', $nombreCuerpo);
                $cleanName = str_ireplace('Cuerpo de Bomberos ', '', $cleanName);

                if (stripos($nomCiaFixed, 'Brigada') !== false) {
                    // It is a Brigada
                    // "Brigada Caburgua" (NomCIA) + " de Pucon" (Cuerpo)
                    // If NomCIA already has "de ...", we might be careful, but usually it's just the local name.
                    $nombreFantasia = "{$nomCiaFixed} de " . trim($cleanName);
                } else {
                    // "Primera Compañía de Talagante"
                    $ordinalName = mb_convert_case($nomCiaFixed, MB_CASE_TITLE, "UTF-8");
                    $nombreFantasia = "{$ordinalName} Compañía de " . trim($cleanName);
                }
            }

            // 1. Zonas
            $zona = Zona::firstOrCreate(
                ['nombre' => $nombreZona],
                ['codigo' => $codZonal]
            );

            // 2. Regiones
            $region = Region::firstOrCreate(
                ['nombre' => $nombreRegion],
                ['zona_id' => $zona->id]
            );

            // 3. Provincias
            $provincia = Provincia::firstOrCreate(
                ['nombre' => $nombreProvincia],
                ['region_id' => $region->id]
            );

            // 4. Clientes (Cuerpos / Compañías) are now handled by ImportBomberosSeeder.
        }
    }

    private function fixEncoding($string)
    {
        // Check for common double-encoding artifacts (Mojibake)
        // e.g., Ã- instead of í
        if (str_contains($string, 'Ã')) {
            try {
                // Convert from UTF-8 (interpreted) back to Latin-1 (bytes), which reveals the original UTF-8 bytes
                $attempt = mb_convert_encoding($string, "ISO-8859-1", "UTF-8");
                if (mb_check_encoding($attempt, 'UTF-8')) {
                    return $attempt;
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }
        return $string;
    }

    private function getOrdinal($n)
    {
        $ordinals = [
            1 => 'Primera',
            2 => 'Segunda',
            3 => 'Tercera',
            4 => 'Cuarta',
            5 => 'Quinta',
            6 => 'Sexta',
            7 => 'Séptima',
            8 => 'Octava',
            9 => 'Novena',
            10 => 'Décima',
            11 => 'Undécima',
            12 => 'Duodécima',
            13 => 'Decimotercera',
            14 => 'Decimocuarta',
            15 => 'Decimoquinta',
            16 => 'Decimosexta',
            17 => 'Decimoséptima',
            18 => 'Decimoctava',
            19 => 'Decimonovena',
            20 => 'Vigésima',
        ];

        return $ordinals[$n] ?? "{$n}ª";
    }
}
