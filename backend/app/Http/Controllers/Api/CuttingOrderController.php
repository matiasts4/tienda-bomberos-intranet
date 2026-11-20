<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cotizacion;
use App\Models\FactoryLog;
use App\Models\OrdenCorte;
use App\Services\WorkshopStageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CuttingOrderController extends Controller
{
    public function __construct(
        private readonly WorkshopStageService $stageService
    ) {
    }

    /**
     * Lista las órdenes de corte con filtros opcionales por etapa, cotización,
     * compañía, cuerpo o texto de búsqueda.
     */
    public function index(Request $request)
    {
        $query = OrdenCorte::with([
            'items',
            'supplies',
            'cotizacion.estado',
            'compania',
            'cuerpo',
            'responsable:id,name,email',
            'historialEtapas',
        ]);

        $stageFilter = $request->query('stage')
            ?? $request->query('etapa')
            ?? $request->query('status');

        if ($stageFilter !== null && trim((string) $stageFilter) !== '') {
            $query->whereIn('status', $this->stageService->statusesForFilter((string) $stageFilter));
        }

        if ($request->filled('cotizacion_id')) {
            $query->where('cotizacion_id', (int) $request->query('cotizacion_id'));
        }

        if ($request->filled('compania_id')) {
            $query->where('compania_id', (string) $request->query('compania_id'));
        }

        if ($request->filled('cuerpo_id')) {
            $query->where('cuerpo_id', (string) $request->query('cuerpo_id'));
        }

        if ($request->filled('search')) {
            $search = '%' . trim((string) $request->query('search')) . '%';
            $query->where(function ($sub) use ($search) {
                $sub->where('code', 'like', $search)
                    ->orWhere('client', 'like', $search)
                    ->orWhere('selected_product', 'like', $search);
            });
        }

        $orders = $query->orderBy('created_at', 'desc')->orderBy('id', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    /**
     * Genera automáticamente una orden de corte a partir de una cotización aprobada.
     */
    public function storeFromQuote(Request $request, $quoteId)
    {
        $cotizacion = ctype_digit((string) $quoteId)
            ? Cotizacion::find((int) $quoteId)
            : null;

        if (! $cotizacion) {
            return response()->json([
                'success' => false,
                'message' => 'Cotización no encontrada',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'notes' => 'nullable|string',
            'estimated_days' => 'nullable|integer|min:1',
            'fecha_compromiso' => 'nullable|date',
            'responsable_id' => 'nullable|integer|exists:users,id',
            'allow_duplicate' => 'nullable|boolean',
            'supplies' => 'nullable|array',
            'supplies.*.name' => 'required_with:supplies|string',
            'supplies.*.type' => 'required_with:supplies|string',
            'supplies.*.quantity' => 'required_with:supplies|numeric|min:0.01',
            'supplies.*.unit' => 'required_with:supplies|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $ordenCorte = $this->stageService->createFromQuote(
                $cotizacion,
                $request->user(),
                $validator->validated()
            );

            return response()->json([
                'success' => true,
                'message' => 'Orden de corte generada exitosamente desde cotización',
                'data' => $ordenCorte,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Store a newly created cutting order in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'client' => 'nullable|string|max:255',
            'selected_product' => 'required|string|max:255',
            'notes' => 'nullable|string',
            'estimated_days' => 'nullable|integer',
            'items' => 'required|array|min:1',
            'items.*.product_type' => 'required|string',
            'items.*.size' => 'required|string',
            'items.*.color' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.fabric_type' => 'required|string',
            'supplies' => 'nullable|array',
            'supplies.*.name' => 'required_with:supplies|string',
            'supplies.*.type' => 'required_with:supplies|string',
            'supplies.*.quantity' => 'required_with:supplies|numeric|min:0.01',
            'supplies.*.unit' => 'required_with:supplies|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            return DB::transaction(function () use ($request) {
                $lastOrder = OrdenCorte::orderBy('id', 'desc')->first();
                $nextCode = $lastOrder ? (intval($lastOrder->code) + 1) : 1000;

                $ordenCorte = OrdenCorte::create([
                    'code' => (string) $nextCode,
                    'client' => $request->client ?? 'Orden de Corte General',
                    'selected_product' => $request->selected_product,
                    'status' => 'order-received',
                    'notes' => $request->notes,
                    'estimated_days' => $request->estimated_days ?? 7,
                    'progress' => 0,
                ]);

                foreach ($request->items as $itemData) {
                    $ordenCorte->items()->create($itemData);
                }

                if ($request->has('supplies')) {
                    foreach ($request->supplies as $supplyData) {
                        $ordenCorte->supplies()->create($supplyData);
                    }
                }

                $ordenCorte->load(['items', 'supplies']);

                return response()->json([
                    'success' => true,
                    'message' => 'Orden de corte creada exitosamente',
                    'data' => $ordenCorte
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear orden de corte: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified cutting order.
     */
    public function show($id)
    {
        $ordenCorte = OrdenCorte::with([
            'items',
            'supplies',
            'cotizacion.estado',
            'compania',
            'cuerpo',
            'responsable:id,name,email',
            'historialEtapas',
        ])->find($id);

        if (!$ordenCorte) {
            return response()->json([
                'success' => false,
                'message' => 'Orden de corte no encontrada'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $ordenCorte
        ]);
    }

    /**
     * Actualiza la etapa operativa respetando la máquina de estados del taller
     * y registrando la transición en historial_etapas y logs_fabrica.
     */
    public function updateStage(Request $request, $id)
    {
        $ordenCorte = OrdenCorte::find($id);

        if (! $ordenCorte) {
            return response()->json([
                'success' => false,
                'message' => 'Orden de corte no encontrada',
            ], 404);
        }

        $rawStage = $request->input('stage')
            ?? $request->input('etapa')
            ?? $request->input('status');

        if (! is_string($rawStage) || trim($rawStage) === '') {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => [
                    'stage' => ['El campo stage es obligatorio.'],
                ],
            ], 422);
        }

        $observaciones = $request->input('observaciones') ?? $request->input('notes');

        try {
            $updated = $this->stageService->transitionStage(
                $ordenCorte,
                $rawStage,
                $request->user(),
                is_string($observaciones) ? $observaciones : null
            );

            return response()->json([
                'success' => true,
                'message' => 'Etapa operativa actualizada exitosamente',
                'data' => $updated,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Retorna el historial cronológico de etapas y registros de bitácora de una orden.
     */
    public function logs($id)
    {
        $ordenCorte = OrdenCorte::with(['historialEtapas', 'items.packages'])->find($id);

        if (! $ordenCorte) {
            return response()->json([
                'success' => false,
                'message' => 'Orden de corte no encontrada',
            ], 404);
        }

        $packageIds = $ordenCorte->items
            ->flatMap(fn ($item) => $item->packages->pluck('id'))
            ->unique()
            ->values();

        $factoryLogs = FactoryLog::with('user:id,name,email')
            ->where(function ($query) use ($ordenCorte, $packageIds) {
                $query->where(function ($sub) use ($ordenCorte) {
                    $sub->where('entity_type', 'CuttingOrder')
                        ->where('entity_id', $ordenCorte->id);
                });

                if ($packageIds->isNotEmpty()) {
                    $query->orWhere(function ($sub) use ($packageIds) {
                        $sub->where('entity_type', 'Package')
                            ->whereIn('entity_id', $packageIds);
                    });
                }
            })
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $stageHistory = $ordenCorte->historialEtapas->map(function ($entry) {
            return [
                'id' => $entry->id,
                'etapa_anterior' => $entry->etapa_anterior ?? $entry->estadoAnterior?->nombre,
                'etapa_nueva' => $entry->etapa_nueva ?? $entry->estadoNuevo?->nombre,
                'estado_anterior' => $entry->estadoAnterior,
                'estado_nuevo' => $entry->estadoNuevo,
                'observaciones' => $entry->observaciones,
                'user' => $entry->user,
                'user_id' => $entry->user_id,
                'created_at' => $entry->created_at,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'cutting_order_id' => $ordenCorte->id,
                'code' => $ordenCorte->code,
                'current_stage' => $this->stageService->currentStageKey($ordenCorte->status),
                'status' => $ordenCorte->status,
                'stage_history' => $stageHistory,
                'factory_logs' => $factoryLogs,
            ],
        ]);
    }

    /**
     * Update the specified cutting order in storage.
     */
    public function update(Request $request, $id)
    {
        $ordenCorte = OrdenCorte::find($id);

        if (!$ordenCorte) {
            return response()->json([
                'success' => false,
                'message' => 'Orden de corte no encontrada'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'sometimes|string|in:order-received,in-cutting,in-assembly,in-quality-control,completed,delivered,cutting,cutting-finished,transit,sewing,quality-control,finished,corte,confeccion,empaque,terminado',
            'progress' => 'sometimes|numeric|min:0|max:100',
            'notes' => 'sometimes|nullable|string',
            'estimated_days' => 'sometimes|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $dataToUpdate = $request->only(['notes', 'estimated_days', 'progress']);

        if ($request->has('status')) {
            // Mapeo de alias del frontend a estados canónicos del backend
            $statusAliases = [
                'cutting'         => 'in-cutting',
                'cutting-finished'=> 'in-assembly',
                'sewing'          => 'in-assembly',
                'quality-control' => 'in-quality-control',
                'transit'         => 'delivered',
                'finished'        => 'completed',
            ];
            $dataToUpdate['status'] = $statusAliases[$request->status] ?? $request->status;
        }

        $ordenCorte->update($dataToUpdate);

        $ordenCorte->load(['items', 'supplies']);

        return response()->json([
            'success' => true,
            'message' => 'Orden de corte actualizada exitosamente',
            'data' => $ordenCorte
        ]);
    }

    /**
     * Remove the specified cutting order from storage.
     */
    public function destroy($id)
    {
        $ordenCorte = OrdenCorte::find($id);

        if (!$ordenCorte) {
            return response()->json([
                'success' => false,
                'message' => 'Orden de corte no encontrada'
            ], 404);
        }

        $ordenCorte->delete();

        return response()->json([
            'success' => true,
            'message' => 'Orden de corte eliminada exitosamente'
        ]);
    }

    /**
     * Get the next available code for a new cutting order.
     */
    public function generateNextCode()
    {
        $lastOrder = OrdenCorte::orderBy('id', 'desc')->first();
        $nextCode = $lastOrder ? (intval($lastOrder->code) + 1) : 1000;

        return response()->json([
            'success' => true,
            'data' => [
                'next_code' => (string) $nextCode
            ]
        ]);
    }
}
