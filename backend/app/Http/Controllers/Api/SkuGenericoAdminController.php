<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ProductCatalogConflictException;
use App\Http\Controllers\Controller;
use App\Models\PteSkuGenerico;
use App\Services\ProductCatalogService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class SkuGenericoAdminController extends Controller
{
    public function __construct(private readonly ProductCatalogService $catalogService) {}

    /**
     * Listado paginado y filtrado de productos base para el Catálogo Comercial.
     */
    public function index(Request $request)
    {
        $search = $request->input('search');
        $estado = $request->input('estado', 'todos');
        $perPage = (int) $request->input('per_page', 24);
        if ($perPage < 1 || $perPage > 100) {
            $perPage = 24;
        }

        $query = PteSkuGenerico::query()
            ->select([
                'id',
                'sku_base',
                'nombre',
                'precio_venta',
                'imagen_url',
                'estado_catalogo',
                'activo',
                'permite_cotizar',
                'modo_kit',
                'archived_at',
            ]);

        // El modo kit no condiciona la visibilidad administrativa: debe poder configurarse aquí.
        // Conservar la exclusión de productos decorativos/informativos.
        $query->where(function ($q) {
            $q->where('es_informativo_cotizacion', false)
                ->orWhereNull('es_informativo_cotizacion');
        })
        ->whereNotIn('nombre', [
            'NOMBRE', 'CARGO', 'BANDERA', 'FRASE',
            'PARCHE 2,5 X 10', 'PARCHE 5 X 10', 'PARCHE 10 X 10', 'PARCHE 25 X 10',
            'PARCHE 2.5 X 10', 'LOGO',
        ]);

        // Filtro por estado
        if ($estado !== 'todos' && in_array($estado, ['borrador', 'publicado', 'archivado'], true)) {
            $query->where('estado_catalogo', $estado);
        }

        // Filtro de búsqueda
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('sku_base', 'like', "%{$search}%")
                    ->orWhere('nombre', 'like', "%{$search}%");
            });
        }

        $results = $query->orderBy('nombre', 'asc')->paginate($perPage);

        return response()->json($results);
    }

    /**
     * Detalle administrativo del producto genérico con sus variantes optimizadas.
     */
    public function show($id)
    {
        $item = PteSkuGenerico::with(['genero:id,nombre'])->findOrFail($id);

        // Relación con variantes ordenada por color y orden de talla
        $variants = $item->variantes()
            ->select('pte_skus.*')
            ->leftJoin('pte_tallas', 'pte_skus.talla_id', '=', 'pte_tallas.id')
            ->leftJoin('pte_colores', 'pte_skus.color_id', '=', 'pte_colores.id')
            ->orderBy('pte_colores.nombre', 'asc')
            ->orderBy('pte_tallas.orden', 'asc')
            ->with(['color:id,nombre', 'talla:id,nombre', 'stock'])
            ->get()
            ->map(function ($v) use ($item) {
                return [
                    'sku' => $v->sku,
                    'nombre' => $v->nombre,
                    'color' => $v->color?->nombre ?? 'Genérico',
                    'talla' => $v->talla?->nombre ?? 'Genérico',
                    'genero' => $item->genero?->nombre ?? 'Unisex',
                    'stock' => $v->stock?->cantidad ?? 0,
                    'reserved_stock' => $v->stock?->reserved_stock ?? 0,
                    'activo' => (bool) $v->activo,
                ];
            });

        // Resumen de colores y tallas únicas
        $colores = $variants->pluck('color')->unique()->filter(fn ($c) => $c !== 'Genérico' && $c !== 'GENERICO')->values();
        $tallas = $variants->pluck('talla')->unique()->filter(fn ($t) => $t !== 'Genérico' && $t !== 'GENERICO')->values();

        $item->variants = $variants;
        $item->colores_disponibles = $colores;
        $item->tallas_disponibles = $tallas;
        $item->stock_total = $variants->sum('stock');
        $item->stock_reservado_total = $variants->sum('reserved_stock');
        if ($item->modo_kit) {
            $virtual = new \App\Models\DetalleCotizacion();
            $virtual->setRelation('personalizationGeneric', $item);
            $item->kit_catalogo = \App\Services\KitPersonalization::catalog($virtual);
        }

        return response()->json($item);
    }

    /**
     * Endpoint exclusivo para crear plantillas base (P1).
     * Exclusivo para superadmin, inicia en estado borrador sin variantes ni stock.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre' => 'required|string|max:150',
            'precio_venta' => 'nullable|numeric',
            'unidad_negocio_id' => 'required|integer',
            'origen_id' => 'required|integer',
            'grp_familia_id' => 'required|integer',
            'familia_id' => 'required|integer',
            'subfamilia_id' => 'required|integer',
            'familia_tipo_id' => 'required|integer',
            'familia_formato_id' => 'required|integer',
            'generos' => 'nullable|array',
            'generos.*' => 'integer',
            'genero_id' => 'nullable|integer',
            'permite_personalizar' => 'nullable|boolean',
            'permite_cotizar' => 'sometimes|boolean',
            'modo_kit' => 'sometimes|boolean',
        ]);

        try {
            $result = $this->catalogService->createTemplate($data, Auth::id());
        } catch (ProductCatalogConflictException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('No se pudo crear la plantilla base de producto.', ['exception' => $exception]);

            return response()->json(['message' => 'No se pudo crear la plantilla. Inténtalo nuevamente.'], 500);
        }

        return response()->json([
            'message' => $result['message'],
            'data' => $result['creados'][0] ?? null,
            'plantillas' => $result['creados'],
            'count' => $result['count'],
        ], 201);
    }

    /**
     * Publica una plantilla en borrador (requiere >= 1 variante activa).
     */
    public function publish(Request $request, $id)
    {
        try {
            $activateVariants = $request->boolean('activate_variants') || $request->boolean('auto_activate');
            $sku = $this->catalogService->publishGeneric((int) $id, $activateVariants);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('No se pudo publicar la plantilla.', ['sku_generico_id' => $id, 'exception' => $exception]);

            return response()->json(['message' => 'No se pudo publicar la plantilla. Inténtalo nuevamente.'], 500);
        }

        return response()->json([
            'message' => 'Plantilla publicada correctamente. Aparecerá en cotizaciones si Permite cotización está activado.',
            'data' => $sku,
        ]);
    }

    /**
     * Previsualiza el impacto antes de confirmar el archivado de una plantilla.
     */
    public function archivePreview($id)
    {
        try {
            $preview = $this->catalogService->getArchivePreview((int) $id);

            return response()->json($preview);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        } catch (Throwable $exception) {
            Log::error('No se pudo generar el preview de archivado.', ['sku_generico_id' => $id, 'exception' => $exception]);

            return response()->json(['message' => 'No se pudo calcular el impacto de archivado.'], 500);
        }
    }

    /**
     * Actualiza información básica de la plantilla.
     */
    public function update(Request $request, $id)
    {
        $sku = PteSkuGenerico::findOrFail($id);

        $data = $request->validate([
            'nombre' => 'sometimes|string|max:150',
            'precio_venta' => 'sometimes|nullable|numeric',
            'activo' => 'sometimes|boolean',
            'estado_catalogo' => 'sometimes|string|in:borrador,publicado,archivado',
            'permite_personalizar' => 'sometimes|boolean',
            'permite_cotizar' => 'sometimes|boolean',
            'modo_kit' => 'sometimes|boolean',
            'componentes_kit' => 'sometimes|array',
        ]);

        if (array_key_exists('precio_venta', $data) && $data['precio_venta'] !== null) {
            $data['precio_venta'] = (int) round((float) $data['precio_venta'] / 10) * 10;
        }

        try {
            $sku = $this->catalogService->updateGeneric((int) $sku->id, $data, Auth::id());
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('No se pudo actualizar el producto base del catálogo.', ['sku_generico_id' => $id, 'exception' => $exception]);

            return response()->json(['message' => 'No se pudo actualizar el producto. Inténtalo nuevamente.'], 500);
        }

        return response()->json($sku);
    }

    /**
     * Archiva la plantilla preservando stock, variantes e historial.
     */
    public function destroy(Request $request, $id)
    {
        $reason = $request->input('archive_reason');

        try {
            $this->catalogService->archiveGeneric((int) $id, Auth::id(), $reason);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        } catch (Throwable $exception) {
            Log::error('No se pudo archivar el producto base del catálogo.', ['sku_generico_id' => $id, 'exception' => $exception]);

            return response()->json(['message' => 'No se pudo archivar el producto. Inténtalo nuevamente.'], 500);
        }

        return response()->json([
            'message' => 'Producto archivado correctamente. Su historial y stock fueron preservados.',
            'archived' => true,
        ]);
    }

    /**
     * Reactiva una plantilla como borrador (no reactiva variantes automáticamente).
     */
    public function reactivate(Request $request, $id)
    {
        try {
            $activateVariants = $request->boolean('activate_variants') || $request->boolean('auto_activate');
            $sku = $this->catalogService->reactivateGeneric((int) $id, $activateVariants);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        } catch (Throwable $exception) {
            Log::error('No se pudo reactivar el producto base.', ['sku_generico_id' => $id, 'exception' => $exception]);

            return response()->json(['message' => 'No se pudo reactivar el producto. Inténtalo nuevamente.'], 500);
        }

        return response()->json([
            'message' => 'Producto reactivado como borrador. Selecciona las variantes que deseas activar antes de publicarlo.',
            'data' => $sku,
        ]);
    }

    /**
     * Archiva variantes seleccionadas de una plantilla.
     */
    public function archiveVariants(Request $request, $id)
    {
        $request->validate([
            'skus' => 'required|array|min:1',
            'skus.*' => 'required|string',
        ]);

        try {
            $result = $this->catalogService->archiveVariants((int) $id, $request->input('skus'));
        } catch (Throwable $exception) {
            Log::error('No se pudieron archivar las variantes seleccionadas.', ['sku_generico_id' => $id, 'exception' => $exception]);

            return response()->json(['message' => 'No se pudieron archivar las variantes. Inténtalo nuevamente.'], 500);
        }

        return response()->json($result);
    }

    /**
     * Reactiva variantes seleccionadas de una plantilla.
     */
    public function reactivateVariants(Request $request, $id)
    {
        $request->validate([
            'skus' => 'required|array|min:1',
            'skus.*' => 'required|string',
        ]);

        try {
            $result = $this->catalogService->reactivateVariants((int) $id, $request->input('skus'));
        } catch (Throwable $exception) {
            Log::error('No se pudieron reactivar las variantes seleccionadas.', ['sku_generico_id' => $id, 'exception' => $exception]);

            return response()->json(['message' => 'No se pudieron reactivar las variantes. Inténtalo nuevamente.'], 500);
        }

        return response()->json($result);
    }

    /**
     * Sube y reemplaza imágenes de forma atómica.
     * Si falla la BD, elimina el archivo recién subido.
     * Si tiene éxito, elimina el archivo anterior si es local.
     */
    public function uploadAssets(Request $request, $id)
    {
        $sku = PteSkuGenerico::findOrFail($id);

        $request->validate([
            'imagen' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:5120',
            'imagen_back' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:5120',
            'imagen_side' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $updates = [];
        $uploadedPaths = [];
        $oldPathsToDelete = [];

        foreach (['imagen' => 'imagen_url', 'imagen_back' => 'imagen_back_url', 'imagen_side' => 'imagen_side_url'] as $fileKey => $dbCol) {
            if ($request->hasFile($fileKey)) {
                $storedPath = $request->file($fileKey)->store('productos/imagenes', 'public');
                $uploadedPaths[] = $storedPath;
                $updates[$dbCol] = '/storage/'.$storedPath;

                // Identificar path anterior si era local en /storage/
                $oldUrl = $sku->{$dbCol};
                if ($oldUrl && Str::startsWith($oldUrl, '/storage/')) {
                    $oldPathsToDelete[] = Str::after($oldUrl, '/storage/');
                }
            }
        }

        if (empty($updates)) {
            return response()->json($sku);
        }

        try {
            $sku->update($updates);

            // Tras éxito en BD, eliminamos de forma segura los archivos anteriores
            foreach ($oldPathsToDelete as $oldPath) {
                if (Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }
            }
        } catch (Throwable $exception) {
            // Si la actualización DB falla, eliminamos los archivos recién subidos
            foreach ($uploadedPaths as $newPath) {
                if (Storage::disk('public')->exists($newPath)) {
                    Storage::disk('public')->delete($newPath);
                }
            }

            Log::error('No se pudieron actualizar los recursos del producto.', ['sku_generico_id' => $id, 'exception' => $exception]);

            return response()->json(['message' => 'No se pudo actualizar la imagen del producto.'], 500);
        }

        return response()->json($sku);
    }

    /**
     * Agrega nuevas variantes de color y talla a la plantilla.
     */
    public function addVariants(Request $request, $id)
    {
        $request->validate([
            'colores' => 'required|array|min:1',
            'colores.*' => 'required|integer|distinct|min:0|max:99',
            'tallas' => 'required|array|min:1',
            'tallas.*' => 'required|integer|distinct|min:0|max:99',
        ]);

        try {
            $summary = $this->catalogService->addVariants(
                (int) $id,
                $request->input('colores'),
                $request->input('tallas')
            );
        } catch (ProductCatalogConflictException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('No se pudieron agregar variantes al producto.', ['sku_generico_id' => $id, 'exception' => $exception]);

            return response()->json(['message' => 'No se pudieron agregar las variantes. Inténtalo nuevamente.'], 500);
        }

        $message = "Se crearon {$summary['creados']} variantes";
        if ($summary['reactivados'] > 0) {
            $message .= " y se reactivaron {$summary['reactivados']}";
        }
        $message .= '.';

        return response()->json([
            'message' => $message,
            'resumen' => $summary,
        ]);
    }

    /** Cambia exclusivamente el nombre de una variante perteneciente a la plantilla. */
    public function renameVariant(Request $request, $id, $sku)
    {
        if (is_string($request->input('nombre'))) {
            $request->merge(['nombre' => trim($request->input('nombre'))]);
        }
        $data = $request->validate([
            'nombre' => 'required|string|max:150',
            'nombre_anterior' => 'required|string',
        ]);

        try {
            $variant = $this->catalogService->renameVariant((int) $id, (string) $sku, $data['nombre'], $data['nombre_anterior']);
        } catch (ProductCatalogConflictException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'La variante no pertenece a este producto o ya no está disponible.'], 404);
        } catch (Throwable $exception) {
            Log::error('No se pudo cambiar el nombre de la variante.', ['sku' => $sku, 'exception' => $exception]);
            return response()->json(['message' => 'No se pudo guardar el nombre. Inténtalo nuevamente.'], 500);
        }

        return response()->json(['sku' => $variant->sku, 'nombre' => $variant->nombre]);
    }
}
