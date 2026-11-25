<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Bitácora mensual {{ $mes }}</title>
    <style>
        @page { margin: 30px 30px 40px; }
        body { font-family: sans-serif; font-size: 10px; color: #263247; }
        .header { width: 100%; border-bottom: 2px solid #444; margin-bottom: 16px; }
        .header td { padding: 0 0 12px; vertical-align: middle; }
        .brand { width: 220px; }
        h1 { font-size: 19px; margin: 0 0 6px; }
        p { margin: 4px 0; }
        .details { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .details th { background: #edf0f4; text-align: left; }
        .details th, .details td { padding: 6px; border-bottom: 1px solid #ddd; vertical-align: top; word-wrap: break-word; }
        .details thead { display: table-header-group; }
        .details tr { page-break-inside: avoid; }
        .number { text-align: right; }
        .muted { color: #536174; font-size: 9px; }
        .footer { position: fixed; bottom: -24px; left: 0; right: 0; text-align: center; font-size: 9px; color: #536174; }
    </style>
</head>
<body>
    <table class="header"><tr>
        <td class="brand"><img src="{{ public_path('tb.logo BLACK.png') }}" width="190" alt="Tienda Bomberos"></td>
        <td>
            <h1>Bitácora {{ ($semana ?? null) ? 'semanal · Semana '.$semana : 'mensual' }} · {{ \Carbon\Carbon::createFromFormat('!Y-m', $mes)->format('m/Y') }}</h1>
            <p>Tipo: {{ str_replace('_', ' ', $tipo) }} · {{ $movimientos->count() }} movimientos</p>
            <p class="muted">Período: {{ $periodo ?? $mes }} · Generado: {{ now()->format('d/m/Y H:i') }}. Incluye todo el período para el tipo indicado, no solo la página visible.</p>
        </td>
    </tr></table>
    <p style="margin-bottom: 12px;">Cantidad indica las unidades de la operación. Cambio disponible es la diferencia real de stock; un despacho ya reservado puede mostrar 0.</p>
    <table class="details">
        <thead><tr>
            <th style="width: 10%;">Fecha / hora</th>
            <th style="width: 15%;">Operación</th>
            <th style="width: 13%;">Responsable</th>
            <th style="width: 26%;">Producto / talla</th>
            <th style="width: 8%;">Cantidad</th>
            <th style="width: 10%;">Cambio disponible</th>
            <th style="width: 9%;">Anterior</th>
            <th style="width: 9%;">Nuevo</th>
        </tr></thead>
        <tbody>
            @foreach($movimientos as $mov)
                @php $delta = $mov->saldo_nuevo - $mov->saldo_anterior; @endphp
                <tr>
                    <td>{{ \Carbon\Carbon::parse($mov->fecha_hora)->format('d/m/Y H:i') }}</td>
                    <td>
                        {{ str_replace('_', ' ', $mov->tipo_movimiento) }}<br>
                        <span class="muted">Registro #{{ $mov->id }}</span>
                        @if($mov->cotizacion_id)
                            <br><strong style="color: #92400e;">Cotización: #{{ $mov->cotizacion_id }}</strong>
                        @elseif($mov->referencia_externa)
                            <br><strong style="color: #92400e;">Cotización: {{ $mov->referencia_externa }}</strong>
                        @endif
                        @php $cliente = $mov->cliente_nombre ?: ($mov->cotizacion?->nombre_cliente ?: $mov->cotizacion?->cliente?->nombre_empresa); @endphp
                        @if($cliente)
                            <br><span style="color: #1e40af; font-weight: bold;">Cliente: {{ $cliente }}</span>
                        @endif
                    </td>
                    @php
                        $verbo = ['solicito' => 'Solicitó', 'aprobo' => 'Aprobó', 'rechazo' => 'Rechazó'][\App\Http\Controllers\BitacoraController::ROL_ACTOR[$mov->tipo_movimiento] ?? ''] ?? 'Registró';
                        // Con solicitud vinculada, quien pidió la reserva; en historial sin vínculo, el dueño de la cotización.
                        $solicitante = $mov->peticion_id ? $mov->peticion?->user?->name : $mov->cotizacion?->ejecutivo?->name;
                    @endphp
                    <td>
                        {{ $verbo }}: {{ $mov->usuario?->name ?? 'Sistema' }}
                        @if($mov->tipo_movimiento !== 'RESERVA_PRODUCTO' && $solicitante && $solicitante !== ($mov->usuario?->name ?? ''))
                            <br><span class="muted">Solicitó: {{ $solicitante }}</span>
                        @endif
                    </td>
                    <td>{{ $mov->skuProducto?->nombre ?? 'Producto no disponible' }}<br><span class="muted">Talla: {{ $mov->skuProducto?->talla?->nombre ?? '-' }} · SKU: {{ $mov->sku }}</span></td>
                    <td class="number">{{ $mov->cantidad }}</td>
                    <td class="number">{{ $delta > 0 ? '+' : '' }}{{ $delta }}</td>
                    <td class="number">{{ $mov->saldo_anterior }}</td>
                    <td class="number">{{ $mov->saldo_nuevo }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="footer">Documento de control interno - Tienda Bomberos</div>
</body>
</html>
