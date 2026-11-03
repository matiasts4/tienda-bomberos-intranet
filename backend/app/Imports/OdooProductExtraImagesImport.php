<?php

namespace App\Imports;

use App\Models\MktProduct;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class OdooProductExtraImagesImport implements ToModel, WithHeadingRow
{
    private $stats = [
        'images_added' => 0,
        'errors' => []
    ];

    public function model(array $row)
    {
        if (empty($row['id'])) {
            return null;
        }

        $sku = (string) $row['id'];
        
        // Find the image column. Maatwebsite Excel snake_cases headers, so 'product_template_image_ids/image_1920' 
        // usually becomes something like 'product_template_image_ids_image_1920' or similar. We'll search for 'image_1920'
        $imageCol = null;
        foreach ($row as $key => $value) {
            if (str_contains(strtolower((string)$key), 'image_1920') && !empty($value)) {
                $imageCol = $value;
                break;
            }
        }

        if (!$imageCol) {
            return null;
        }

        $product = MktProduct::where('sku', $sku)->first();
        if (!$product) {
            $this->stats['errors'][] = "Imagen Extra SKU {$sku}: Producto no encontrado.";
            return null;
        }

        $imageUrl = $this->processImage($imageCol, $sku);
        if ($imageUrl) {
            $images = $product->images_json ?? [];
            if (!in_array($imageUrl, $images)) {
                $images[] = $imageUrl;
                $product->update(['images_json' => $images]);
                $this->stats['images_added']++;
            }
        }

        return null;
    }

    private function processImage($imageData, $sku)
    {
        if (filter_var($imageData, FILTER_VALIDATE_URL)) {
            return $imageData;
        }

        if (preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $imageData) && strlen($imageData) > 100) {
            try {
                $decoded = base64_decode($imageData, true);
                if ($decoded !== false) {
                    $finfo = new \finfo(FILEINFO_MIME_TYPE);
                    $mimeType = $finfo->buffer($decoded);
                    $extension = 'jpg';
                    if ($mimeType === 'image/webp') {
                        $extension = 'webp';
                    } elseif ($mimeType === 'image/png') {
                        $extension = 'png';
                    } elseif ($mimeType === 'image/jpeg') {
                        $extension = 'jpg';
                    } elseif ($mimeType === 'image/gif') {
                        $extension = 'gif';
                    }

                    $fileName = 'extra_' . $sku . '_' . md5($decoded) . '.' . $extension;
                    $path = 'marketplace/products/' . $fileName;
                    if (!Storage::disk('public')->exists($path)) {
                        Storage::disk('public')->put($path, $decoded);
                    }
                    return asset("storage/{$path}");
                }
            } catch (\Exception $e) {
                Log::error("Failed to decode extra base64 image for SKU {$sku}: " . $e->getMessage());
            }
        }
        
        return null;
    }

    public function getStats()
    {
        return $this->stats;
    }
}
