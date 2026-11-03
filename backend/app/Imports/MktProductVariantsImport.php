<?php

namespace App\Imports;

use App\Models\MktProduct;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class MktProductVariantsImport implements ToModel, WithHeadingRow
{
    private $stats = [
        'updated' => 0,
        'not_found' => 0,
        'details' => [], // Add details array
    ];

    public function model(array $row)
    {
        if (empty($row['id'])) {
            return null;
        }

        $sku = (string) $row['id'];
        // Match attribute_line_ids/attribute_id OR attribute_id
        $attrName = $row['attribute_line_ids_attribute_id'] ?? $row['attribute_line_idsattribute_id'] ?? $row['attribute_id'] ?? null;
        // Match attribute_line_ids/value_ids OR value_ids
        $attrValues = $row['attribute_line_ids_value_ids'] ?? $row['attribute_line_idsvalue_ids'] ?? $row['value_ids'] ?? null;

        if (!$attrName || !$attrValues) {
            // Check original keys if mapped incorrectly
            foreach ($row as $k => $v) {
                if (str_contains($k, 'attribute_id')) {
                    $attrName = $v;
                }
                if (str_contains($k, 'value_ids')) {
                    $attrValues = $v;
                }
            }
        }

        if (!$attrName || !$attrValues) {
            return null;
        }

        $product = MktProduct::where('sku', $sku)->orWhere('sku', strtoupper($sku))->first();

        if ($product) {
            $valuesArray = array_map('trim', explode(',', $attrValues));
            
            $variants = $product->variants_json ?? [];
            
            // Check if variant name already exists
            $found = false;
            foreach ($variants as &$variant) {
                if (strtolower($variant['name']) === strtolower($attrName)) {
                    // Update values and make them unique
                    $variant['values'] = array_values(array_unique(array_merge($variant['values'], $valuesArray)));
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                $variants[] = [
                    'name' => $attrName,
                    'values' => $valuesArray
                ];
            }

            $product->update(['variants_json' => $variants]);
            $this->stats['updated']++;
            $this->stats['details'][] = [
                'sku' => $sku,
                'name' => $product->name,
                'variants' => $variants
            ];
        } else {
            $this->stats['not_found']++;
        }

        return null;
    }

    public function getStats()
    {
        return $this->stats;
    }
}
