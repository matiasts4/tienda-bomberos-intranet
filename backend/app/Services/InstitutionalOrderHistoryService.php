<?php

namespace App\Services;

use App\Models\Compania;
use App\Models\Cotizacion;
use App\Models\Cuerpo;
use App\Models\Despacho;
use App\Models\OrdenCorte;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Consolida el historial cronológico institucional de compras, órdenes de taller
 * y despachos entregados para una Compañía o un Cuerpo de Bomberos.
 */
class InstitutionalOrderHistoryService
{
    /** @var array<int, string> */
    private const APPROVED_QUOTE_STATE_NAMES = [
        'ventas',
        'aprobada',
        'aprobado',
        'aprobada por cliente',
        'aprobada - cotización',
        'aprobada - cotizacion',
    ];

    /** @var array<int, string> */
    private const COMPLETED_WORKSHOP_STATUSES = [
        OrdenCorte::STAGE_TERMINADO,
        'completed',
        'finished',
        'delivered',
    ];

    public function __construct(
        private readonly WorkshopStageService $stageService
    ) {
    }

    /**
     * Construye el historial unificado para una Compañía bomberil específica.
     *
     * @param  array{year?: int|null, status?: string|null, garment_type?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function forCompania(Compania $compania, array $filters = []): array
    {
        $compania->loadMissing(['cuerpo.region:id,nombre', 'cuerpo.comuna:id,nombre']);

        $normalizedFilters = $this->normalizeFilters($filters);

        $approvedQuotes = $this->queryApprovedQuotes(
            fn (Builder $q) => $q->where('compania_id', $compania->id),
            $normalizedFilters
        );

        $workshopOrders = $this->queryWorkshopOrders(
            function (Builder $q) use ($compania) {
                $q->where('compania_id', $compania->id)
                    ->orWhereHas('cotizacion', fn (Builder $c) => $c->where('compania_id', $compania->id));
            },
            $normalizedFilters
        );

        $deliveredOrders = $this->queryDeliveredOrders(
            fn (Builder $q) => $q->whereHas('cotizacion', fn (Builder $c) => $c->where('compania_id', $compania->id)),
            $normalizedFilters
        );

        return [
            'success' => true,
            'company' => [
                'id' => $compania->id,
                'nombre' => $compania->nombre,
                'numero' => $compania->numero,
                'cuerpo_id' => $compania->cuerpo_id,
                'cuerpo' => $compania->cuerpo ? [
                    'id' => $compania->cuerpo->id,
                    'nombre' => $compania->cuerpo->nombre,
                ] : null,
            ],
            'filters' => $normalizedFilters,
            'summary' => $this->buildSummary($approvedQuotes, $workshopOrders, $deliveredOrders),
            'approved_quotes' => $approvedQuotes->values(),
            'workshop_orders' => $workshopOrders->values(),
            'delivered_orders' => $deliveredOrders->values(),
            'timeline' => $this->buildTimeline($approvedQuotes, $workshopOrders, $deliveredOrders),
        ];
    }

    /**
     * Construye el historial unificado para un Cuerpo de Bomberos y todas sus compañías.
     *
     * @param  array{year?: int|null, status?: string|null, garment_type?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function forCuerpo(Cuerpo $cuerpo, array $filters = []): array
    {
        $cuerpo->loadMissing(['region:id,nombre', 'comuna:id,nombre', 'companias:id,cuerpo_id,nombre,numero']);
        $companiaIds = $cuerpo->companias->pluck('id')->all();

        $normalizedFilters = $this->normalizeFilters($filters);

        $approvedQuotes = $this->queryApprovedQuotes(
            function (Builder $q) use ($cuerpo, $companiaIds) {
                $q->where('cuerpo_id', $cuerpo->id);
                if (! empty($companiaIds)) {
                    $q->orWhereIn('compania_id', $companiaIds);
                }
            },
            $normalizedFilters
        );

        $workshopOrders = $this->queryWorkshopOrders(
            function (Builder $q) use ($cuerpo, $companiaIds) {
                $q->where('cuerpo_id', $cuerpo->id);
                if (! empty($companiaIds)) {
                    $q->orWhereIn('compania_id', $companiaIds);
                }
                $q->orWhereHas('cotizacion', function (Builder $c) use ($cuerpo, $companiaIds) {
                    $c->where('cuerpo_id', $cuerpo->id);
                    if (! empty($companiaIds)) {
                        $c->orWhereIn('compania_id', $companiaIds);
                    }
                });
            },
            $normalizedFilters
        );

        $deliveredOrders = $this->queryDeliveredOrders(
            function (Builder $q) use ($cuerpo, $companiaIds) {
                $q->whereHas('cotizacion', function (Builder $c) use ($cuerpo, $companiaIds) {
                    $c->where('cuerpo_id', $cuerpo->id);
                    if (! empty($companiaIds)) {
                        $c->orWhereIn('compania_id', $companiaIds);
                    }
                });
            },
            $normalizedFilters
        );

        return [
            'success' => true,
            'fire_department' => [
                'id' => $cuerpo->id,
                'nombre' => $cuerpo->nombre,
                'rut' => $cuerpo->rut,
                'companias_count' => $cuerpo->companias->count(),
            ],
            'filters' => $normalizedFilters,
            'summary' => $this->buildSummary($approvedQuotes, $workshopOrders, $deliveredOrders),
            'approved_quotes' => $approvedQuotes->values(),
            'workshop_orders' => $workshopOrders->values(),
            'delivered_orders' => $deliveredOrders->values(),
            'timeline' => $this->buildTimeline($approvedQuotes, $workshopOrders, $deliveredOrders),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{year: int|null, status: string|null, garment_type: string|null}
     */
    private function normalizeFilters(array $filters): array
    {
        $year = isset($filters['year']) && $filters['year'] !== ''
            ? (int) $filters['year']
            : null;

        $status = isset($filters['status']) && trim((string) $filters['status']) !== ''
            ? trim((string) $filters['status'])
            : null;

        $garmentType = isset($filters['garment_type']) && trim((string) $filters['garment_type']) !== ''
            ? trim((string) $filters['garment_type'])
            : null;

        return [
            'year' => $year,
            'status' => $status,
            'garment_type' => $garmentType,
        ];
    }

