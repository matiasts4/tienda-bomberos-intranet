<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Bitácora de Movimientos</title>
    <style>
        body {
            font-family: sans-serif;
            font-size: 12px;
            color: #333;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #444;
            padding-bottom: 10px;
            position: relative;
            height: 60px;
        }

        .logo {
            position: absolute;
            top: 0;
            left: 0;
            width: 220px;
            height: auto;
        }

        .header h1 {
            margin: 10px 0 5px 0;
            font-size: 18px;
            text-transform: uppercase;
        }

        .header p {
            margin: 0;
            color: #666;
            font-size: 10px;
        }

        .lote-container {
            margin-bottom: 25px;
            page-break-inside: avoid;
        }

        .lote-header {
            background-color: #f3f4f6;
            padding: 8px;
            border: 1px solid #ddd;
            border-bottom: none;
        }

        .lote-info {
            width: 100%;
            border-collapse: collapse;
        }

        .lote-info td {
            border: none;
            padding: 2px;
        }

        .badge {
            padding: 3px 6px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 10px;
            color: white;
        }

        .bg-green {
            background-color: #10b981;
        }

        .bg-blue {
            background-color: #3b82f6;
        }

        .bg-gray {
            background-color: #6b7280;
        }

        .table-detalle {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #ddd;
        }

        .table-detalle th {
            background-color: #e5e7eb;
            padding: 5px;
            text-align: left;
            font-size: 10px;
            border-bottom: 1px solid #bbb;
        }

        .table-detalle td {
            padding: 5px;
            border-bottom: 1px solid #eee;
            font-size: 11px;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .font-mono {
            font-family: monospace;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .info-table td {
            padding: 5px;
            border: 1px solid #eee;
        }

        .info-table .label {
            background-color: #f9fafb;
            font-weight: bold;
            width: 30%;
        }

        .section {
            margin-bottom: 25px;
        }

        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 9px;
            color: #999;
            border-top: 1px solid #eee;
            padding-top: 5px;
        }
    </style>
</head>

<body>
    <div class="header">
        <img src="{{ public_path('tb.logo BLACK.png') }}" class="logo" alt="Logo">

        <h1>Reporte de Movimientos</h1>
        <p>Generado el: {{ date('d/m/Y H:i') }}</p>
    </div>

    @foreach($reporte as $lote)
        <div class="lote-container">
            @if(isset($lote['cotizacion_info']) && $lote['cotizacion_info'])
                <div class="section" style="margin-bottom: 15px;">
                    <table class="info-table">
                        <tr>
                            <td class="label">Cotización:</td>
                            <td>
                                @if($lote['cotizacion_id'])
                                    <strong style="color: #92400e; font-size: 13px;">#{{ $lote['cotizacion_id'] }}</strong>
                                    @if(!empty($lote['cotizacion_info']['numero'])) ({{ $lote['cotizacion_info']['numero'] }}) @endif
                                @elseif(!empty($lote['cotizacion_info']['numero']))
                                    <strong style="color: #92400e; font-size: 13px;">{{ $lote['cotizacion_info']['numero'] }}</strong>
                                @else
                                    <strong style="color: #92400e; font-size: 13px;">N/A</strong>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td class="label">
                                {{ $lote['tipo_movimiento'] === 'RESERVA_PRODUCTO' ? 'Reservado A:' : 'Vendido A:' }}
                            </td>
                            <td>
                                <strong style="color: #1e3a8a; font-size: 13px;">{{ $lote['cliente_nombre'] ?: ($lote['cotizacion_info']['cliente']['nombre_empresa'] ?? '---') }}</strong><br>
                                <small>
                                    Contacto: {{ $lote['cotizacion_info']['cliente']['nombre_contacto'] ?? 'N/A' }} |
                                    Tel: {{ $lote['cotizacion_info']['cliente']['telefono'] ?? 'N/A' }} |
                                    Email: {{ $lote['cotizacion_info']['cliente']['correo'] ?? 'N/A' }}
                                </small>
                            </td>
                        </tr>
                        <tr>
                            <td class="label">Fecha Cotización:</td>
                            <td>{{ $lote['cotizacion_info']['fecha'] ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <td class="label">Solicitado Por:</td>
                            <td>{{ $lote['solicitante'] ?? $lote['cotizacion_info']['ejecutivo'] ?? 'N/A' }}</td>
                        </tr>
                        @if($lote['tipo_movimiento'] === 'SALIDA_VENTA')
                        <tr>
                            <td class="label">Autorizado Por:</td>
                            <td>{{ $lote['usuario'] }}</td>
                        </tr>
                        @elseif($lote['tipo_movimiento'] === 'ANULACION_RESERVA')
                        <tr>
                            <td class="label">Rechazado Por:</td>
                            <td>{{ $lote['usuario'] }}</td>
                        </tr>
                        @elseif($lote['tipo_movimiento'] === 'RESERVA_PRODUCTO' && !empty($lote['estado_solicitud']))
                        <tr>
                            <td class="label">Estado de la Solicitud:</td>
                            <td>{{ $lote['estado_solicitud'] }}</td>
                        </tr>
                        @endif
                    </table>
                </div>
            @else
                <div class="lote-header">
                    <table class="lote-info">
                        <tr>
                            <td width="20%"><strong>Fecha:</strong> {{ $lote['fecha_hora_grupo'] }}</td>

                            <td width="30%">
                                <strong>{{ isset($tipo_reporte) && $tipo_reporte === 'historial_sku' ? 'Producto:' : 'Usuario:' }}</strong>
                                {{ $lote['usuario'] }}
                            </td>

                            <td width="20%">
                                @php
                                    $color = 'bg-gray';
                                    if (str_contains($lote['tipo_movimiento'], 'INGRESO')) {
                                        $color = 'bg-green';
                                    }
                                    if (str_contains($lote['tipo_movimiento'], 'SALIDA')) {
                                        $color = 'bg-blue';
                                    }
                                    if (str_contains($lote['tipo_movimiento'], 'ANULACION')) {
                                        $color = 'bg-gray';
                                    }
                                    if (str_contains($lote['tipo_movimiento'], 'RESERVA')) {
                                        $color = 'bg-blue';
                                    }
                                @endphp
                                <span class="badge {{ $color }}">{{ $lote['tipo_movimiento'] }}</span>
                            </td>
                            <td width="30%" class="text-right">
                                <strong>Total Items:</strong> {{ $lote['total_registros'] }} |
                                <strong>Unidades:</strong> {{ $lote['total_unidades'] }}
                            </td>
                        </tr>
                        @if((isset($lote['cotizacion_id']) && $lote['cotizacion_id']) || (!empty($lote['cliente_nombre'])))
                            <tr>
                                <td colspan="4" style="border-top: 1px dashed #ddd; padding-top: 5px; margin-top: 5px;">
                                    @if(isset($lote['cotizacion_id']) && $lote['cotizacion_id'])
                                        <strong style="color: #92400e;">Cotización:</strong> <span style="font-weight: bold;">#{{ $lote['cotizacion_id'] }}</span> &nbsp;|&nbsp;
                                    @endif
                                    @if(!empty($lote['cliente_nombre']))
                                        <strong style="color: #1e3a8a;">Cliente:</strong> <span style="font-weight: bold;">{{ $lote['cliente_nombre'] }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endif
                    </table>
                </div>
            @endif

            <table class="table-detalle">
                <thead>
                    <tr>
                        <th width="15%">
                            {{ isset($tipo_reporte) && $tipo_reporte === 'historial_sku' ? 'Fecha/Hora' : 'Hora' }}
                        </th>

                        @if(isset($tipo_reporte) && $tipo_reporte === 'historial_sku')
                            <th width="40%">Usuario Responsable</th>
                        @else
                            <th width="15%">SKU</th>
                            <th width="20%">Producto</th>
                            <th width="10%">Talla</th>
                            <th width="10%">Color</th>
                        @endif

                        <th width="10%" class="text-right">Movimiento</th>
                        @if(isset($lote['cotizacion_info']) && $lote['cotizacion_info'])
                            <th width="10%" class="text-right">Precio Unit.</th>
                            <th width="10%" class="text-right">Subtotal</th>
                        @else
                            <th width="10%" class="text-right">Saldo Ant.</th>
                            <th width="10%" class="text-right">Saldo Nuevo</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach($lote['items'] as $item)
                        @php
                            $esResta = $item['saldo_nuevo'] < $item['saldo_anterior'];
                        @endphp
                        <tr>
                            <td class="font-mono">{{ $item['hora_exacta'] }}</td>

                            @if(isset($tipo_reporte) && $tipo_reporte === 'historial_sku')
                                <td>{{ $item['usuario_nombre'] ?? '---' }}</td>
                            @else
                                <td class="font-mono">{{ $item['sku'] }}</td>
                                <td>{{ $item['producto'] }}</td>
                                <td>{{ $item['talla'] ?? '-' }}</td>
                                <td>{{ $item['color'] ?? '-' }}</td>
                            @endif

                            <td class="text-right"
                                style="font-weight: bold; {{ $esResta ? 'color: #dc2626;' : 'color: #16a34a;' }}">
                                {{ $esResta ? '-' : '+' }}{{ $item['cantidad'] }}
                            </td>

                            @if(isset($lote['cotizacion_info']) && $lote['cotizacion_info'])
                                <td class="text-right" style="color: #666;">${{ number_format($item['precio_unitario'], 0, ',', '.') }}</td>
                                <td class="text-right"><strong>${{ number_format($item['cantidad'] * $item['precio_unitario'], 0, ',', '.') }}</strong></td>
                            @else
                                <td class="text-right" style="color: #666;">{{ $item['saldo_anterior'] }}</td>
                                <td class="text-right"><strong>{{ $item['saldo_nuevo'] }}</strong></td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if($lote['tipo_movimiento'] === 'RESERVA_PRODUCTO')
                <div class="firmas-container" style="margin-top: 30px;">
                    <table width="100%">
                        <tr>
                            <td width="33%" style="text-align: center;">
                                <div
                                    style="border-top: 1px solid #000; width: 140px; margin: 0 auto; padding-top: 5px; font-size: 9px;">
                                    Solicitado Por
                                </div>
                            </td>
                            <td width="33%" style="text-align: center;">
                                <div
                                    style="border-top: 1px solid #000; width: 140px; margin: 0 auto; padding-top: 5px; font-size: 9px;">
                                    V°B° Bodega
                                </div>
                            </td>
                            <td width="33%" style="text-align: center;">
                                <div
                                    style="border-top: 1px solid #000; width: 140px; margin: 0 auto; padding-top: 5px; font-size: 9px;">
                                    Firma Cliente
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>
            @endif
        </div>
    @endforeach

    <div class="footer">
        Documento de control interno - Tienda Bomberos
    </div>
</body>

</html>