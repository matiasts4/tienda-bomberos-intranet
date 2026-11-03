<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Peticion;
use App\Models\PteStock;
use App\Models\PteMovimiento;
use App\Models\Estado;
use App\Models\Cotizacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class OrderRequestController extends Controller
{
    /**
     * List quotes available for reservation (Sent or Approved).
     */
    public function quotes(Request $request)
    {
        $validStatusIds = Estado::where('scope', 'cotizacion')
            ->whereIn('nombre', ['Cotización Enviada', 'Ventas'])
            ->pluck('id');

        $query = Cotizacion::whereIn('estado_id', $validStatusIds)
            ->with(['cliente', 'cuerpo', 'compania.cuerpo', 'contacto'])
            ->orderBy('id', 'desc');

        if ($request->filled('search')) {
            $s = '%' . $request->search . '%';
            $query->where(function ($q) use ($s) {
                $q->where('numero', 'like', $s)
                    ->orWhere('id', 'like', $s)
                    ->orWhereHas('cliente', function ($c) use ($s) {
                        $c->where('nombre_empresa', 'like', $s);
                    })
                    ->orWhereHas('cuerpo', function ($c) use ($s) {
                        $c->where('nombre', 'like', $s);
                    })
                    ->orWhereHas('compania', function ($c) use ($s) {
                        $c->where('nombre', 'like', $s);
                    })
                    ->orWhereHas('contacto', function ($c) use ($s) {
                        $c->where('nombre', 'like', $s);
                    });
            });
        }

        return response()->json($query->limit(50)->get());
    }

    /**
     * Create a new stock reservation request.
     */
    public function store(Request $request)
    {
        $isExternal = filter_var($request->input('is_external', false), FILTER_VALIDATE_BOOLEAN);

        $rules = [
            'items' => 'required|array|min:1',
            'items.*.sku' => 'required|distinct|exists:pte_skus,sku',
            'items.*.quantity' => 'required|integer|min:1',
            'observation' => 'nullable|string',
            'is_external' => 'nullable|boolean',
        ];

        if ($isExternal) {
            $rules['external_quote_number'] = 'required|string|max:100';
            // Igual que cotizaciones.nombre en Comercial: describe de qué se trata la cotización.
            $rules['external_quote_name'] = 'required|string|max:255';
            $rules['external_client_name'] = 'nullable|string|max:255';
        } else {
            $rules['quote_identifier'] = 'required';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación: ' . implode(', ', $validator->errors()->all()),
                'errors' => $validator->errors()
            ], 422);
        }

        return DB::transaction(function () use ($request, $isExternal) {
            $cotizacion = null;
            $cotizacionId = null;
            $externalQuoteNumber = null;
            $externalQuoteName = null;
            $externalClientName = null;

            if ($isExternal) {
                $externalQuoteNumber = trim((string)$request->input('external_quote_number'));
                $externalQuoteName = trim((string)$request->input('external_quote_name'));
                $externalClientName = $request->filled('external_client_name') ? trim((string)$request->input('external_client_name')) : null;
                $clientNameForMovement = $externalClientName;
                $movObs = "Reserva de producto para Cotización Externa #{$externalQuoteNumber}" . ($externalClientName ? " ({$externalClientName})" : "");
                // Una referencia externa nunca se vincula a una cotización interna:
                // coincidir en dígitos no implica que sea la misma cotización.
            } else {
                $quoteIdentifier = $request->quote_identifier;
                $cotizacion = Cotizacion::with(['cliente', 'cuerpo', 'compania.cuerpo', 'contacto'])
                    ->where('id', $quoteIdentifier)
                    ->orWhere('numero', $quoteIdentifier)
                    ->first();

                if (!$cotizacion) {
                    return response()->json([
                        'message' => "Cotización no encontrada: {$quoteIdentifier}"
                    ], 404);
                }

                $cotizacionId = $cotizacion->id;
                $clientNameForMovement = $cotizacion->nombre_cliente;
                $movObs = "Reserva de producto para Cotización #{$cotizacion->id}";
            }

            $createdRequests = [];
            // One reservation submission is one audit operation, not one per SKU.
            $lotId = (string) Str::uuid();
            $movementTime = now()->toDateTimeString();

            foreach (collect($request->items)->sortBy('sku') as $item) {
                $sku = $item['sku'];
                $quantity = $item['quantity'];
                $product = \App\Models\PteSku::with('skuGenerico')->find($sku);
                if ($product?->skuGenerico?->modo_kit) \App\Services\KitPlan::fail('El kit es un producto comercial. Reserva los SKU físicos de sus componentes.');

                $stock = PteStock::where('sku', $sku)->lockForUpdate()->first();

                if (!$stock || $stock->cantidad < $quantity) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'items' => 'Stock disponible insuficiente para ' . (\App\Models\PteSku::where('sku', $sku)->value('nombre') ?? 'el producto solicitado') . '.',
                    ]);
                }

                $prevStock = $stock->cantidad;

                // Descontar stock físico inmediatamente al reservar
                $stock->cantidad -= $quantity;
                // Incrementar stock reservado
                $stock->reserved_stock += $quantity;
                $stock->save();

                // Asegurar que el estado exista
                $pendingStatus = Estado::firstOrCreate(
                    ['scope' => 'peticion', 'nombre' => 'Pendiente'],
                    ['color' => 'blue', 'orden' => 1]
                );
                $pendingStatusId = $pendingStatus->id;

                // La solicitud se crea antes que el movimiento para dejarlo vinculado a ella.
                $orderRequest = Peticion::create([
                    'sku' => $sku,
                    'cantidad' => $quantity,
                    'cotizacion_id' => $cotizacionId,
                    'referencia_externa' => $externalQuoteNumber,
                    'nombre_cotizacion' => $externalQuoteName,
                    'cliente_externo' => $externalClientName,
                    'estado_id' => $pendingStatusId,
                    'user_id' => auth()->id(),
                    'fecha_creacion' => now()->toDateString(),
                    'observacion' => $request->observation
                ]);

                // Registrar movimiento en bitácora como RESERVA_PRODUCTO
                PteMovimiento::create([
                    'sku' => $sku,
                    'tipo_movimiento' => 'RESERVA_PRODUCTO',
                    'cantidad' => $quantity,
                    'saldo_anterior' => $prevStock,
                    'saldo_nuevo' => $stock->cantidad,
                    'fecha_hora' => $movementTime,
                    'usuario_id' => auth()->id(),
                    'cotizacion_id' => $cotizacionId,
                    'peticion_id' => $orderRequest->id,
                    'referencia_externa' => $externalQuoteNumber,
                    'id_lote' => $lotId,
                    'cliente_nombre' => $clientNameForMovement,
                    'observacion' => $movObs
                ]);

                $createdRequests[] = $orderRequest;
            }

            return response()->json([
                'message' => 'Solicitud de pedido creada y stock reservado con éxito.',
                'data' => $createdRequests
            ], 201);
        });
    }

    /**
     * Get just the count of pending order requests (for performance on frontend)
     */
    public function countPending()
    {
        $pendingStatusId = Estado::where('scope', 'peticion')->where('nombre', 'Pendiente')->value('id');

        if (!$pendingStatusId) {
            return response()->json(['count' => 0]);
        }

        // Cuenta pedidos pendientes (cotización o referencia + solicitante), no items.
        // Misma clave que agrupa Solicitudes Pendientes en el frontend.
        $peticiones = Peticion::where('estado_id', $pendingStatusId)
            ->get(['cotizacion_id', 'referencia_externa', 'user_id']);

        $count = $peticiones->map(function ($req) {
            $pedido = $req->cotizacion_id
                ? (string) $req->cotizacion_id
                : 'ext_'.($req->referencia_externa ?: 'sin_ref');

            return $pedido.'|'.($req->user_id ?? 'sin_usuario');
        })->unique()->count();

        return response()->json(['count' => $count]);
    }

    /**
     * List pending order requests.
     */
    public function index()
    {
        $pendingStatusId = Estado::where('scope', 'peticion')->where('nombre', 'Pendiente')->value('id');

        $requests = Peticion::where('estado_id', $pendingStatusId)
            ->with(['skuProducto.talla', 'skuProducto.color', 'cotizacion.cliente', 'cotizacion.cuerpo', 'cotizacion.compania.cuerpo', 'cotizacion.contacto', 'estado', 'user'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($requests);
    }

    /**
     * Confirm/Approve a dispatch request.
     */
    public function confirm(Request $request, $id)
    {
        try {
            return DB::transaction(function () use ($id) {
                $orderRequest = Peticion::lockForUpdate()->findOrFail($id);
                $this->processConfirmation($orderRequest);

                return response()->json([
                    'message' => 'Despacho confirmado y stock actualizado.',
                    'data' => $orderRequest->load('estado')
                ]);
            });
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Confirm/Approve multiple dispatch requests at once.
     */
    public function bulkConfirm(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:peticiones,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'IDs inválidos.',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            return DB::transaction(function () use ($request) {
                $confirmed = [];
                $lots = [];
                $movementTime = now()->toDateTimeString();
                foreach (collect($request->ids)->unique()->sort() as $id) {
                    $orderRequest = Peticion::lockForUpdate()->findOrFail($id);

                    // Skip if already processed to avoid errors in bulk
                    if ($orderRequest->estado->nombre !== 'Pendiente') {
                        continue;
                    }

                    $key = $this->auditGroupKey($orderRequest);
                    $lots[$key] ??= (string) Str::uuid();
                    $this->processConfirmation($orderRequest, $lots[$key], $movementTime);
                    $confirmed[] = $orderRequest->id;
                }

                return response()->json([
                    'message' => count($confirmed) . ' despachos confirmados con éxito.',
                    'confirmed_ids' => $confirmed
                ]);
            });
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Core logic to confirm a single request and update stock.
     */
    private function processConfirmation(Peticion $orderRequest, ?string $lotId = null, ?string $movementTime = null)
    {
        if ($orderRequest->estado->nombre !== 'Pendiente') {
            throw new \Exception("La solicitud #{$orderRequest->id} ya ha sido procesada.");
        }

        $stock = PteStock::where('sku', $orderRequest->sku)->lockForUpdate()->first();

        if (!$stock) {
            throw new \Exception("Stock no encontrado para el SKU {$orderRequest->sku} (Solicitud #{$orderRequest->id}).");
        }

        // Verificar que haya suficiente reservado para liberar
        if ($stock->reserved_stock < $orderRequest->cantidad) {
            throw new \Exception("Error de consistencia: Stock reservado insuficiente para el SKU {$orderRequest->sku} (Solicitud #{$orderRequest->id}).");
        }

        // NO DESCONTAMOS stock físico (ya se descontó al reservar)
        // Solo liberamos el stock reservado
        $prevStock = $stock->cantidad;
        $stock->reserved_stock -= $orderRequest->cantidad;
        $stock->save();

        // Update request status
        $despachadoStatus = Estado::firstOrCreate(
            ['scope' => 'peticion', 'nombre' => 'Despachado'],
            ['color' => 'green', 'orden' => 2]
        );
        $orderRequest->estado_id = $despachadoStatus->id;
        $orderRequest->save();

        $orderRequest->loadMissing(['cotizacion.cliente', 'cotizacion.cuerpo', 'cotizacion.compania.cuerpo', 'cotizacion.contacto']);
        $clientName = $orderRequest->cliente_externo ?: ($orderRequest->cotizacion?->nombre_cliente ?? null);

        $cotizDesc = $orderRequest->cotizacion_id
            ? "Cotización #{$orderRequest->cotizacion_id}"
            : "Cotización Externa #{$orderRequest->referencia_externa}" . ($orderRequest->cliente_externo ? " ({$orderRequest->cliente_externo})" : "");

        // Record movement (el saldo no cambia porque ya se descontó al reservar)
        PteMovimiento::create([
            'sku' => $orderRequest->sku,
            'tipo_movimiento' => 'SALIDA_VENTA',
            'cantidad' => $orderRequest->cantidad,
            'saldo_anterior' => $prevStock,
            'saldo_nuevo' => $prevStock, // El saldo no cambia (ya se descontó en la reserva)
            'fecha_hora' => $movementTime ?? now(),
            'usuario_id' => auth()->id(),
            'cotizacion_id' => $orderRequest->cotizacion_id,
            'peticion_id' => $orderRequest->id,
            'referencia_externa' => $orderRequest->referencia_externa,
            'id_lote' => $lotId ?? (string) Str::uuid(),
            'cliente_nombre' => $clientName,
            'observacion' => "Confirmación de despacho para Solicitud #{$orderRequest->id} ({$cotizDesc})"
        ]);
    }

    /**
     * Reject/Cancel a dispatch request.
     */
    public function reject(Request $request, $id)
    {
        try {
            return DB::transaction(function () use ($id) {
                $orderRequest = Peticion::lockForUpdate()->findOrFail($id);
                $this->processRejection($orderRequest);

                return response()->json([
                    'message' => 'Despacho rechazado y stock liberado.',
                    'data' => $orderRequest->load('estado')
                ]);
            });
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Reject multiple dispatch requests at once.
     */
    public function bulkReject(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:peticiones,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'IDs inválidos.',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            return DB::transaction(function () use ($request) {
                $rejected = [];
                $lots = [];
                $movementTime = now()->toDateTimeString();
                foreach (collect($request->ids)->unique()->sort() as $id) {
                    $orderRequest = Peticion::lockForUpdate()->findOrFail($id);

                    if ($orderRequest->estado->nombre !== 'Pendiente') {
                        continue;
                    }

                    $key = $this->auditGroupKey($orderRequest);
                    $lots[$key] ??= (string) Str::uuid();
                    $this->processRejection($orderRequest, $lots[$key], $movementTime);
                    $rejected[] = $orderRequest->id;
                }

                return response()->json([
                    'message' => count($rejected) . ' despachos rechazados con éxito.',
                    'rejected_ids' => $rejected
                ]);
            });
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Core logic to reject a single request and revert stock.
     */
    private function processRejection(Peticion $orderRequest, ?string $lotId = null, ?string $movementTime = null)
    {
        if ($orderRequest->estado->nombre !== 'Pendiente') {
            throw new \Exception("La solicitud #{$orderRequest->id} ya ha sido procesada.");
        }

        $stock = PteStock::where('sku', $orderRequest->sku)->lockForUpdate()->first();

        if (!$stock) {
            throw new \Exception("Stock no encontrado para el SKU {$orderRequest->sku} (Solicitud #{$orderRequest->id}).");
        }

        if ($stock->reserved_stock < $orderRequest->cantidad) {
            throw new \Exception("Error de consistencia: Stock reservado insuficiente para el SKU {$orderRequest->sku} (Solicitud #{$orderRequest->id}).");
        }

        // Devolver stock físico y liberar stock reservado
        $prevStock = $stock->cantidad; // stock real activo circulante
        $stock->reserved_stock -= $orderRequest->cantidad;
        $stock->cantidad += $orderRequest->cantidad;
        $stock->save();

        // Update request status
        $rechazadoStatus = Estado::firstOrCreate(
            ['scope' => 'peticion', 'nombre' => 'Rechazado'],
            ['color' => 'red', 'orden' => 3]
        );
        $orderRequest->estado_id = $rechazadoStatus->id;
        $orderRequest->save();

        $orderRequest->loadMissing(['cotizacion.cliente', 'cotizacion.cuerpo', 'cotizacion.compania.cuerpo', 'cotizacion.contacto']);
        $clientName = $orderRequest->cliente_externo ?: ($orderRequest->cotizacion?->nombre_cliente ?? null);

        $cotizDesc = $orderRequest->cotizacion_id
            ? "Cotización #{$orderRequest->cotizacion_id}"
            : "Cotización Externa #{$orderRequest->referencia_externa}" . ($orderRequest->cliente_externo ? " ({$orderRequest->cliente_externo})" : "");

        // Record movement
        PteMovimiento::create([
            'sku' => $orderRequest->sku,
            'tipo_movimiento' => 'ANULACION_RESERVA',
            'cantidad' => $orderRequest->cantidad,
            'saldo_anterior' => $prevStock,
            'saldo_nuevo' => $stock->cantidad, // ahora tiene más cantidad
            'fecha_hora' => $movementTime ?? now(),
            'usuario_id' => auth()->id(),
            'cotizacion_id' => $orderRequest->cotizacion_id,
            'peticion_id' => $orderRequest->id,
            'referencia_externa' => $orderRequest->referencia_externa,
            'id_lote' => $lotId ?? (string) Str::uuid(),
            'cliente_nombre' => $clientName,
            'observacion' => "Rechazo de despacho para Solicitud #{$orderRequest->id} ({$cotizDesc})"
        ]);
    }


    // A bulk action may contain different orders; do not merge their audit trails.
    private function auditGroupKey(Peticion $orderRequest): string
    {
        return json_encode([
            $orderRequest->cotizacion_id,
            $orderRequest->referencia_externa,
            $orderRequest->cliente_externo,
            $orderRequest->user_id,
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * Generate PDF for the initial request.
     */
    public function downloadReceiptPdf(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1|max:200',
            'ids.*' => 'required|integer|distinct|exists:peticiones,id',
        ]);
        $items = Peticion::whereIn('id', $validated['ids'])
            ->with(['skuProducto.talla', 'skuProducto.color', 'cotizacion.cliente', 'cotizacion.cuerpo', 'cotizacion.compania.cuerpo', 'cotizacion.contacto', 'user', 'estado'])
            ->orderBy('id')->get();
        $first = $items->first();
        // Solo agrupar líneas del mismo solicitante y referencia, no cotizaciones ajenas.
        abort_if($items->contains(fn ($item) => $item->user_id !== $first->user_id
            || $item->cotizacion_id !== $first->cotizacion_id
            || $item->referencia_externa !== $first->referencia_externa), 422, 'Las líneas no corresponden a la misma solicitud.');
        $quote = $first->cotizacion ?? (object) [
            'id' => null,
            'referencia_externa' => $first->referencia_externa,
            'nombre_cliente' => $first->cliente_externo ?? 'Cliente no especificado',
            'fecha' => $first->fecha_creacion,
            'fecha_creacion' => $first->fecha_creacion,
        ];
        return Pdf::loadView('pdf.quote_request', [
            'cotizacion' => $quote,
            'items' => $items,
            'title' => 'Comprobante de Solicitud de Reserva',
            'ejecutivo' => $first->user->name ?? 'N/A',
            'requestReceipt' => true,
        ])->stream("solicitud_{$first->id}.pdf");
    }

    public function downloadRequestPdf($id)
    {
        $orderRequest = Peticion::with(['skuProducto.talla', 'skuProducto.color', 'cotizacion.cliente', 'cotizacion.cuerpo', 'cotizacion.compania.cuerpo', 'cotizacion.contacto', 'estado', 'user'])->findOrFail($id);

        $pdf = Pdf::loadView('pdf.order_request', [
            'order' => $orderRequest,
            'title' => 'Solicitud de Reserva de Stock',
            'ejecutivo' => $orderRequest->user->name ?? 'N/A'
        ]);

        return $pdf->stream("request_{$orderRequest->id}.pdf");
    }

    /**
     * Generate a consolidated PDF for all pending items in a quote.
     */
    public function downloadQuoteRequestPdf(Request $request, $cotizacionId)
    {
        $pendingStatusId = Estado::where('scope', 'peticion')->where('nombre', 'Pendiente')->value('id');

        if (str_starts_with((string)$cotizacionId, 'ext_') || !is_numeric($cotizacionId) || !Cotizacion::where('id', $cotizacionId)->exists()) {
            $cleanRef = str_starts_with((string)$cotizacionId, 'ext_') ? substr((string)$cotizacionId, 4) : (string)$cotizacionId;
            $requests = Peticion::where('referencia_externa', $cleanRef)
                ->where('estado_id', $pendingStatusId)
                // Un pedido por solicitante: la misma referencia puede venir de varios ejecutivos.
                ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
                ->with(['skuProducto.talla', 'skuProducto.color', 'user'])
                ->get();

            if ($requests->isEmpty()) {
                return response()->json(['message' => 'No hay solicitudes pendientes para esta referencia externa.'], 404);
            }

            $first = $requests->first();
            $cotizacion = (object)[
                'id' => null,
                'numero' => "EXT-{$cleanRef}",
                'referencia_externa' => $cleanRef,
                'nombre_cliente' => $first->cliente_externo ?? 'Cliente no especificado (Cotización Externa)',
                'fecha' => $first->fecha_creacion ?? now()->toDateString(),
                'fecha_creacion' => $first->fecha_creacion ?? now()->toDateString(),
            ];
            $filename = 'solicitud_externa_' . Str::slug($cleanRef) . '.pdf';
        } else {
            $cotizacion = Cotizacion::with(['cliente', 'cuerpo', 'compania.cuerpo', 'contacto'])->findOrFail($cotizacionId);

            $requests = Peticion::where('cotizacion_id', $cotizacionId)
                ->where('estado_id', $pendingStatusId)
                // Un pedido por solicitante: la misma referencia puede venir de varios ejecutivos.
                ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
                ->with(['skuProducto.talla', 'skuProducto.color', 'user'])
                ->get();

            if ($requests->isEmpty()) {
                return response()->json(['message' => 'No hay solicitudes pendientes para esta cotización.'], 404);
            }
            $filename = "solicitud_cotizacion_{$cotizacion->id}.pdf";
        }

        $pdf = Pdf::loadView('pdf.quote_request', [
            'cotizacion' => $cotizacion,
            'items' => $requests,
            'title' => 'Solicitud Consolidada de Reserva',
            'ejecutivo' => $requests->first()->user->name ?? 'N/A'
        ]);

        return $pdf->stream($filename);
    }

    /**
     * Generate PDF for the confirmed dispatch.
     */
    public function downloadDispatchPdf($id)
    {
        $orderRequest = Peticion::with(['skuProducto.talla', 'skuProducto.color', 'cotizacion.cliente', 'cotizacion.cuerpo', 'cotizacion.compania.cuerpo', 'cotizacion.contacto', 'estado'])->findOrFail($id);

        if (!in_array($orderRequest->estado->nombre, ['Despachado', 'Aceptado'], true)) {
            return response()->json(['message' => 'El pedido aún no ha sido aceptado.'], 400);
        }

        // Generar QR de Encuesta
        $hash = base64_encode((string) $orderRequest->cotizacion_id);
        $url = env('FRONTEND_URL', 'http://localhost:3000') . '/encuesta?ref=' . $hash;
        $qrCodeSvg = base64_encode(QrCode::format('svg')->size(150)->generate($url));

        // "Autorizado por" es quien aprobó el despacho, no quien descarga el PDF.
        $aprobador = PteMovimiento::where('peticion_id', $orderRequest->id)
            ->where('tipo_movimiento', 'SALIDA_VENTA')
            ->with('usuario:id,name')
            ->latest('id')
            ->first()?->usuario?->name;

        $pdf = Pdf::loadView('pdf.order_request', [
            'order' => $orderRequest,
            'title' => 'Confirmación de Despacho',
            'ejecutivo' => $aprobador ?? 'No registrado',
            'qrCode' => $qrCodeSvg
        ]);

        return $pdf->stream("dispatch_{$orderRequest->id}.pdf");
    }

    /**
     * Generate PDF for the confirmed dispatch by Cotizacion ID.
     */
    public function downloadDispatchPdfByCotizacion($cotizacionId)
    {
        $cotizacion = Cotizacion::with('cliente')->findOrFail($cotizacionId);

        // Generar QR de Encuesta
        $hash = base64_encode($cotizacion->id);
        $url = env('FRONTEND_URL', 'http://localhost:3000') . '/encuesta?ref=' . $hash;
        $qrCodeSvg = base64_encode(QrCode::format('svg')->size(150)->generate($url));

        // Use the quote_request view but with the title Confirmación de Despacho
        $pendingStatusId = Estado::where('scope', 'peticion')->where('nombre', 'Pendiente')->value('id');
        
        // We get ALL requests for this quote (we assume they are the ones being dispatched)
        $requests = Peticion::where('cotizacion_id', $cotizacionId)
            ->with(['skuProducto.talla', 'skuProducto.color', 'user'])
            ->get();

        if ($requests->isEmpty()) {
            return response()->json(['message' => 'No hay solicitudes para esta cotización.'], 404);
        }

        $pdf = Pdf::loadView('pdf.quote_request', [
            'cotizacion' => $cotizacion,
            'items' => $requests,
            'title' => 'Confirmación de Despacho',
            // Solicitantes reales de las reservas, no quien descarga el PDF.
            'ejecutivo' => $requests->pluck('user.name')->filter()->unique()->implode(', ') ?: 'N/A',
            'qrCode' => $qrCodeSvg
        ]);

        return $pdf->stream("dispatch_cotizacion_{$cotizacion->id}.pdf");
    }
}
