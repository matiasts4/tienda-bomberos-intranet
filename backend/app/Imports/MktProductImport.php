<?php

namespace App\Imports;

use App\Models\MktProduct;
use App\Models\MktCategory;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class MktProductImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        if (!isset($row['sku']) || !isset($row['name'])) {
            return null;
        }

        $categoryId = null;
        if (isset($row['category_slug']) && !empty($row['category_slug'])) {
            $category = MktCategory::where('slug', $row['category_slug'])->first();
            if ($category) {
                $categoryId = $category->id;
            }
        }

        $price = null;
        $priceKeys = ['price', 'precio', 'list_price', 'precio_de_venta'];
        foreach ($priceKeys as $pk) {
            if (isset($row[$pk]) && $row[$pk] !== '' && $row[$pk] !== null) {
                $val = trim((string)$row[$pk]);
                $cleanVal = preg_replace('/[^\d.,]/', '', $val);
                if (str_contains($cleanVal, '.') && str_contains($cleanVal, ',')) {
                    $cleanVal = str_replace('.', '', $cleanVal);
                    $cleanVal = str_replace(',', '.', $cleanVal);
                } elseif (str_contains($cleanVal, ',')) {
                    $cleanVal = str_replace(',', '.', $cleanVal);
                }
                if (is_numeric($cleanVal)) {
                    $price = (float) $cleanVal;
                    break;
                }
            }
        }

        return MktProduct::updateOrCreate(
            ['sku' => (string) $row['sku']],
            [
                'name' => $row['name'],
                'description' => $row['description'] ?? null,
                'category_id' => $categoryId,
                'price' => $price,
                'active' => isset($row['active']) ? (bool) $row['active'] : true,
            ]
        );
    }
}
