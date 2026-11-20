<?php

namespace App\Services;

use App\Models\Cotizacion;
use App\Models\DetalleCotizacion;
use App\Models\Estado;
use App\Models\FactoryLog;
use App\Models\HistorialEtapa;
use App\Models\OrdenCorte;
use App\Models\User;
use App\Support\DeliveryDeadline;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Servicio de dominio para generación de órdenes de corte desde cotizaciones
 * aprobadas y máquina de estados de etapas operativas de taller.
 */
class WorkshopStageService
{
    /** @var array<string, array<int, string>> */
    private const STAGE_ALIASES = [
        OrdenCorte::STAGE_CORTE => [
            'corte',
            'en corte',
            'en-corte',
            'cutting',
            'in-cutting',
        ],
        OrdenCorte::STAGE_CONFECCION => [
            'confeccion',
            'confección',
            'en confeccion',
            'en confección',
            'en-confeccion',
            'sewing',
            'in-assembly',
            'cutting-finished',
        ],
        OrdenCorte::STAGE_EMPAQUE => [
            'empaque',
            'empaque y control de calidad',
            'packing',
            'quality-control',
            'in-quality-control',
            'control-calidad',
            'control de calidad',
            'pending-warehouse',
        ],
        OrdenCorte::STAGE_TERMINADO => [
            'terminado',
            'finished',
            'completed',
            'delivered',
            'transit',
            'despacho',
        ],
    ];

    /** @var array<string, array<int, string>> */
    private const ALLOWED_TRANSITIONS = [
        'order-received' => [OrdenCorte::STAGE_CORTE],
        OrdenCorte::STAGE_CORTE => [OrdenCorte::STAGE_CONFECCION],
        OrdenCorte::STAGE_CONFECCION => [OrdenCorte::STAGE_EMPAQUE],
        OrdenCorte::STAGE_EMPAQUE => [OrdenCorte::STAGE_TERMINADO],
        OrdenCorte::STAGE_TERMINADO => [],
    ];

    /** @var array<string, array{nombre: string, color: string, orden: int}> */
    private const STAGE_ESTADO_META = [
        OrdenCorte::STAGE_CORTE => ['nombre' => 'Corte', 'color' => '#f59e0b', 'orden' => 1],
        OrdenCorte::STAGE_CONFECCION => ['nombre' => 'Confección', 'color' => '#3b82f6', 'orden' => 2],
        OrdenCorte::STAGE_EMPAQUE => ['nombre' => 'Empaque', 'color' => '#8b5cf6', 'orden' => 3],
        OrdenCorte::STAGE_TERMINADO => ['nombre' => 'Terminado', 'color' => '#10b981', 'orden' => 4],
    ];

    /**
     * Determina si una cotización se encuentra en estado aprobado / venta.
     */
    public function isQuoteApproved(Cotizacion $cotizacion): bool
    {
        $estadoNombre = trim((string) ($cotizacion->estado?->nombre ?? ''));
        if ($estadoNombre === '') {
            return false;
        }

        if (in_array($estadoNombre, DeliveryDeadline::ESTADOS_VENTA, true)) {
            return true;
        }

        $normalized = mb_strtolower($estadoNombre, 'UTF-8');

        return in_array($normalized, [
            'ventas',
            'aprobada',
            'aprobado',
            'aprobada por cliente',
            'aprobada - cotización',
            'aprobada - cotizacion',
        ], true);
    }

    /**
     * Normaliza un nombre o alias de etapa hacia su clave canónica de taller.
     */
    public function normalizeStage(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $normalized = mb_strtolower(trim($raw), 'UTF-8');
        $normalized = str_replace('_', '-', $normalized);
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        if ($normalized === '') {
            return null;
        }

        foreach (self::STAGE_ALIASES as $canonical => $aliases) {
            if ($normalized === $canonical || in_array($normalized, $aliases, true)) {
                return $canonical;
            }
        }

        return null;
    }

