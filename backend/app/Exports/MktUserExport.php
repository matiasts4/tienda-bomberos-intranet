<?php

namespace App\Exports;

use App\Models\MktUser;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class MktUserExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    protected int $marketplaceId;

    public function __construct(int $marketplaceId)
    {
        $this->marketplaceId = $marketplaceId;
    }

    public function collection()
    {
        return MktUser::where('marketplace_id', $this->marketplaceId)
                      ->orderBy('name')
                      ->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Nombre Completo',
            'RUT',
            'Email',
            'Teléfono',
            'Compañía / Unidad',
            'Cargo',
            'Estado',
            'Fecha Registro',
        ];
    }

    public function map($user): array
    {
        return [
            $user->id,
            $user->name,
            $user->rut,
            $user->email ?: '',
            $user->phone ?: '',
            $user->company_unit ?: '',
            $user->cargo ?: '',
            $user->active ? 'Activo' : 'Inactivo',
            $user->created_at ? $user->created_at->format('d/m/Y H:i') : '',
        ];
    }
}
