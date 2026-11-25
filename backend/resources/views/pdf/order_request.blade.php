<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
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
            page-break-inside: avoid;
        }

        .header-table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
        }

        .header-table td {
            padding: 0;
            vertical-align: middle;
        }

        .header-table .brand {
            width: 220px;
            padding-right: 20px;
        }

        .logo {
            display: block;
            width: 220px;
            height: auto;
        }

        .header h1 {
            margin: 0 0 5px 0;
            font-size: 18px;
            line-height: 1.25;
            text-transform: uppercase;
        }

        .header p {
            margin: 0;
            color: #666;
            font-size: 10px;
        }

        .section {
            margin-bottom: 25px;
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

        .items-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #ddd;
        }

        .items-table th {
            background-color: #e5e7eb;
            padding: 8px;
            text-align: left;
            font-size: 11px;
            border-bottom: 1px solid #bbb;
        }

        .items-table td {
            padding: 8px;
            border-bottom: 1px solid #eee;
            font-size: 12px;
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

        .badge {
            padding: 3px 6px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 10px;
            color: white;
        }

        .bg-yellow {
            background-color: #f59e0b;
        }

        .bg-green {
            background-color: #10b981;
        }
    </style>
</head>

<body>
    <div class="header">
        <table class="header-table">
            <tr>
                <td class="brand">
                    <img src="{{ public_path('tb.logo BLACK.png') }}" class="logo" alt="Tienda Bomberos">
                </td>
                <td>
                    <h1>{{ $title }}</h1>
                    <p>Documento Generado: {{ date('d/m/Y H:i') }}</p>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <table class="info-table">
            <tr>
                <td class="label">ID de Referencia:</td>
                <td>#{{ $order->id }}</td>
            </tr>
            <tr>
                <td class="label">Cotización:</td>
                <td>
                    @if($order->cotizacion)
                        #{{ $order->cotizacion->id }} ({{ $order->cotizacion->numero ?? 'N/A' }})
                    @else
                        Externa: #{{ $order->referencia_externa ?? 'N/A' }}
                    @endif
                </td>
            </tr>
            <tr>
                <td class="label">Vendido A:</td>
                <td>
                    @if($order->cotizacion)
                        <strong>{{ $order->cotizacion->nombre_cliente }}</strong><br>
                        <small>
                            Contacto: {{ $order->cotizacion->contacto->nombre ?? 'N/A' }} |
                            Tel: {{ $order->cotizacion->cliente->telefono ?? 'N/A' }} |
                            Email: {{ $order->cotizacion->cliente->correo ?? 'N/A' }}
                        </small>
                    @else
                        <strong>{{ $order->cliente_externo ?? 'Cliente no especificado (Cotización Externa)' }}</strong>
                    @endif
                </td>
            </tr>
            <tr>
                <td class="label">Estado:</td>
                <td>
                    <span class="badge {{ $order->estado->nombre == 'Pendiente' ? 'bg-yellow' : 'bg-green' }}">
                        {{ $order->estado->nombre }}
                    </span>
                </td>
            </tr>
            @if($order->cotizacion && ($order->cotizacion->fecha_creacion ?? $order->cotizacion->fecha ?? null))
                <tr>
                    <td class="label">Fecha Cotización:</td>
                    <td>{{ \Carbon\Carbon::parse($order->cotizacion->fecha_creacion ?? $order->cotizacion->fecha)->format('d/m/Y') }}</td>
                </tr>
            @endif
            <tr>
                <td class="label">Fecha Solicitud:</td>
                <td>{{ $order->fecha_creacion ? \Carbon\Carbon::parse($order->fecha_creacion)->format('d/m/Y') : date('d/m/Y') }}</td>
            </tr>
            <tr>
                <td class="label">Observación:</td>
                <td>{{ $order->observacion ?? 'Sin observaciones.' }}</td>
            </tr>
            <tr>
                <td class="label">Solicitado Por:</td>
                <td>{{ $order->user->name ?? 'N/A' }}</td>
            </tr>
            @if($title === 'Confirmación de Despacho')
                <tr>
                    <td class="label">Autorizado Por:</td>
                    <td>{{ $ejecutivo }}</td>
                </tr>
            @endif
        </table>
    </div>

    <div class="section">
        <h3>Productos Solicitados</h3>
        <table class="items-table">
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Nombre del Producto</th>
                    <th>Talla</th>
                    <th>Color</th>
                    <th style="text-align: right;">Cantidad</th>
                    <th style="text-align: right;">Precio Unit.</th>
                    <th style="text-align: right;">Total</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>{{ $order->sku }}</code></td>
                    <td>{{ $order->skuProducto->nombre }}</td>
                    <td>{{ $order->skuProducto->talla->nombre ?? '-' }}</td>
                    <td>{{ $order->skuProducto->color->nombre ?? '-' }}</td>
                    <td style="text-align: right;">{{ $order->cantidad }}</td>
                    <td style="text-align: right;">
                        ${{ number_format($order->skuProducto->precio_venta ?? 0, 0, ',', '.') }}</td>
                    <td style="text-align: right; font-weight: bold;">
                        @php $subtotal = ($order->skuProducto->precio_venta ?? 0) * $order->cantidad; @endphp
                        ${{ number_format($subtotal, 0, ',', '.') }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    @if($order->estado->nombre == 'Aceptado' || $title === 'Confirmación de Despacho')
        <div class="section" style="margin-top: 50px;">
            <table width="100%">
                <tr>
                    <td width="50%" style="text-align: center;">
                        <div style="border-top: 1px solid #000; width: 200px; margin: 0 auto; padding-top: 5px;">
                            Despacho Autorizado Por
                        </div>
                    </td>
                    <td width="50%" style="text-align: center;">
                        <div style="border-top: 1px solid #000; width: 200px; margin: 0 auto; padding-top: 5px;">
                            Recibido Por (Bodega)
                        </div>
                    </td>
                </tr>
            </table>
        </div>
        
        @if(isset($qrCode))
        <div class="section" style="margin-top: 40px; text-align: center; border: 1px solid #ddd; padding: 15px; border-radius: 8px; background-color: #f9fafb;">
            <h3 style="margin-top: 0; color: #10b981; font-size: 14px;">¡Queremos escucharte!</h3>
            <p style="font-size: 11px; color: #555; margin-bottom: 10px;">Escanea este código con tu celular para evaluar el producto y tu experiencia de compra.</p>
            <img src="data:image/svg+xml;base64,{!! $qrCode !!}" style="width: 100px; height: 100px; border: 1px solid #ccc; padding: 5px; background: white;">
        </div>
        @endif
    @endif

    <div class="footer">
        Documento de Control Interno - Tienda Bomberos
    </div>
</body>

</html>