    /**
     * Devuelve la clave actual de estado de una orden para evaluación de máquina de estados.
     */
    public function currentStageKey(?string $status): string
    {
        $raw = mb_strtolower(trim((string) $status), 'UTF-8');
        if ($raw === '' || $raw === 'order-received') {
            return 'order-received';
        }

        return $this->normalizeStage($raw) ?? $raw;
    }

    /**
     * Retorna todos los valores almacenables en BD que equivalen a una etapa al filtrar.
     *
     * @return array<int, string>
     */
    public function statusesForFilter(string $filter): array
    {
        $canonical = $this->normalizeStage($filter);
        if ($canonical === null) {
            return [trim($filter)];
        }

        $map = [
            OrdenCorte::STAGE_CORTE => ['corte', 'in-cutting', 'cutting', 'order-received'],
            OrdenCorte::STAGE_CONFECCION => ['confeccion', 'in-assembly', 'sewing', 'cutting-finished'],
            OrdenCorte::STAGE_EMPAQUE => ['empaque', 'in-quality-control', 'quality-control', 'pending-warehouse'],
            OrdenCorte::STAGE_TERMINADO => ['terminado', 'completed', 'finished', 'delivered', 'transit'],
        ];

        return $map[$canonical] ?? [$canonical];
    }

    /**
     * Verifica si está permitido avanzar desde el estado actual hacia la etapa objetivo.
     */
    public function canTransition(?string $currentStatus, string $targetStage): bool
    {
        $currentKey = $this->currentStageKey($currentStatus);
        $allowed = self::ALLOWED_TRANSITIONS[$currentKey] ?? [];

        return in_array($targetStage, $allowed, true);
    }

    /**
     * Retorna las etapas válidas siguientes desde el estado actual.
     *
     * @return array<int, string>
     */
    public function allowedNextStages(?string $currentStatus): array
    {
        $currentKey = $this->currentStageKey($currentStatus);

        return self::ALLOWED_TRANSITIONS[$currentKey] ?? [];
    }

    /**
     * Obtiene o crea el registro en `estados` (scope = taller) para una etapa canónica.
     */
    public function resolveEstadoForStage(string $stage): Estado
    {
        $meta = self::STAGE_ESTADO_META[$stage] ?? [
            'nombre' => ucfirst($stage),
            'color' => '#6b7280',
            'orden' => 99,
        ];

        return Estado::firstOrCreate(
            ['scope' => 'taller', 'nombre' => $meta['nombre']],
            ['color' => $meta['color'], 'orden' => $meta['orden']]
        );
    }

