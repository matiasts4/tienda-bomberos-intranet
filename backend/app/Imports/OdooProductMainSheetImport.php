<?php

namespace App\Imports;

use App\Models\MktProduct;
use App\Models\MktCategory;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class OdooProductMainSheetImport implements ToModel, WithHeadingRow
{
    private $stats = [
        'created' => 0,
        'updated' => 0,
        'failed' => 0,
        'images_added' => 0,
        'changes' => [],
        'errors' => []
    ];

    public function model(array $row)
    {
        // 1. Dynamic SKU / ID resolution
        $sku = null;
        
        // Try labeled keys first (id, sku, default_code, referencia, etc.)
        foreach ($row as $k => $v) {
            $keyLower = strtolower((string)$k);
            if (!empty($v) && (
                $keyLower === 'id' ||
                $keyLower === 'sku' ||
                str_contains($keyLower, 'product_tmpl_id') ||
                str_contains($keyLower, 'product_id') ||
                str_contains($keyLower, 'default_code') ||
                str_contains($keyLower, 'referencia')
            )) {
                $sku = trim((string) $v);
                break;
            }
        }

        // Fallback: Check Column A value if no labeled key matched
        $values = array_values($row);
        if (!$sku && !empty($values[0])) {
            $val0 = trim((string)$values[0]);
            if (!str_contains($val0, 'http') && strlen($val0) < 100) {
                $sku = $val0;
            }
        }

        if (!$sku) {
            return null;
        }

        // Name resolution (accepts Odoo 'name', and Excel 'nombre', 'nombre_del_producto', etc.)
        $name = null;
        $nameKeys = ['name', 'nombre', 'nombre_del_producto', 'product_name', 'producto'];
        foreach ($nameKeys as $nk) {
            if (!empty($row[$nk])) {
                $name = trim((string)$row[$nk]);
                break;
            }
        }

        // --- CASE 1: Main Product Row (contains product name) ---
        if (!empty($name)) {

            // Category validation (Supports comma-separated categories from Odoo or exported Excel)
            $categoryName = null;
            $catKeys = ['public_categ_ids', 'categoria_del_producto', 'categoria', 'categorias', 'category', 'categories'];
            foreach ($catKeys as $ck) {
                if (!empty($row[$ck])) {
                    $categoryName = (string)$row[$ck];
                    break;
                }
            }

            $categoryIds = [];
            if (!empty($categoryName)) {
                $catList = array_map('trim', explode(',', $categoryName));
                foreach ($catList as $catName) {
                    if (empty($catName)) continue;
                    $found = MktCategory::where('name', $catName)->first();
                    if ($found) {
                        $categoryIds[] = $found->id;
                    } else {
                        // Create category if not found, to ease imports
                        $slug = \Illuminate\Support\Str::slug($catName);
                        $createdCat = MktCategory::firstOrCreate(
                            ['slug' => $slug],
                            ['name' => $catName]
                        );
                        $categoryIds[] = $createdCat->id;
                    }
                }
            }

            $description = null;
            $descKeys = ['website_description', 'description_sale', 'description', 'descripcion', 'detalle'];
            foreach ($descKeys as $dk) {
                if (isset($row[$dk]) && $row[$dk] !== null) {
                    $description = trim((string)$row[$dk]);
                    break;
                }
            }

            $image1920 = $row['image_1920'] ?? ($row['imagenes_urls'] ?? ($row['imagenes'] ?? ($row['image'] ?? null)));
            $imagesJson = [];
            
            // Handle main image
            if ($image1920) {
                if (str_contains($image1920, ',')) {
                    $splitImgs = array_map('trim', explode(',', $image1920));
                    foreach ($splitImgs as $imgItem) {
                        $imgUrl = $this->processImage($imgItem, $sku, 'main');
                        if ($imgUrl) $imagesJson[] = $imgUrl;
                    }
                } else {
                    $imageUrl = $this->processImage($image1920, $sku, 'main');
                    if ($imageUrl) {
                        $imagesJson[] = $imageUrl;
                    }
                }
            }

            // Extract price from columns (list_price, precio_de_venta, precio, price, etc.)
            $price = null;
            $priceKeys = ['list_price', 'precio_de_venta', 'precio', 'price', 'listprice', 'preciodeventa'];
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

            // Extract variants / tallas
            $variantsJson = null;
            $variantKeys = ['tallas_variantes', 'tallas', 'variantes', 'variants', 'talla'];
            foreach ($variantKeys as $vk) {
                if (!empty($row[$vk])) {
                    $rawVariants = trim((string)$row[$vk]);
                    $variantsJson = $this->parseVariantsString($rawVariants);
                    break;
                }
            }

            // Extract active status
            $active = true;
            $statusKeys = ['estado', 'activo', 'active', 'is_active', 'status'];
            foreach ($statusKeys as $sk) {
                if (isset($row[$sk])) {
                    $valLower = strtolower(trim((string)$row[$sk]));
                    if (in_array($valLower, ['inactivo', 'inactive', 'no', '0', 'false', 'desactivado'])) {
                        $active = false;
                    }
                    break;
                }
            }

            $product = MktProduct::where('sku', $sku)->orWhere('sku', strtoupper($sku))->first();

            $data = [
                'name' => $name,
                'active' => $active,
            ];

            if ($description !== null) {
                $data['description'] = $description;
            }

            if (!empty($categoryIds)) {
                $data['category_id'] = $categoryIds[0];
            }

            if ($price !== null) {
                $data['price'] = $price;
            }

            if (!empty($imagesJson)) {
                $data['images_json'] = $imagesJson;
            }

            if ($variantsJson !== null) {
                $data['variants_json'] = $variantsJson;
            }

            if ($product) {
                // Track changes for detailed feedback
                $changes = [];
                if ($price !== null && (float)$product->price != (float)$price) {
                    $oldP = $product->price ? '$' . number_format((float)$product->price, 0, ',', '.') : 'sin precio';
                    $newP = '$' . number_format((float)$price, 0, ',', '.');
                    $changes[] = "Precio: {$oldP} → {$newP}";
                }
                if ($product->name !== $name) {
                    $changes[] = "Nombre actualizado";
                }
                if ($variantsJson !== null && json_encode($product->variants_json) !== json_encode($variantsJson)) {
                    $changes[] = "Variantes/Tallas actualizadas";
                }
                if ($product->active !== $active) {
                    $changes[] = "Estado: " . ($active ? 'Activo' : 'Inactivo');
                }

                $product->update($data);
                $this->stats['updated']++;

                if (!empty($changes)) {
                    $this->stats['changes'][] = "SKU {$sku} ({$name}): " . implode(', ', $changes);
                }
            } else {
                $data['images_json'] = $data['images_json'] ?? [];
                $data['variants_json'] = $data['variants_json'] ?? [];
                $product = MktProduct::create(array_merge(['sku' => $sku], $data));
                $this->stats['created']++;
                $this->stats['changes'][] = "SKU {$sku} ({$name}): Producto creado exitosamente";
            }

            // --- SYNC MANY TO MANY CATEGORIES ---
            if (!empty($categoryIds)) {
                $product->categories()->sync($categoryIds);
            }
        }

        // --- CASE 2: Extra Image Check ---
        $extraImageCol = null;
        
        $isValidImage = function($valStr) {
            return filter_var($valStr, FILTER_VALIDATE_URL) || (preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $valStr) && strlen($valStr) > 100);
        };

        foreach ($row as $key => $value) {
            $keyLower = strtolower((string)$key);
            if (!empty($name) && $keyLower === 'image_1920') {
                continue;
            }
            if ((str_contains($keyLower, 'image') || str_contains($keyLower, 'foto') || str_contains($keyLower, 'extra') || str_contains($keyLower, 'url')) && !empty($value)) {
                if ($isValidImage((string)$value)) {
                    $extraImageCol = $value;
                    break;
                }
            }
        }

        if ($extraImageCol) {
            $product = MktProduct::where('sku', $sku)->orWhere('sku', strtoupper($sku))->first();
            if ($product) {
                $imageUrl = $this->processImage($extraImageCol, $sku, 'extra');
                if ($imageUrl) {
                    $images = $product->images_json ?? [];
                    if (!in_array($imageUrl, $images)) {
                        $images[] = $imageUrl;
                        $product->update(['images_json' => $images]);
                        $this->stats['images_added']++;
                    }
                }
            }
        }

        return null;
    }

    private function parseVariantsString(string $raw): array
    {
        $result = [];
        
        // Check if format is "Talla: S, M, L | Color: Azul, Rojo"
        if (str_contains($raw, ':') || str_contains($raw, '|')) {
            $sections = explode('|', $raw);
            foreach ($sections as $sec) {
                $sec = trim($sec);
                if (empty($sec)) continue;
                if (str_contains($sec, ':')) {
                    [$attrName, $vals] = explode(':', $sec, 2);
                    $valArray = array_values(array_filter(array_map('trim', explode(',', $vals))));
                    if (!empty($valArray)) {
                        $result[] = [
                            'name' => trim($attrName),
                            'values' => $valArray
                        ];
                    }
                } else {
                    $valArray = array_values(array_filter(array_map('trim', explode(',', $sec))));
                    if (!empty($valArray)) {
                        $result[] = [
                            'name' => 'Talla',
                            'values' => $valArray
                        ];
                    }
                }
            }
        } else {
            // Simple comma-separated values (assumed to be Tallas)
            $valArray = array_values(array_filter(array_map('trim', explode(',', $raw))));
            if (!empty($valArray)) {
                $result[] = [
                    'name' => 'Talla',
                    'values' => $valArray
                ];
            }
        }

        return $result;
    }

    private function processImage($imageData, $sku, $type = 'main')
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

                    $fileName = $type . '_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $sku) . '_' . md5($decoded) . '.' . $extension;
                    $path = 'marketplace/products/' . $fileName;
                    if (!Storage::disk('public')->exists($path)) {
                        Storage::disk('public')->put($path, $decoded);
                    }
                    return asset("storage/{$path}");
                }
            } catch (\Exception $e) {
                Log::error("Failed to decode base64 image for SKU {$sku}: " . $e->getMessage());
            }
        }
        
        return null;
    }

    public function getStats()
    {
        return $this->stats;
    }
}
