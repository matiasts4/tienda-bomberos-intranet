<?php

namespace App\Exports;

use App\Models\MktProduct;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class MktProductExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    protected ?int $marketplaceId;

    public function __construct(?int $marketplaceId = null)
    {
        $this->marketplaceId = $marketplaceId;
    }

    public function collection()
    {
        return MktProduct::with(['categories', 'marketplaces'])
            ->orderBy('name')
            ->get();
    }

    public function headings(): array
    {
        return [
            'SKU',
            'Nombre del Producto',
            'Categoría',
            'Precio de Venta',
            'Tallas / Variantes',
            'Descripción',
            'Estado',
            'Asignado al Marketplace',
            'Imágenes (URLs)',
        ];
    }

    public function map($p): array
    {
        // Categorías separadas por coma
        $categoriesStr = $p->categories->pluck('name')->implode(', ');
        if (empty($categoriesStr) && $p->category) {
            $categoriesStr = $p->category->name;
        }

        // Tallas y variantes formateadas (ej: Talla: S, M, L | Color: Azul, Rojo)
        $variantsStr = '';
        if (!empty($p->variants_json) && is_array($p->variants_json)) {
            $parts = [];
            foreach ($p->variants_json as $v) {
                if (isset($v['name']) && isset($v['values']) && is_array($v['values'])) {
                    $parts[] = $v['name'] . ': ' . implode(', ', $v['values']);
                }
            }
            $variantsStr = implode(' | ', $parts);
        }

        // Estado de asignación al marketplace actual
        $isAssigned = 'No';
        if ($this->marketplaceId) {
            $isAssigned = $p->marketplaces->contains('id', $this->marketplaceId) ? 'Sí' : 'No';
        }

        // Imágenes
        $imagesStr = !empty($p->images_json) && is_array($p->images_json)
            ? implode(', ', $p->images_json)
            : '';

        return [
            $p->sku,
            $p->name,
            $categoriesStr,
            $p->price !== null ? (float) $p->price : '',
            $variantsStr,
            $p->description ?: '',
            $p->active ? 'Activo' : 'Inactivo',
            $isAssigned,
            $imagesStr,
        ];
    }
}