    /**
     * Genera una orden de corte correlativa con sus ítems, tallas y personalizaciones
     * a partir de una cotización aprobada, registrando el ingreso inicial en bitácora.
     *
     * @param  array<string, mixed>  $overrides
     *
     * @throws ValidationException
     */
    public function createFromQuote(Cotizacion $cotizacion, User $user, array $overrides = []): OrdenCorte
    {
        $cotizacion->load(['estado', 'detalles', 'cliente', 'cuerpo', 'compania.cuerpo', 'contacto']);

        if (! $this->isQuoteApproved($cotizacion)) {
            $estadoActual = $cotizacion->estado?->nombre ?? 'sin estado';

            throw ValidationException::withMessages([
                'quote' => ["Solo las cotizaciones aprobadas pueden generar una orden de corte (estado actual: {$estadoActual})."],
            ]);
        }

        if ($cotizacion->detalles->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => ['La cotización no posee ítems registrados para generar una orden de corte.'],
            ]);
        }

        if (empty($overrides['allow_duplicate']) && OrdenCorte::where('cotizacion_id', $cotizacion->id)->exists()) {
            throw ValidationException::withMessages([
                'quote' => ['Esta cotización ya cuenta con una orden de corte asociada.'],
            ]);
        }

        $itemsPayload = $this->extractItemsFromQuote($cotizacion);
        $totalUnits = array_sum(array_column($itemsPayload, 'quantity'));
        $primaryProduct = $itemsPayload[0]['product_type']
            ?? ($cotizacion->nombre ?: 'Prenda Institucional');

        $estimatedDays = isset($overrides['estimated_days'])
            ? (int) $overrides['estimated_days']
            : $this->parseEstimatedDays($cotizacion->plazo_produccion);

        $fechaCompromiso = $overrides['fecha_compromiso'] ?? $cotizacion->fecha_entrega;
        $responsableId = $overrides['responsable_id'] ?? $user->id;
        $quoteRef = $cotizacion->numero ?: ('CT-' . str_pad((string) $cotizacion->id, 4, '0', STR_PAD_LEFT));
        $notes = $overrides['notes'] ?? $cotizacion->observaciones;

        return DB::transaction(function () use (
            $cotizacion,
            $user,
            $itemsPayload,
            $totalUnits,
            $primaryProduct,
            $estimatedDays,
            $fechaCompromiso,
            $responsableId,
            $quoteRef,
            $notes,
            $overrides
        ) {
            $nextCode = $this->generateNextSequentialCode();

            $ordenCorte = OrdenCorte::create([
                'code' => $nextCode,
                'cotizacion_id' => $cotizacion->id,
                'cuerpo_id' => $cotizacion->cuerpo_id ?? $cotizacion->compania?->cuerpo_id,
                'compania_id' => $cotizacion->compania_id,
                'responsable_id' => $responsableId,
                'client' => $cotizacion->nombre_cliente,
                'selected_product' => $primaryProduct,
                'status' => OrdenCorte::STAGE_CORTE,
                'notes' => $notes,
                'estimated_days' => $estimatedDays,
                'fecha_compromiso' => $fechaCompromiso,
                'progress' => OrdenCorte::STAGE_PROGRESS[OrdenCorte::STAGE_CORTE],
                'total_unidades' => $totalUnits,
                'unidades_en_taller' => $totalUnits,
                'unidades_en_reparacion' => 0,
                'unidades_listas' => 0,
            ]);

            foreach ($itemsPayload as $itemData) {
                $ordenCorte->items()->create($itemData);
            }

            if (! empty($overrides['supplies']) && is_array($overrides['supplies'])) {
                foreach ($overrides['supplies'] as $supplyData) {
                    $ordenCorte->supplies()->create($supplyData);
                }
            }

            $estadoInicial = $this->resolveEstadoForStage(OrdenCorte::STAGE_CORTE);

            HistorialEtapa::create([
                'model_type' => OrdenCorte::class,
                'model_id' => $ordenCorte->id,
                'user_id' => $user->id,
                'estado_anterior_id' => null,
                'estado_nuevo_id' => $estadoInicial->id,
                'etapa_anterior' => null,
                'etapa_nueva' => OrdenCorte::STAGE_CORTE,
                'observaciones' => "Orden generada automáticamente desde cotización {$quoteRef}",
            ]);

            FactoryLog::create([
                'action' => 'Orden Generada desde Cotización',
                'description' => "Se generó la orden de corte {$ordenCorte->code} desde cotización {$quoteRef} en etapa Corte",
                'details' => [
                    'code' => $ordenCorte->code,
                    'cotizacion_id' => $cotizacion->id,
                    'cotizacion_numero' => $quoteRef,
                    'stage' => OrdenCorte::STAGE_CORTE,
                    'total_unidades' => $totalUnits,
                ],
                'entity_type' => 'CuttingOrder',
                'entity_id' => $ordenCorte->id,
                'user_id' => $user->id,
            ]);

            return $ordenCorte->load([
                'items',
                'supplies',
                'cotizacion.estado',
                'compania',
                'cuerpo',
                'responsable:id,name,email',
                'historialEtapas',
            ]);
        });
    }

    /**
     * Ejecuta una transición validada en la máquina de estados de taller y audita el cambio.
     *
     * @throws ValidationException
     */
    public function transitionStage(
        OrdenCorte $ordenCorte,
        string $rawTargetStage,
        User $user,
        ?string $observaciones = null
    ): OrdenCorte {
        $targetStage = $this->normalizeStage($rawTargetStage);

        if ($targetStage === null) {
            throw ValidationException::withMessages([
                'stage' => ['Etapa operativa inválida. Etapas permitidas: corte, confeccion, empaque, terminado.'],
            ]);
        }

        return DB::transaction(function () use ($ordenCorte, $targetStage, $user, $observaciones) {
            $locked = OrdenCorte::lockForUpdate()->findOrFail($ordenCorte->id);
            $currentKey = $this->currentStageKey($locked->status);

            if (! $this->canTransition($currentKey, $targetStage)) {
                $allowed = $this->allowedNextStages($currentKey);
                $hint = empty($allowed)
                    ? 'La orden ya se encuentra en su etapa final.'
                    : 'Etapa siguiente permitida: ' . implode(', ', $allowed) . '.';

                throw ValidationException::withMessages([
                    'stage' => ["Transición de etapa inválida desde '{$currentKey}' hacia '{$targetStage}'. {$hint}"],
                ]);
            }

            $estadoAnterior = in_array($currentKey, OrdenCorte::WORKSHOP_STAGES, true)
                ? $this->resolveEstadoForStage($currentKey)
                : null;
            $estadoNuevo = $this->resolveEstadoForStage($targetStage);

            $updateData = [
                'status' => $targetStage,
                'progress' => OrdenCorte::STAGE_PROGRESS[$targetStage] ?? $locked->progress,
            ];

            if ($targetStage === OrdenCorte::STAGE_TERMINADO && $locked->total_unidades > 0) {
                $updateData['unidades_listas'] = $locked->total_unidades;
                $updateData['unidades_en_taller'] = 0;
            }

            if ($observaciones !== null && trim($observaciones) !== '') {
                $updateData['notes'] = $observaciones;
            }

            $locked->update($updateData);

            HistorialEtapa::create([
                'model_type' => OrdenCorte::class,
                'model_id' => $locked->id,
                'user_id' => $user->id,
                'estado_anterior_id' => $estadoAnterior?->id,
                'estado_nuevo_id' => $estadoNuevo->id,
                'etapa_anterior' => $currentKey,
                'etapa_nueva' => $targetStage,
                'observaciones' => $observaciones,
            ]);

            $prevLabel = OrdenCorte::STAGE_LABELS[$currentKey] ?? $currentKey;
            $nextLabel = OrdenCorte::STAGE_LABELS[$targetStage] ?? $targetStage;

            FactoryLog::create([
                'action' => 'Cambio de Etapa',
                'description' => "Orden {$locked->code} avanzó de {$prevLabel} a {$nextLabel}",
                'details' => [
                    'code' => $locked->code,
                    'previous_stage' => $currentKey,
                    'new_stage' => $targetStage,
                    'observaciones' => $observaciones,
                ],
                'entity_type' => 'CuttingOrder',
                'entity_id' => $locked->id,
                'user_id' => $user->id,
            ]);

            return $locked->fresh()->load([
                'items',
                'supplies',
                'cotizacion.estado',
                'compania',
                'cuerpo',
                'responsable:id,name,email',
                'historialEtapas',
            ]);
        });
    }

    /**
     * Mapea los detalles y configuraciones de personalización de la cotización
     * hacia ítems de confección de la orden de corte.
     *
     * @return array<int, array<string, mixed>>
     */
    private function extractItemsFromQuote(Cotizacion $cotizacion): array
    {
        $items = [];

        foreach ($cotizacion->detalles as $detalle) {
            $expanded = $this->expandDetalleItems($detalle);
            foreach ($expanded as $entry) {
                $items[] = $entry;
            }
        }

        return $items;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function expandDetalleItems(DetalleCotizacion $detalle): array
    {
        $productName = trim((string) ($detalle->producto ?: ($detalle->sku ?: 'Prenda Institucional')));
        $config = is_array($detalle->configuracion) ? $detalle->configuracion : [];
        $defaultFabric = $config['fabric_type'] ?? $config['tela'] ?? 'Estándar';

        $grupos = $config['grupos'] ?? null;
        if (is_array($grupos) && ! empty($grupos)) {
            $rows = [];
            foreach ($grupos as $grupo) {
                if (! is_array($grupo)) {
                    continue;
                }

                $tallas = $grupo['tallas'] ?? [];
                if (! is_array($tallas) || empty($tallas)) {
                    continue;
                }

                $groupPersonalizacion = array_filter([
                    'is_personalizable' => (bool) $detalle->is_personalizable,
                    'tipo_personalizacion' => $detalle->tipo_personalizacion,
                    'nombre_grupo' => $grupo['nombre_grupo'] ?? null,
                    'matrices' => $grupo['matrices'] ?? null,
                    'variables' => $grupo['variables'] ?? null,
                ], fn ($value) => $value !== null && $value !== false && $value !== []);

                foreach ($tallas as $tallaRow) {
                    if (! is_array($tallaRow)) {
                        continue;
                    }

                    $qty = (int) ($tallaRow['cantidad'] ?? 0);
                    if ($qty <= 0) {
                        continue;
                    }

                    $rows[] = [
                        'detalle_cotizacion_id' => $detalle->id,
                        'sku' => $detalle->sku,
                        'product_type' => $productName,
                        'size' => (string) ($tallaRow['talla'] ?? $detalle->talla ?? 'Estándar'),
                        'color' => (string) ($tallaRow['color'] ?? $detalle->color ?? 'Institucional'),
                        'quantity' => $qty,
                        'fabric_type' => (string) $defaultFabric,
                        'personalizacion' => empty($groupPersonalizacion) ? null : $groupPersonalizacion,
                        'notes' => $grupo['nombre_grupo'] ?? null,
                    ];
                }
            }

            if (! empty($rows)) {
                return $rows;
            }
        }

        $personalizacion = array_filter([
            'is_personalizable' => (bool) $detalle->is_personalizable,
            'tipo_personalizacion' => $detalle->tipo_personalizacion,
            'genero' => $detalle->genero,
            'configuracion' => ! empty($config) ? $config : null,
        ], fn ($value) => $value !== null && $value !== false && $value !== []);

        return [[
            'detalle_cotizacion_id' => $detalle->id,
            'sku' => $detalle->sku,
            'product_type' => $productName,
            'size' => (string) ($detalle->talla ?: 'Estándar'),
            'color' => (string) ($detalle->color ?: 'Institucional'),
            'quantity' => max(1, (int) ($detalle->cantidad ?: 1)),
            'fabric_type' => (string) $defaultFabric,
            'personalizacion' => empty($personalizacion) ? null : $personalizacion,
            'notes' => $detalle->tipo_personalizacion
                ? "Personalización: {$detalle->tipo_personalizacion}"
                : null,
        ]];
    }

    private function generateNextSequentialCode(): string
    {
        $lastOrder = OrdenCorte::orderBy('id', 'desc')->lockForUpdate()->first();
        $nextCode = $lastOrder ? max(1000, intval($lastOrder->code) + 1) : 1000;

        while (OrdenCorte::where('code', (string) $nextCode)->exists()) {
            $nextCode++;
        }

        return (string) $nextCode;
    }

    private function parseEstimatedDays(?string $plazo): int
    {
        if ($plazo && preg_match('/(\d+)/', $plazo, $matches)) {
            return max(1, (int) $matches[1]);
        }

        return 7;
    }
}
