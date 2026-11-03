<?php

namespace App\Imports;

use App\Models\MktUser;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class MktUserImport implements ToCollection, WithHeadingRow
{
    protected int $marketplaceId;
    public int $importedCount = 0;
    public int $updatedCount = 0;
    public array $errors = [];

    public function __construct(int $marketplaceId)
    {
        $this->marketplaceId = $marketplaceId;
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {
            $rowArray = $row->toArray();
            
            // Buscar columnas flexibles
            $name = $this->getValue($rowArray, ['nombre', 'name', 'nombre_completo', 'nombres']);
            $rut = $this->getValue($rowArray, ['rut', 'run', 'identificacion', 'dni']);
            $email = $this->getValue($rowArray, ['email', 'correo', 'correo_electronico', 'mail']);
            $phone = $this->getValue($rowArray, ['telefono', 'phone', 'celular', 'tel']);
            $companyUnit = $this->getValue($rowArray, ['compania', 'compania_unidad', 'unidad', 'company_unit', 'cia', 'unidad_bomberos']);
            $cargo = $this->getValue($rowArray, ['cargo', 'role', 'rol', 'posicion', 'grado']);
            $activeVal = $this->getValue($rowArray, ['activo', 'active', 'estado']);

            if (empty($name) || empty($rut)) {
                if (!empty($name) || !empty($rut)) {
                    $this->errors[] = "Fila " . ($index + 2) . ": Nombre y RUT son obligatorios.";
                }
                continue;
            }

            $cleanRut = MktUser::cleanRut($rut);
            $formattedRut = MktUser::formatRut($rut);

            if (empty($cleanRut)) {
                $this->errors[] = "Fila " . ($index + 2) . ": RUT inválido ({$rut}).";
                continue;
            }

            $active = true;
            if ($activeVal !== null && $activeVal !== '') {
                $str = strtolower(trim((string)$activeVal));
                if (in_array($str, ['0', 'false', 'no', 'inactivo', 'desactivado'])) {
                    $active = false;
                }
            }

            // Buscar si ya existe el usuario para este marketplace
            $existing = MktUser::where('marketplace_id', $this->marketplaceId)
                               ->where(function($q) use ($cleanRut, $formattedRut, $rut) {
                                   $q->where('rut', $cleanRut)
                                     ->orWhere('rut', $formattedRut)
                                     ->orWhere('rut', $rut);
                               })
                               ->first();

            if ($existing) {
                $existing->update([
                    'name' => $name,
                    'rut' => $formattedRut,
                    'email' => $email ?: $existing->email,
                    'phone' => $phone ?: $existing->phone,
                    'company_unit' => $companyUnit ?: $existing->company_unit,
                    'cargo' => $cargo ?: $existing->cargo,
                    'active' => $active,
                ]);
                $this->updatedCount++;
            } else {
                MktUser::create([
                    'marketplace_id' => $this->marketplaceId,
                    'name' => $name,
                    'rut' => $formattedRut,
                    'email' => $email ?: null,
                    'phone' => $phone ?: null,
                    'company_unit' => $companyUnit ?: null,
                    'cargo' => $cargo ?: null,
                    'password' => Hash::make($cleanRut), // Contraseña inicial = RUT sin puntos ni guión
                    'active' => $active,
                ]);
                $this->importedCount++;
            }
        }
    }

    private function getValue(array $row, array $keys): ?string
    {
        // Normalizar claves del array a minúsculas y sin acentos
        $normalizedRow = [];
        foreach ($row as $k => $v) {
            $normK = strtolower(trim((string)$k));
            $normK = str_replace(['á', 'é', 'í', 'ó', 'ú', 'ñ', ' '], ['a', 'e', 'i', 'o', 'u', 'n', '_'], $normK);
            $normalizedRow[$normK] = $v;
        }

        foreach ($keys as $key) {
            $normKey = strtolower(trim($key));
            $normKey = str_replace(['á', 'é', 'í', 'ó', 'ú', 'ñ', ' '], ['a', 'e', 'i', 'o', 'u', 'n', '_'], $normKey);

            if (isset($normalizedRow[$normKey]) && $normalizedRow[$normKey] !== '' && $normalizedRow[$normKey] !== null) {
                return trim((string)$normalizedRow[$normKey]);
            }
        }
        return null;
    }
}
