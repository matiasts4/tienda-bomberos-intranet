<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\DetalleCotizacion;
use Illuminate\Support\Facades\DB;
use App\Services\QuotePersonalization;
use App\Models\Cotizacion;

class CotizacionItemPersonalizacionController extends Controller
{
    public function show($cotizacion_item_id)
    {
        QuotePersonalization::authorize();
        $item = DetalleCotizacion::findOrFail($cotizacion_item_id);

        $generos = [];
        $colores = [];
        $tallas = [];

        $permitePersonalizar = true;
        if ($item->sku) {
            $gen = QuotePersonalization::generic($item);
            if ($gen) {
                $permitePersonalizar = (bool) ($gen->permite_personalizar ?? true);
                $variants = $gen->variantes()->with(['color', 'talla'])->get();
                
                $colores = $variants->pluck('color.nombre')->filter(fn($n) => strtolower($n) !== 'generico')->unique()->values()->toArray();
                $tallas = $variants->pluck('talla.nombre')->filter(fn($n) => strtolower($n) !== 'generico')->unique()->values()->toArray();
                
                $gen->load('genero');
                if ($gen->genero && strtolower($gen->genero->nombre) !== 'generico') {
                    $generos = [$gen->genero->nombre];
                } else {
                    // Fallback to basic genders without Generico
                    $generos = ['Unisex'];
                }
            }
        }

        // Si no encontró nada o no tiene SKU, fallback básico sin "Generico"
        if (empty($generos)) $generos = ['Unisex', 'Hombre', 'Mujer'];
        if (empty($colores)) $colores = ['Blanco', 'Negro', 'Azul'];
        if (empty($tallas)) $tallas = ['S', 'M', 'L', 'XL'];

        return response()->json([
            'es_informativo_cotizacion' => QuotePersonalization::informational($item),
            'tipo_personalizacion' => $item->tipo_personalizacion ?? 'bordado',
            'permite_personalizar' => $permitePersonalizar,
            'modo_kit' => \App\Services\KitPersonalization::isKit($item),
            'kit_catalogo' => \App\Services\KitPersonalization::isKit($item) ? \App\Services\KitPersonalization::catalog($item) : [],
            'kit' => ($item->configuracion['kit_version'] ?? null) === 1 ? $item->configuracion : null,
            'grupos' => \App\Services\KitPersonalization::isKit($item) ? [] : ($item->configuracion ?? []),
            'options' => [
                'generos' => $generos,
                'colores' => $colores,
                'tallas' => $tallas
            ]
        ]);
    }

    public function update(Request $request, $cotizacion_item_id)
    {
        QuotePersonalization::authorize();
        $request->validate(['grupos' => 'present|array', 'kit' => 'sometimes|array']);
        return DB::transaction(function () use ($request, $cotizacion_item_id) {
            $item = DetalleCotizacion::findOrFail($cotizacion_item_id);
            $quote = Cotizacion::lockForUpdate()->findOrFail($item->cotizacion_id);
            QuotePersonalization::assertEditable($quote);
            $config = \App\Services\KitPersonalization::isKit($item)
                ? \App\Services\KitPersonalization::normalize($item, $request->input('kit', $request->grupos), false) : $request->grupos;
            $item->tipo_personalizacion = QuotePersonalization::validateConfig($item, $config, false);
            $item->configuracion = $config;
            $item->save();
            $quote->update(['estado_personalizacion' => null, 'personalizacion_completada' => false]);
            return response()->json(['message' => 'Personalización guardada correctamente', 'grupos' => $item->configuracion]);
        });
    }

    public function uploadMatrizImage(Request $request, $cotizacion_item_id)
    {
        $this->authorizeUpload($cotizacion_item_id);
        $request->validate([
            'image' => ['required', 'image', 'max:5120'], // 5MB Max
        ]);

        try {
            $file = $request->file('image');
            $path = $file->store('personalizaciones/item_' . $cotizacion_item_id, 'public');

            return response()->json([
                'url' => \Illuminate\Support\Facades\Storage::url($path),
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al subir la imagen'], 500);
        }
    }

    public function uploadNames(Request $request, $cotizacion_item_id)
    {
        $this->authorizeUpload($cotizacion_item_id);
        $request->validate([
            // PDF, Excel, Word, CSV... limit to 10MB
            'file' => ['required', 'file', 'mimes:pdf,xls,xlsx,csv,doc,docx,txt', 'max:10240'],
        ]);

        try {
            $file = $request->file('file');
            $path = $file->store('personalizaciones/nombres_' . $cotizacion_item_id, 'public');

            $nombres_extraidos = [];
            $extension = strtolower($file->getClientOriginalExtension());

            if (in_array($extension, ['xls', 'xlsx', 'csv'])) {
                try {
                    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getRealPath());
                    $worksheet = $spreadsheet->getActiveSheet();
                    $rows = $worksheet->toArray();
                    foreach ($rows as $row) {
                        // asumiendo que la primera columna tiene el nombre
                        if (!empty($row[0])) {
                            $stringVal = trim((string) $row[0]);
                            if ($stringVal !== '' && strtolower($stringVal) !== 'nombre' && strtolower($stringVal) !== 'nombres') {
                                $nombres_extraidos[] = $stringVal;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    \Log::error("Error parseando excel: " . $e->getMessage());
                }
            } else if ($extension === 'txt') {
                $content = file_get_contents($file->getRealPath());
                $lines = explode("\n", $content);
                foreach ($lines as $line) {
                    $val = trim($line);
                    if ($val !== '') {
                        $nombres_extraidos[] = $val;
                    }
                }
            }

            return response()->json([
                'url' => \Illuminate\Support\Facades\Storage::url($path),
                'name' => $file->getClientOriginalName(),
                'nombres_extraidos' => $nombres_extraidos
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al subir el archivo de nombres'], 500);
        }
    }
    private function authorizeUpload($id): void
    {
        QuotePersonalization::authorize();
        $item = DetalleCotizacion::findOrFail($id);
        QuotePersonalization::assertEditable($item->cotizacion);
        abort_if(QuotePersonalization::informational($item), 422, 'Este ítem solo se incluye en la cotización y su PDF.');
    }
}