    /**
     * @param  callable(Builder): void  $scopeConstraint
     * @param  array{year: int|null, status: string|null, garment_type: string|null}  $filters
     * @return Collection<int, Cotizacion>
     */
    private function queryApprovedQuotes(callable $scopeConstraint, array $filters): Collection
    {
        $query = Cotizacion::with([
            'estado',
            'detalles',
            'compania:id,cuerpo_id,nombre,numero',
            'cuerpo:id,nombre',
            'ordenesCorte.items',
            'despachoModelo.estado',
        ])
            ->where($scopeConstraint)
            ->whereHas('estado', function (Builder $estadoQuery) {
                $placeholders = implode(', ', array_fill(0, count(self::APPROVED_QUOTE_STATE_NAMES), '?'));
                $estadoQuery->whereRaw("LOWER(nombre) IN ({$placeholders})", self::APPROVED_QUOTE_STATE_NAMES);
            });

        if ($filters['year'] !== null) {
            [$startDate, $endDate, $startDateTime, $endDateTime] = $this->yearBounds($filters['year']);
            $query->where(function (Builder $q) use ($startDate, $endDate, $startDateTime, $endDateTime) {
                $q->whereBetween('fecha_venta', [$startDate, $endDate])
                    ->orWhere(function (Builder $q2) use ($startDate, $endDate) {
                        $q2->whereNull('fecha_venta')
                            ->whereBetween('fecha_creacion', [$startDate, $endDate]);
                    })
                    ->orWhere(function (Builder $q3) use ($startDateTime, $endDateTime) {
                        $q3->whereNull('fecha_venta')
                            ->whereNull('fecha_creacion')
                            ->whereBetween('created_at', [$startDateTime, $endDateTime]);
                    });
            });
        }

        if ($filters['status'] !== null) {
            $statusList = $this->stageService->statusesForFilter($filters['status']);
            $lowerStatus = mb_strtolower($filters['status'], 'UTF-8');

            $query->where(function (Builder $q) use ($statusList, $lowerStatus) {
                $q->whereHas('ordenesCorte', fn (Builder $o) => $o->whereIn('status', $statusList))
                    ->orWhereHas('estado', fn (Builder $e) => $e->whereRaw('LOWER(nombre) LIKE ?', ["%{$lowerStatus}%"]));
            });
        }

        if ($filters['garment_type'] !== null) {
            $pattern = '%' . mb_strtolower($filters['garment_type'], 'UTF-8') . '%';
            $query->whereHas('detalles', function (Builder $d) use ($pattern) {
                $d->whereRaw("LOWER(COALESCE(producto, '')) LIKE ?", [$pattern]);
            });
        }

        return $query
            ->orderByDesc('fecha_venta')
            ->orderByDesc('fecha_creacion')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @param  callable(Builder): void  $scopeConstraint
     * @param  array{year: int|null, status: string|null, garment_type: string|null}  $filters
     * @return Collection<int, OrdenCorte>
     */
    private function queryWorkshopOrders(callable $scopeConstraint, array $filters): Collection
    {
        $query = OrdenCorte::with([
            'items',
            'supplies',
            'cotizacion:id,numero,nombre,total_neto,total,fecha_venta,fecha_creacion',
            'compania:id,cuerpo_id,nombre,numero',
            'cuerpo:id,nombre',
            'responsable:id,name,email',
            'historialEtapas',
        ])->where($scopeConstraint);

        if ($filters['year'] !== null) {
            [$startDate, $endDate, $startDateTime, $endDateTime] = $this->yearBounds($filters['year']);
            $query->where(function (Builder $q) use ($startDate, $endDate, $startDateTime, $endDateTime) {
                $q->whereBetween('created_at', [$startDateTime, $endDateTime])
                    ->orWhereBetween('fecha_compromiso', [$startDate, $endDate]);
            });
        }

        if ($filters['status'] !== null) {
            $query->whereIn('status', $this->stageService->statusesForFilter($filters['status']));
        }

        if ($filters['garment_type'] !== null) {
            $pattern = '%' . mb_strtolower($filters['garment_type'], 'UTF-8') . '%';
            $query->where(function (Builder $q) use ($pattern) {
                $q->whereRaw("LOWER(COALESCE(selected_product, '')) LIKE ?", [$pattern])
                    ->orWhereHas('items', function (Builder $i) use ($pattern) {
                        $i->whereRaw("LOWER(COALESCE(product_type, '')) LIKE ?", [$pattern]);
                    });
            });
        }

        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @param  callable(Builder): void  $scopeConstraint
     * @param  array{year: int|null, status: string|null, garment_type: string|null}  $filters
     * @return Collection<int, Despacho>
     */
    private function queryDeliveredOrders(callable $scopeConstraint, array $filters): Collection
    {
        $query = Despacho::with([
            'estado',
            'cotizacion.estado',
            'cotizacion.detalles',
            'cotizacion.compania:id,cuerpo_id,nombre,numero',
        ])
            ->where($scopeConstraint)
            ->where(function (Builder $q) {
                $q->whereHas('estado', function (Builder $e) {
                    $e->whereRaw("LOWER(nombre) LIKE 'entregado%'");
                })->orWhereNotNull('fecha_despacho');
            });

        if ($filters['year'] !== null) {
            [$startDate, $endDate, $startDateTime, $endDateTime] = $this->yearBounds($filters['year']);
            $query->where(function (Builder $q) use ($startDate, $endDate, $startDateTime, $endDateTime) {
                $q->whereBetween('fecha_despacho', [$startDate, $endDate])
                    ->orWhere(function (Builder $q2) use ($startDateTime, $endDateTime) {
                        $q2->whereNull('fecha_despacho')
                            ->whereBetween('created_at', [$startDateTime, $endDateTime]);
                    });
            });
        }

        if ($filters['status'] !== null) {
            $canonical = $this->stageService->normalizeStage($filters['status']);
            $lowerStatus = mb_strtolower($filters['status'], 'UTF-8');

            if ($canonical !== OrdenCorte::STAGE_TERMINADO && ! str_contains($lowerStatus, 'entregad')) {
                $statusList = $this->stageService->statusesForFilter($filters['status']);
                $query->where(function (Builder $q) use ($statusList, $lowerStatus) {
                    $q->whereHas('estado', fn (Builder $e) => $e->whereRaw('LOWER(nombre) LIKE ?', ["%{$lowerStatus}%"]))
                        ->orWhereHas('cotizacion.ordenesCorte', fn (Builder $o) => $o->whereIn('status', $statusList));
                });
            }
        }

        if ($filters['garment_type'] !== null) {
            $pattern = '%' . mb_strtolower($filters['garment_type'], 'UTF-8') . '%';
            $query->whereHas('cotizacion.detalles', function (Builder $d) use ($pattern) {
                $d->whereRaw("LOWER(COALESCE(producto, '')) LIKE ?", [$pattern]);
            });
        }

        return $query
            ->orderByDesc('fecha_despacho')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @param  Collection<int, Cotizacion>  $approvedQuotes
     * @param  Collection<int, OrdenCorte>  $workshopOrders
     * @param  Collection<int, Despacho>  $deliveredOrders
     * @return array<string, int>
     */
    private function buildSummary(
        Collection $approvedQuotes,
        Collection $workshopOrders,
        Collection $deliveredOrders
    ): array {
        $completedWorkshopOrders = $workshopOrders->filter(
            fn (OrdenCorte $order) => in_array(
                mb_strtolower((string) $order->status, 'UTF-8'),
                self::COMPLETED_WORKSHOP_STATUSES,
                true
            )
        )->count();

        return [
            'total_approved_quotes' => $approvedQuotes->count(),
            'total_workshop_orders' => $workshopOrders->count(),
            'completed_workshop_orders' => $completedWorkshopOrders,
            'delivered_orders' => $deliveredOrders->count(),
            'total_amount' => (int) $approvedQuotes->sum('total'),
        ];
    }

    /**
     * Combina cotizaciones aprobadas, órdenes de taller y despachos entregados
     * en una línea de tiempo cronológica descendente.
     *
     * @param  Collection<int, Cotizacion>  $approvedQuotes
     * @param  Collection<int, OrdenCorte>  $workshopOrders
     * @param  Collection<int, Despacho>  $deliveredOrders
     * @return array<int, array<string, mixed>>
     */
    private function buildTimeline(
        Collection $approvedQuotes,
        Collection $workshopOrders,
        Collection $deliveredOrders
    ): array {
        $entries = collect();

        foreach ($approvedQuotes as $quote) {
            $date = $quote->fecha_venta?->format('Y-m-d')
                ?? $quote->fecha_creacion?->format('Y-m-d')
                ?? $quote->created_at?->format('Y-m-d');

            $entries->push([
                'type' => 'approved_quote',
                'id' => $quote->id,
                'reference' => $quote->numero ?: ('CT-' . str_pad((string) $quote->id, 4, '0', STR_PAD_LEFT)),
                'title' => $quote->nombre ?: 'Cotización aprobada',
                'date' => $date,
                'timestamp' => $quote->created_at?->toIso8601String(),
                'status' => $quote->estado?->nombre ?? 'Aprobada',
                'garments' => $quote->detalles->pluck('producto')->filter()->unique()->values()->all(),
                'total_quantity' => (int) $quote->detalles->sum('cantidad'),
                'amount' => (int) $quote->total,
                'cotizacion_id' => $quote->id,
            ]);
        }

        foreach ($workshopOrders as $order) {
            $garments = $order->items->pluck('product_type')->filter()->unique()->values()->all();
            if (empty($garments) && $order->selected_product) {
                $garments = [$order->selected_product];
            }

            $entries->push([
                'type' => 'workshop_order',
                'id' => $order->id,
                'reference' => 'OC-' . $order->code,
                'title' => $order->selected_product,
                'date' => $order->created_at?->format('Y-m-d'),
                'timestamp' => $order->created_at?->toIso8601String(),
                'status' => $this->stageService->currentStageKey($order->status),
                'garments' => $garments,
                'total_quantity' => (int) ($order->total_unidades ?: $order->items->sum('quantity')),
                'amount' => $order->cotizacion ? (int) $order->cotizacion->total : null,
                'cotizacion_id' => $order->cotizacion_id,
            ]);
        }

        foreach ($deliveredOrders as $dispatch) {
            $quote = $dispatch->cotizacion;
            $date = $dispatch->fecha_despacho
                ? substr((string) $dispatch->fecha_despacho, 0, 10)
                : $dispatch->created_at?->format('Y-m-d');

            $entries->push([
                'type' => 'delivered_order',
                'id' => $dispatch->id,
                'reference' => 'DESP-' . str_pad((string) $dispatch->id, 4, '0', STR_PAD_LEFT),
                'title' => $quote?->nombre ?: 'Pedido entregado',
                'date' => $date,
                'timestamp' => $dispatch->created_at?->toIso8601String(),
                'status' => $dispatch->estado?->nombre ?? 'Entregado',
                'garments' => $quote ? $quote->detalles->pluck('producto')->filter()->unique()->values()->all() : [],
                'total_quantity' => $quote ? (int) $quote->detalles->sum('cantidad') : 0,
                'amount' => $quote ? (int) $quote->total : null,
                'cotizacion_id' => $dispatch->cotizacion_id,
            ]);
        }

        return $entries
            ->sortByDesc(fn (array $item) => sprintf(
                '%s|%s|%010d',
                $item['date'] ?? '0000-00-00',
                $item['timestamp'] ?? '0000-00-00T00:00:00Z',
                (int) $item['id']
            ))
            ->values()
            ->all();
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: string}
     */
    private function yearBounds(int $year): array
    {
        return [
            sprintf('%04d-01-01', $year),
            sprintf('%04d-12-31', $year),
            sprintf('%04d-01-01 00:00:00', $year),
            sprintf('%04d-12-31 23:59:59', $year),
        ];
    }
}
