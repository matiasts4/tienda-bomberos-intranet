<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MktProduct;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\MktProductImport;
use App\Imports\OdooProductImport;
use App\Exports\MktProductExport;
use PhpOffice\PhpSpreadsheet\IOFactory;
use ZipArchive;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class MktProductAdminController extends Controller
{
    public function index(Request $request)
    {
        $query = MktProduct::with('categories');
        
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category_id')) {
            // Esto se podría ajustar luego para pivot, de momento mantenemos el category_id base o agregamos whereHas
            $query->whereHas('categories', function($q) use ($request) {
                $q->where('mkt_categories.id', $request->category_id);
            });
        }
        
        $perPage = (int) $request->get('per_page', 20);
        return response()->json($query->orderBy('created_at', 'desc')->paginate($perPage));
    }

    public function allIds(Request $request)
    {
        $query = MktProduct::query();
        
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category_id')) {
            $query->whereHas('categories', function($q) use ($request) {
                $q->where('mkt_categories.id', $request->category_id);
            });
        }
        
        return response()->json($query->pluck('id'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'sku' => 'required|string|unique:mkt_products',
            'name' => 'required|string',
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:mkt_categories,id',
            'active' => 'boolean',
            'price' => 'nullable|numeric|min:0',
            'images_json' => 'nullable|array',
            'images_json.*' => 'string'
        ]);

        $product = MktProduct::create($data);
        return response()->json($product, 201);
    }

    public function show($id)
    {
        $product = MktProduct::with('categories')->findOrFail($id);
        return response()->json($product);
    }

    public function update(Request $request, $id)
    {
        $product = MktProduct::findOrFail($id);
        
        $data = $request->validate([
            'sku' => 'string|unique:mkt_products,sku,' . $product->id,
            'name' => 'string',
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:mkt_categories,id',
            'active' => 'boolean',
            'price' => 'nullable|numeric|min:0',
            'images_json' => 'nullable|array',
            'images_json.*' => 'string'
        ]);

        $product->update($data);
        return response()->json($product);
    }

    public function destroy($id)
    {
        $product = MktProduct::findOrFail($id);
        $product->delete();
        return response()->json(null, 204);
    }

    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:mkt_products,id'
        ]);

        MktProduct::whereIn('id', $request->ids)->delete();

        return response()->json(['message' => 'Productos eliminados exitosamente']);
    }

    public function exportExcel(Request $request)
    {
        $marketplaceId = $request->filled('marketplace_id') ? (int) $request->marketplace_id : null;
        $fileName = $marketplaceId ? "catalogo_productos_marketplace_{$marketplaceId}.xlsx" : "catalogo_general_productos.xlsx";

        return Excel::download(new MktProductExport($marketplaceId), $fileName);
    }

    public function importExcel(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,csv'
        ]);

        Excel::import(new MktProductImport, $request->file('file'));

        return response()->json(['message' => 'Catálogo importado exitosamente']);
    }

    public function importOdooExcel(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv'
        ]);

        try {
            $extension = strtolower($request->file('file')->getClientOriginalExtension());
            $filePath = $request->file('file')->getRealPath();
            
            // Bypass PhpSpreadsheet for CSV to avoid the 32767 char limit per cell for base64 images
            if ($extension === 'csv') {
                $import = new \App\Imports\OdooProductMainSheetImport();
                $handle = fopen($filePath, 'r');
                
                $bom = fread($handle, 3);
                if ($bom !== "\xEF\xBB\xBF") rewind($handle);

                $headers = fgetcsv($handle);
                if ($headers) {
                    $headers[0] = preg_replace('/^[\xef\xbb\xbf]+/', '', $headers[0]);
                    $mappedHeaders = array_map(function($h) {
                        return \Illuminate\Support\Str::slug(trim($h), '_');
                    }, $headers);

                    while (($row = fgetcsv($handle)) !== false) {
                        if (empty(array_filter($row))) continue;
                        
                        $assocRow = [];
                        foreach ($mappedHeaders as $idx => $header) {
                            $assocRow[$header] = $row[$idx] ?? null;
                        }
                        $import->model($assocRow);
                    }
                }
                fclose($handle);

                return response()->json([
                    'message' => 'Importación de Odoo CSV finalizada',
                    'summary' => $import->getStats()
                ]);
            }

            $sheetCount = 1;
            try {
                $reader = IOFactory::createReaderForFile($filePath);
                $reader->setReadDataOnly(true);
                $sheets = $reader->listWorksheetNames($filePath);
                $sheetCount = count($sheets);
            } catch (\Exception $e) {
                $sheetCount = 1;
            }
            
            $import = new OdooProductImport($sheetCount);
            Excel::import($import, $request->file('file'));

            return response()->json([
                'message' => 'Importación de Odoo finalizada',
                'summary' => $import->getSummary()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al procesar el archivo: ' . $e->getMessage()
            ], 500);
        }
    }

    public function importVariantsExcel(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv'
        ]);

        try {
            $import = new \App\Imports\MktProductVariantsImport();
            Excel::import($import, $request->file('file'));

            return response()->json([
                'message' => 'Tallas / Variantes importadas exitosamente',
                'summary' => $import->getStats()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al procesar el archivo: ' . $e->getMessage()
            ], 422);
        }
    }

    public function uploadImagesZip(Request $request)
    {
        $request->validate([
            'zip_file' => 'required|file|mimes:zip'
        ]);

        $allowedExtensions = ['webp', 'jpg', 'jpeg', 'png'];

        $zip = new ZipArchive;
        $file = $request->file('zip_file');
        $res = $zip->open($file->getRealPath());

        if ($res !== true) {
            return response()->json(['message' => 'No se pudo abrir el archivo ZIP'], 400);
        }

        $batchId = (string) Str::uuid();
        $extractPath = storage_path("app/public/marketplace/temp/{$batchId}");

        if (!file_exists($extractPath)) {
            mkdir($extractPath, 0755, true);
        }

        $realExtractPath = realpath($extractPath);
        $skipped = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $entryName = $stat['name'];

            // Skip directories
            if (str_ends_with($entryName, '/')) {
                continue;
            }

            // ── Security: reject dangerous entry names ──

            // 1. Reject entries containing path traversal sequences (any OS)
            $normalized = str_replace('\\', '/', $entryName);
            if (str_contains($normalized, '../') || str_contains($normalized, '..\\')) {
                $skipped[] = $entryName;
                continue;
            }

            // 2. Reject entries that are exactly ".." or contain a ".." path segment
            $segments = explode('/', $normalized);
            $hasDotDot = false;
            foreach ($segments as $segment) {
                if ($segment === '..') {
                    $hasDotDot = true;
                    break;
                }
            }
            if ($hasDotDot) {
                $skipped[] = $entryName;
                continue;
            }

            // 3. Reject absolute paths (Unix or Windows)
            if (str_starts_with($normalized, '/') || preg_match('/^[A-Za-z]:/', $normalized)) {
                $skipped[] = $entryName;
                continue;
            }

            // 4. Reject URL-encoded traversal attempts
            $decoded = rawurldecode($entryName);
            $decodedNormalized = str_replace('\\', '/', $decoded);
            if (str_contains($decodedNormalized, '../') || str_contains($decodedNormalized, '..')) {
                if ($decodedNormalized !== $normalized) {
                    $skipped[] = $entryName;
                    continue;
                }
            }

            // 5. Reject null bytes
            if (str_contains($entryName, "\0")) {
                $skipped[] = $entryName;
                continue;
            }

            // 6. Check file extension — only allow image formats
            $extension = strtolower(pathinfo($normalized, PATHINFO_EXTENSION));
            if (!in_array($extension, $allowedExtensions, true)) {
                $skipped[] = $entryName;
                continue;
            }

            // 7. Build target path using only the basename (flatten structure)
            $baseName = basename($normalized);
            $targetPath = $realExtractPath . DIRECTORY_SEPARATOR . $baseName;

            // 8. Final safety: ensure resolved path is inside extractPath
            //    (use dirname because the file doesn't exist yet for realpath)
            $targetDir = realpath(dirname($targetPath));
            if ($targetDir === false || !str_starts_with($targetDir, $realExtractPath)) {
                $skipped[] = $entryName;
                continue;
            }

            // 9. Handle duplicate basenames — prepend index
            if (file_exists($targetPath)) {
                $baseName = $i . '_' . $baseName;
                $targetPath = $realExtractPath . DIRECTORY_SEPARATOR . $baseName;
            }

            // ── Extract this single entry safely ──
            $contents = $zip->getFromIndex($i);
            if ($contents === false) {
                $skipped[] = $entryName;
                continue;
            }

            file_put_contents($targetPath, $contents);
        }

        $zip->close();

        // Collect extracted image URLs
        $files = \Illuminate\Support\Facades\File::allFiles($extractPath);
        $urls = collect($files)->filter(function ($f) use ($allowedExtensions) {
            return in_array(strtolower($f->getExtension()), $allowedExtensions, true);
        })->map(function ($f) use ($batchId) {
            $relativePath = str_replace('\\', '/', $f->getRelativePathname());
            return asset("storage/marketplace/temp/{$batchId}/{$relativePath}");
        })->values()->toArray();

        $response = [
            'batch_id' => $batchId,
            'files' => $urls,
        ];

        if (count($skipped) > 0) {
            $response['skipped'] = $skipped;
        }

        return response()->json($response);
    }

    public function assignImages(Request $request, $id)
    {
        $product = MktProduct::findOrFail($id);
        
        $request->validate([
            'images' => 'required|array',
            'images.*' => 'string'
        ]);

        $product->update(['images_json' => $request->images]);

        return response()->json($product);
    }

    public function uploadSingleImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,webp|max:5120'
        ]);

        $file = $request->file('image');
        $fileName = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('marketplace/products', $fileName, 'public');

        return response()->json([
            'url' => asset("storage/{$path}")
        ]);
    }
}

