<?php

namespace App\Services;

use App\Exceptions\ProductCatalogConflictException;
use App\Models\Peticion;
use App\Models\PteColor;
use App\Models\PteGenero;
use App\Models\PteSku;
use App\Models\PteSkuGenerico;
use App\Models\PteStock;
use App\Models\PteTalla;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductCatalogService
{
    public function __construct(private readonly SkuGeneratorService $skuGenerator) {}

    /**
     * Sincroniza el estado formal del catálogo y la columna de compatibilidad 'activo'.
     */
    public function syncCatalogState(
        PteSkuGenerico $generic,
        string $estado,
        ?int $userId = null,
        ?string $reason = null
    ): PteSkuGenerico {
        $attributes = [
            'estado_catalogo' => $estado,
            'activo' => ($estado === 'publicado'),
        ];

        if ($estado === 'archivado') {
            $attributes['archived_at'] = now();
            $attributes['archived_by'] = $userId;
            $attributes['archive_reason'] = $reason;
        } elseif ($estado === 'borrador' || $estado === 'publicado') {
            $attributes['archived_at'] = null;
            $attributes['archived_by'] = null;
            $attributes['archive_reason'] = null;
        }

        $generic->update($attributes);

        return $generic->refresh();
    }

    /**
     * Crea exclusivamente una plantilla (PteSkuGenerico) en estado 'borrador'.
     * Cero variantes (PteSku), cero stock (PteStock).
     * Soporta creación por género único o múltiple si se proporciona un array 'generos'.
     *
     * @return array{creados: PteSkuGenerico[], count: int, message: string}
     */
    public function createTemplate(array $attributes, ?int $userId = null): array
    {
        $this->skuGenerator->validateSegmentLimits($attributes);

        $generosIds = [];
        if (! empty($attributes['generos']) && is_array($attributes['generos'])) {
            $generosIds = collect($attributes['generos'])->map(fn ($id) => (int) $id)->unique()->values()->all();
        } elseif (isset($attributes['genero_id'])) {
            $generosIds = [(int) $attributes['genero_id']];
        } else {
            $generosIds = [0];
        }

        $precioVenta = isset($attributes['precio_venta']) && $attributes['precio_venta'] !== null
            ? (int) round((float) $attributes['precio_venta'] / 10) * 10
            : null;

        $created = [];

        try {
            DB::transaction(function () use ($attributes, $generosIds, $precioVenta, &$created) {
                foreach ($generosIds as $generoId) {
                    $skuBase = $this->skuGenerator->generateBaseSku(
                        (int) $attributes['unidad_negocio_id'],
                        (int) $attributes['origen_id'],
                        (int) $attributes['grp_familia_id'],
                        (int) $attributes['familia_id'],
                        (int) $attributes['subfamilia_id'],
                        (int) $attributes['familia_tipo_id'],
                        (int) $attributes['familia_formato_id'],
                        $generoId
                    );

                    $existing = PteSkuGenerico::query()->where('sku_base', $skuBase)->lockForUpdate()->first();
                    if ($existing) {
                        throw new ProductCatalogConflictException(
                            "El producto base con SKU {$skuBase} ('{$existing->nombre}') ya existe en el catálogo."
                        );
                    }

                    $generoModel = PteGenero::find($generoId);
                    $genreName = ($generoModel && ! in_array($generoModel->nombre, ['Generico', 'Unisex', 'GENERICO', 'UNISEX']))
                        ? $generoModel->nombre
                        : '';

                    $finalName = trim((string) $attributes['nombre']);
                    if ($genreName !== '' && ! Str::endsWith($finalName, $genreName)) {
                        $finalName .= ' '.$genreName;
                    }

                    $generic = PteSkuGenerico::create([
                        'sku_base' => $skuBase,
                        'nombre' => $finalName,
                        'precio_venta' => $precioVenta,
                        'unidad_negocio_id' => (int) $attributes['unidad_negocio_id'],
                        'origen_id' => (int) $attributes['origen_id'],
                        'grp_familia_id' => (int) $attributes['grp_familia_id'],
                        'familia_id' => (int) $attributes['familia_id'],
                        'subfamilia_id' => (int) $attributes['subfamilia_id'],
                        'familia_tipo_id' => (int) $attributes['familia_tipo_id'],
                        'familia_formato_id' => (int) $attributes['familia_formato_id'],
                        'genero_id' => $generoId,
                        'estado_catalogo' => 'borrador',
                        'activo' => false,
                        'permite_personalizar' => filter_var($attributes['permite_personalizar'] ?? true, FILTER_VALIDATE_BOOLEAN),
                        'permite_cotizar' => filter_var($attributes['permite_cotizar'] ?? true, FILTER_VALIDATE_BOOLEAN),
                        'modo_kit' => filter_var($attributes['modo_kit'] ?? false, FILTER_VALIDATE_BOOLEAN),
                        'componentes_kit' => [],
                    ]);

                    $created[] = $generic;
                }
            });
        } catch (QueryException $exception) {
            if ($this->isIntegrityConflict($exception)) {
                throw new ProductCatalogConflictException(
                    'Ya existe una plantilla con esta combinación base en el catálogo.',
                    previous: $exception
                );
            }
            throw $exception;
        }

        return [
            'creados' => $created,
            'count' => count($created),
            'message' => !empty($attributes['modo_kit']) ? 'Plantilla Kit/Traje creada. Define y guarda sus componentes antes de publicarla.' : 'Plantilla creada. Añade al menos una variante para poder publicarla.',
        ];
    }

    /**
     * Publica una plantilla base.
     * Requiere que exista al menos una variante activa.
     */
    public function publishGeneric(int $genericId, bool $activateAllVariants = false): PteSkuGenerico
    {
        return DB::transaction(function () use ($genericId, $activateAllVariants) {
            $generic = PteSkuGenerico::query()->lockForUpdate()->findOrFail($genericId);

            if ($activateAllVariants && !$generic->modo_kit) {
                PteSku::query()
                    ->where('sku_generico_id', $generic->id)
                    ->update(['activo' => true]);
            }

            $hasActiveVariants = PteSku::query()
                ->where('sku_generico_id', $generic->id)
                ->where('activo', true)
                ->exists();

            if ($generic->modo_kit) {
                if (!$generic->componentes_kit) KitPlan::fail('Define los componentes antes de publicar el kit.');
                KitPersonalization::validateCatalog($generic->componentes_kit, $generic->id);
            } elseif (! $hasActiveVariants) {
                throw ValidationException::withMessages([
                    'publicar' => ['No se puede publicar la plantilla: debe tener al menos una variante activa.'],
                ]);
            }

            return $this->syncCatalogState($generic, 'publicado');
        });
    }

    /**
     * Archiva una plantilla base:
     * - Desactiva atómicamente todas sus variantes.
     * - Registra usuario, fecha y motivo de archivo.
     * - Preserva intacto el stock, movimientos y demás relaciones.
     */
    public function archiveGeneric(int $genericId, ?int $userId = null, ?string $reason = null): PteSkuGenerico
    {
        return DB::transaction(function () use ($genericId, $userId, $reason) {
            $generic = PteSkuGenerico::query()->lockForUpdate()->findOrFail($genericId);

            PteSku::query()
                ->where('sku_generico_id', $generic->id)
                ->where('activo', true)
                ->update(['activo' => false]);

            return $this->syncCatalogState($generic, 'archivado', $userId, $reason);
        });
    }

    /**
     * Obtiene el preview de impacto de archivar una plantilla.
     */
    public function getArchivePreview(int $genericId): array
    {
        $generic = PteSkuGenerico::findOrFail($genericId);
        $variants = PteSku::where('sku_generico_id', $generic->id)->get(['sku', 'activo']);
        $skus = $variants->pluck('sku')->all();

        $activeVariantsCount = $variants->where('activo', true)->count();
        $stocks = PteStock::whereIn('sku', $skus)->get(['cantidad', 'reserved_stock']);
        $totalStock = (int) $stocks->sum('cantidad');
        $reservedStock = (int) $stocks->sum('reserved_stock');

        $pendingRequests = Peticion::whereIn('sku', $skus)
            ->whereHas('estado', fn ($q) => $q->where('nombre', 'Pendiente'))
            ->count();

        return [
            'generic_id' => $generic->id,
            'generic_nombre' => $generic->nombre,
            'variantes_afectadas' => $activeVariantsCount,
            'variantes_totales' => $variants->count(),
            'stock_fisico_total' => $totalStock,
            'stock_reservado' => $reservedStock,
            'solicitudes_pendientes' => $pendingRequests,
        ];
    }

    /**
     * Reactiva una plantilla como borrador.
     */
    public function reactivateGeneric(int $genericId, bool $reactivateVariants = false): PteSkuGenerico
    {
        return DB::transaction(function () use ($genericId, $reactivateVariants) {
            $generic = PteSkuGenerico::query()->lockForUpdate()->findOrFail($genericId);

            if ($reactivateVariants) {
                PteSku::query()
                    ->where('sku_generico_id', $generic->id)
                    ->update(['activo' => true]);
            }

            return $this->syncCatalogState($generic, 'borrador');
        });
    }

    /**
     * Archiva una variante individual preservando su stock e historial.
     */
    public function archiveVariant(string $sku): PteSku
    {
        return DB::transaction(function () use ($sku) {
            $variant = PteSku::query()->lockForUpdate()->where('sku', $sku)->firstOrFail();
            $variant->update(['activo' => false]);

            return $variant->refresh();
        });
    }

    /**
     * Archiva una lista de variantes seleccionadas de una plantilla.
     */
    public function archiveVariants(int $genericId, array $skus): array
    {
        return DB::transaction(function () use ($genericId, $skus) {
            $generic = PteSkuGenerico::query()->lockForUpdate()->findOrFail($genericId);

            $count = PteSku::query()
                ->where('sku_generico_id', $generic->id)
                ->whereIn('sku', $skus)
                ->where('activo', true)
                ->update(['activo' => false]);

            return [
                'archivadas' => $count,
                'message' => "Se archivaron {$count} variantes correctamente.",
            ];
        });
    }

    /**
     * Reactiva una lista de variantes seleccionadas de una plantilla.
     */
    public function reactivateVariants(int $genericId, array $skus): array
    {
        return DB::transaction(function () use ($genericId, $skus) {
            $generic = PteSkuGenerico::query()->lockForUpdate()->findOrFail($genericId);

            $count = PteSku::query()
                ->where('sku_generico_id', $generic->id)
                ->whereIn('sku', $skus)
                ->where('activo', false)
                ->update(['activo' => true]);

            return [
                'reactivadas' => $count,
                'message' => "Se reactivaron {$count} variantes correctamente.",
            ];
        });
    }

    /**
     * Actualiza la plantilla y mantiene consistente su estado con las variantes.
     */
    public function updateGeneric(int $genericId, array $attributes, ?int $userId = null): PteSkuGenerico
    {
        return DB::transaction(function () use ($genericId, $attributes, $userId) {
            $generic = PteSkuGenerico::query()->lockForUpdate()->findOrFail($genericId);

            // Publicar vía edición debe cumplir las mismas reglas que publishGeneric().
            $isKit = (bool) ($attributes['modo_kit'] ?? $generic->modo_kit ?? false);
            if ($isKit && $generic->es_informativo_cotizacion) KitPlan::fail('Un ítem informativo no puede funcionar como kit operativo. Utiliza una plantilla comercial.');
            if (!$isKit && $generic->modo_kit && $generic->estado_catalogo === 'publicado'
                && !$generic->variantes()->where('activo', true)->exists()) {
                // Al volver a producto individual deberá crear variantes antes de cotizarlo.
                $attributes['estado_catalogo'] = 'borrador';
                $attributes['activo'] = false;
            }
            if ($isKit && !$generic->modo_kit) {
                $hasStock = $generic->variantes()->whereHas('stock', fn ($q) => $q->where('cantidad', '>', 0)->orWhere('reserved_stock', '>', 0))->exists();
                if ($hasStock) KitPlan::fail('Este producto tiene stock o reservas. No puede convertirse en kit comercial mientras existan prendas físicas.');
            }
            if (array_key_exists('componentes_kit', $attributes)) {
                $attributes['componentes_kit'] = KitPersonalization::validateCatalog($attributes['componentes_kit'], $generic->id);
            }
            if ($isKit && (($attributes['estado_catalogo'] ?? $generic->estado_catalogo) === 'publicado')) {
                $definitions = $attributes['componentes_kit'] ?? $generic->componentes_kit ?? [];
                if (!$definitions) KitPlan::fail('Un kit publicado debe tener componentes definidos.');
                KitPersonalization::validateCatalog($definitions, $generic->id);
            }
            $wantsPublish = (($attributes['estado_catalogo'] ?? null) === 'publicado')
                || (! array_key_exists('estado_catalogo', $attributes)
                    && array_key_exists('activo', $attributes)
                    && filter_var($attributes['activo'], FILTER_VALIDATE_BOOLEAN));

            if ($wantsPublish && $generic->estado_catalogo !== 'publicado') {
                if ($generic->estado_catalogo === 'archivado') {
                    throw ValidationException::withMessages([
                        'publicar' => ['El producto está archivado. Reactívalo antes de publicarlo.'],
                    ]);
                }

                $hasActiveVariants = PteSku::query()
                    ->where('sku_generico_id', $generic->id)
                    ->where('activo', true)
                    ->exists();

                if (! $hasActiveVariants && !$isKit) {
                    throw ValidationException::withMessages([
                        'publicar' => ['No se puede publicar la plantilla: debe tener al menos una variante activa.'],
                    ]);
                }
            }

            $shouldArchiveVariants = (array_key_exists('estado_catalogo', $attributes) && $attributes['estado_catalogo'] === 'archivado')
                || (!array_key_exists('estado_catalogo', $attributes) && array_key_exists('activo', $attributes) && ! $attributes['activo'] && $generic->estado_catalogo === 'publicado');

            if ($shouldArchiveVariants) {
                PteSku::query()
                    ->where('sku_generico_id', $generic->id)
                    ->where('activo', true)
                    ->update(['activo' => false]);
                $attributes['activo'] = false;
                $attributes['estado_catalogo'] = 'archivado';
                $attributes['archived_by'] = $userId;
                if (! isset($attributes['archived_at'])) {
                    $attributes['archived_at'] = now();
                }
            }

            $generic->update($attributes);

            if (array_key_exists('precio_venta', $attributes) && $attributes['precio_venta'] !== null) {
                PteSku::query()
                    ->where('sku_generico_id', $generic->id)
                    ->update(['precio_venta' => $attributes['precio_venta']]);
            }

            return $generic->refresh();
        });
    }

    /**
     * Agrega nuevas combinaciones o reactiva variantes existentes archivadas.
     * Idempotente para variantes ya activas.
     *
     * @return array{creados: int, reactivados: int, duplicados: int}
     */
    public function addVariants(int $genericId, array $colorIds, array $sizeIds): array
    {
        try {
            return DB::transaction(function () use ($genericId, $colorIds, $sizeIds) {
                $generic = PteSkuGenerico::query()->lockForUpdate()->findOrFail($genericId);
                if ($generic->modo_kit) KitPlan::fail('El kit comercial no genera variantes físicas. Configura las variantes de sus productos hijos.');

                // Solo productos archivados o explícitamente inactivos no-borradores no admiten agregar variantes
                if ($generic->estado_catalogo === 'archivado' || (! $generic->activo && $generic->estado_catalogo !== 'borrador')) {
                    throw new ProductCatalogConflictException(
                        'El producto base está inactivo. Reactívalo antes de agregar o recuperar variantes.'
                    );
                }

                $colorIds = collect($colorIds)->map(fn ($id) => (int) $id)->unique()->values();
                $sizeIds = collect($sizeIds)->map(fn ($id) => (int) $id)->unique()->values();

                $colors = PteColor::query()
                    ->whereIn('id', $colorIds)
                    ->where('is_activo', true)
                    ->get(['id', 'nombre'])
                    ->keyBy('id');
                $sizes = PteTalla::query()
                    ->whereIn('id', $sizeIds)
                    ->where('is_activo', true)
                    ->get(['id', 'nombre'])
                    ->keyBy('id');

                if ($colors->count() !== $colorIds->count()) {
                    throw ValidationException::withMessages([
                        'colores' => ['Uno o más colores no existen o están inactivos.'],
                    ]);
                }
                if ($sizes->count() !== $sizeIds->count()) {
                    throw ValidationException::withMessages([
                        'tallas' => ['Una o más tallas no existen o están inactivas.'],
                    ]);
                }

                if (! $this->skuGenerator->isCanonicalBaseSku((string) $generic->sku_base)) {
                    throw new ProductCatalogConflictException(
                        'El SKU base del producto no tiene un formato válido para generar variantes.'
                    );
                }

                $desired = [];
                foreach ($colorIds as $colorId) {
                    foreach ($sizeIds as $sizeId) {
                        $desired[] = [
                            'color_id' => $colorId,
                            'talla_id' => $sizeId,
                            'sku' => $this->skuGenerator->generateVariantSku(
                                (string) $generic->sku_base,
                                $colorId,
                                $sizeId
                            ),
                        ];
                    }
                }

                $desiredSkus = collect($desired)->pluck('sku');
                $bySku = PteSku::query()
                    ->whereIn('sku', $desiredSkus)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('sku');
                $byCombination = PteSku::query()
                    ->where('sku_generico_id', $generic->id)
                    ->whereIn('color_id', $colorIds)
                    ->whereIn('talla_id', $sizeIds)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy(fn (PteSku $variant) => $variant->color_id.':'.$variant->talla_id);
                $existingSkusWithStock = PteStock::query()
                    ->whereIn('sku', $bySku->keys()->merge($byCombination->pluck('sku'))->unique())
                    ->pluck('sku')
                    ->flip();

                $created = 0;
                $reactivated = 0;
                $duplicates = 0;

                foreach ($desired as $item) {
                    $key = $item['color_id'].':'.$item['talla_id'];
                    $skuMatch = $bySku->get($item['sku']);
                    $combinationMatch = $byCombination->get($key);

                    if ($skuMatch && (
                        (int) $skuMatch->sku_generico_id !== (int) $generic->id
                        || (int) $skuMatch->color_id !== $item['color_id']
                        || (int) $skuMatch->talla_id !== $item['talla_id']
                    )) {
                        throw new ProductCatalogConflictException(
                            "El SKU {$item['sku']} ya pertenece a otro producto o combinación."
                        );
                    }

                    if ($combinationMatch && $combinationMatch->sku !== $item['sku']) {
                        throw new ProductCatalogConflictException(
                            "La combinación seleccionada ya existe con el SKU no canónico {$combinationMatch->sku}."
                        );
                    }

                    $existing = $skuMatch ?: $combinationMatch;
                    if ($existing) {
                        if (! $existingSkusWithStock->has($existing->sku)) {
                            PteStock::query()->create([
                                'sku' => $existing->sku,
                                'cantidad' => 0,
                                'reserved_stock' => 0,
                                'stock_critico' => 5,
                            ]);
                            $existingSkusWithStock->put($existing->sku, 0);
                        }

                        if ($existing->activo) {
                            $duplicates++;
                        } else {
                            $existing->update(['activo' => true]);
                            $reactivated++;
                        }

                        continue;
                    }

                    $colorName = (string) $colors->get($item['color_id'])->nombre;
                    $sizeName = (string) $sizes->get($item['talla_id'])->nombre;
                    $variantName = trim($generic->nombre.' '.$colorName);
                    if ($sizeName !== '' && Str::lower($sizeName) !== 'generico') {
                        $variantName .= ' '.$sizeName;
                    }

                    PteSku::query()->create([
                        'sku' => $item['sku'],
                        'sku_generico_id' => $generic->id,
                        'nombre' => $variantName,
                        'precio_venta' => $generic->precio_venta,
                        'color_id' => $item['color_id'],
                        'talla_id' => $item['talla_id'],
                        'activo' => true,
                    ]);
                    PteStock::query()->create([
                        'sku' => $item['sku'],
                        'cantidad' => 0,
                        'reserved_stock' => 0,
                        'stock_critico' => 5,
                    ]);
                    $created++;
                }

                return [
                    'creados' => $created,
                    'reactivados' => $reactivated,
                    'duplicados' => $duplicates,
                ];
            });
        } catch (QueryException $exception) {
            if ($this->isIntegrityConflict($exception)) {
                throw new ProductCatalogConflictException(
                    'Otra operación creó o modificó una de las variantes seleccionadas. Actualiza el catálogo e inténtalo nuevamente.',
                    previous: $exception
                );
            }

            throw $exception;
        }
    }

    public function renameVariant(int $genericId, string $sku, string $name, string $previousName): PteSku
    {
        return DB::transaction(function () use ($genericId, $sku, $name, $previousName) {
            $variant = PteSku::query()->where('sku_generico_id', $genericId)
                ->where('sku', $sku)->lockForUpdate()->firstOrFail();
            if ($variant->nombre !== $previousName) {
                throw new ProductCatalogConflictException('El nombre cambió mientras lo editabas. Vuelve a abrir el producto para revisar el nombre actual.');
            }
            $variant->update(['nombre' => $name]);
            return $variant;
        });
    }

    private function isIntegrityConflict(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);

        return in_array($sqlState, ['23000', '23505'], true)
            || in_array($driverCode, [19, 1062], true);
    }
}
