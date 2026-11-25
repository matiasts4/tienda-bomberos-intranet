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
                <td class="label">Cotización:</td>
                <td>
                    @if(isset($cotizacion->id) && $cotizacion->id)
                        #{{ $cotizacion->id }} ({{ $cotizacion->numero ?? 'N/A' }})
                    @else
                        Externa: #{{ $cotizacion->referencia_externa ?? ($cotizacion->numero ?? 'N/A') }}
                    @endif
                </td>
            </tr>
            <tr>
                <td class="label">Vendido A:</td>
                <td>
                    <strong>{{ $cotizacion->nombre_cliente ?? 'Cliente no especificado' }}</strong>
                    @if(isset($cotizacion->cliente) && $cotizacion->cliente)
                        <br>
                        <small>
                            Contacto: {{ $cotizacion->contacto->nombre ?? 'N/A' }} |
                            Tel: {{ $cotizacion->cliente->telefono ?? 'N/A' }} |
                            Email: {{ $cotizacion->cliente->correo ?? 'N/A' }}
                        </small>
                    @endif
                </td>
            </tr>
            @if(isset($cotizacion->id) && $cotizacion->id)
                <tr>
                    <td class="label">Fecha Cotización:</td>
                    <td>{{ ($cotizacion->fecha_creacion ?? $cotizacion->fecha ?? null) ? \Carbon\Carbon::parse($cotizacion->fecha_creacion ?? $cotizacion->fecha)->format('d/m/Y') : 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="label">Fecha Solicitud Reserva:</td>
                    <td>{{ ($items->first()?->fecha_creacion) ? \Carbon\Carbon::parse($items->first()->fecha_creacion)->format('d/m/Y') : date('d/m/Y') }}</td>
                </tr>
            @else
                <tr>
                    <td class="label">Fecha Solicitud Reserva:</td>
                    <td>{{ ($items->first()?->fecha_creacion ?? $cotizacion->fecha ?? null) ? \Carbon\Carbon::parse($items->first()?->fecha_creacion ?? $cotizacion->fecha)->format('d/m/Y') : date('d/m/Y') }}</td>
                </tr>
            @endif
            <tr>
                <td class="label">Ejecutivo Solicitante:</td>
                <td>{{ $ejecutivo }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        @if($requestReceipt ?? false)
            <p>Comprobante de solicitud. No autoriza el retiro de productos. La aprobación corresponde al superadmin.</p>
            <p>Líneas solicitadas: {{ $items->pluck('id')->join(', ') }}.</p>
        @endif
        <h3>Productos en la Solicitud</h3>
        <table class="items-table">
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Nombre del Producto</th>
                    <th>Talla</th>
                    <th>Color</th>
                    <th style="text-align: right;">Cantidad</th>
                    <th style="text-align: right;">Precio Unit.</th>
                    <th style="text-align: right;">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @php $totalGeneral = 0; @endphp
                @foreach($items as $item)
                    @php 
                                        $subtotal = ($item->skuProducto->precio_venta ?? 0) * $item->cantidad;
                        $totalGeneral += $subtotal;
                    @endphp
                    <tr>
                        <td><code>{{ $item->sku }}</code></td>
                        <td>{{ $item->skuProducto->nombre }}</td>
                        <td>{{ $item->skuProducto->talla->nombre ?? '-' }}</td>
                        <td>{{ $item->skuProducto->color->nombre ?? '-' }}</td>
                        <td style="text-align: right;">{{ $item->cantidad }}</td>
                        <td style="text-align: right;">${{ number_format($item->skuProducto->precio_venta ?? 0, 0, ',', '.') }}</td>
                        <td style="text-align: right; font-weight: bold;">${{ number_format($subtotal, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="background-color: #f3f4f6;">
                    <td colspan="6" style="text-align: right; font-weight: bold; padding: 10px;">TOTAL GENERAL</td>
                    <td style="text-align: right; font-weight: bold; font-size: 14px; padding: 10px; color: #1d4ed8;">
                        ${{ number_format($totalGeneral, 0, ',', '.') }}
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="section" style="margin-top: 50px;">
        <table width="100%">
            <tr>
                <td width="33%" style="text-align: center;">
                    <div style="border-top: 1px solid #000; width: 150px; margin: 0 auto; padding-top: 5px;">
                        Solicitado Por
                    </div>
                </td>
                <td width="33%" style="text-align: center;">
                    <div style="border-top: 1px solid #000; width: 150px; margin: 0 auto; padding-top: 5px;">
                        V°B° Bodega
                    </div>
                </td>
                <td width="33%" style="text-align: center;">
                    <div style="border-top: 1px solid #000; width: 150px; margin: 0 auto; padding-top: 5px;">
                        Firma Cliente
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

    <div class="footer">
        Documento de Control Interno - Tienda Bomberos
    </div>
</body>

</html>
