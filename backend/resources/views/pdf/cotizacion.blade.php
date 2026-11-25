<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Cotización</title>
    <style>
        body {
            font-family: sans-serif;
            font-size: 12px;
            color: #333;
            line-height: 1.4;
        }

        .header {
            margin-bottom: 30px;
            border-bottom: 2px solid #444;
            padding-bottom: 10px;
            position: relative;
            height: 110px;
        }

        .company-info {
            position: absolute;
            top: 0;
            left: 0;
            font-size: 11px;
            line-height: 1.3;
            color: #000;
            width: 250px;
        }

        .company-info strong.company-name {
            font-size: 14px;
            font-weight: bold;
            display: block;
            margin-bottom: 2px;
        }

        .logo {
            position: absolute;
            top: -10px;
            /* Subimos un poco para dar espacio */
            right: 0;
            width: 260px;
            /* Agrandado de 200px a 260px */
            height: auto;
        }

        .header-title-container {
            position: absolute;
            bottom: 5px;
            /* Alineado mas abajo */
            left: 0;
            right: 0;
            text-align: center;
        }

        .header h1 {
            margin: 0;
            font-size: 18px;
            text-transform: uppercase;
            font-weight: bold;
        }

        .date-line {
            position: absolute;
            bottom: 5px;
            /* Alineado con el titulo */
            right: 0;
            margin: 0;
            color: #444;
            font-size: 11px;
            text-align: right;
        }

        .header p {
            margin: 2px 0 0 0;
            color: #666;
            font-size: 11px;
        }

        /* ... rest of styles ... */

        .client-section {
            background-color: #f9fafb;
            padding: 15px;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            margin-bottom: 25px;
        }

        .client-table {
            width: 100%;
            border-collapse: collapse;
        }

        .client-table td {
            vertical-align: top;
            padding: 3px;
        }

        .label {
            font-weight: bold;
            color: #4b5563;
            width: 80px;
        }

        .table-detalle {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .table-detalle th {
            background-color: #f3f4f6;
            padding: 8px;
            text-align: left;
            font-size: 11px;
            font-weight: bold;
            color: #374151;
            border-bottom: 2px solid #e5e7eb;
        }

        .table-detalle td {
            padding: 8px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 11px;
        }

        .totals-section {
            margin-top: 20px;
            page-break-inside: avoid;
        }

        .totals-table {
            width: 40%;
            float: right;
            border-collapse: collapse;
        }

        .totals-table td {
            padding: 6px;
            border-bottom: 1px solid #eee;
        }

        .totals-table tr:last-child td {
            border-bottom: none;
            font-size: 14px;
            padding-top: 10px;
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

        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 10px;
            color: #9ca3af;
            border-top: 1px solid #eee;
            padding-top: 10px;
        }

        .signature-container {
            margin-top: 80px;
            text-align: center;
            page-break-inside: avoid;
        }

        .signature-line {
            display: inline-block;
            /* border-top: 1px solid #333; */
            width: 280px;
            margin-bottom: 5px;
        }

        /* Intentando simular la firma azul con una fuente script si estuviera disponible, 
           pero usaremos color azul y cursiva como fallback elegante */
        .signature-name {
            font-size: 14px;
            color: #333;
            font-weight: bold;
            display: block;
            margin-top: 5px;
            margin-bottom: 2px;
        }

        .signature-title {
            font-size: 12px;
            color: #333;
            font-weight: bold;
        }

        .signature-company {
            font-size: 11px;
            color: #666;
        }

        /* Firma al centro e Información de Pago abajo a la izquierda,
           en la misma banda para que quepan en la primera página */
        .signature-payment-table {
            width: 100%;
            margin-top: 50px;
            border-collapse: collapse;
            page-break-inside: avoid;
        }

        .signature-payment-table td {
            vertical-align: bottom;
        }

        .payment-info {
            font-size: 11px;
            color: #333;
            line-height: 1.5;
        }

        .payment-info strong.payment-title {
            display: block;
            font-size: 12px;
            margin-bottom: 2px;
        }
    </style>
</head>

<body>
    <div class="header">
        <!-- Datos Empresa Izquierda -->
        <div class="company-info">
            <strong class="company-name">Tienda Bomberos SpA</strong>
            <strong>R.U.T. 77.753.969-8</strong><br>
            Venta de vestuario via com.establecido y<br>
            online<br>
            Longitudinal Seis 599 – Independencia<br>
            Celular +56 9 99788360
        </div>

        <!-- Logo Derecha -->
        <img src="{{ public_path('tb.logo BLACK.png') }}" class="logo" alt="Logo">

        <!-- Título Centrado -->
        <div class="header-title-container">
            <h1>Cotización N° {{ $cotizacion->numero ?? str_pad($cotizacion->id, 4, '0', STR_PAD_LEFT) }}</h1>
        </div>

        <!-- Fecha Abajo Derecha -->
        <p class="date-line">
            Santiago,
            {{ \Illuminate\Support\Str::ucfirst(\Carbon\Carbon::parse($fechaPdf ?? now())->locale('es')->isoFormat('dddd DD MMMM YYYY')) }}
        </p>
    </div>

    <div class="client-section">
        <table class="client-table">
            <tr>
                <td width="50%">
                    <table width="100%">
                        <tr>
                            <td class="label">Señor(es):</td>
                            <td><strong>{{ $nombreClientePdf }}</strong>
                            </td>
                        </tr>
                        <tr>
                            <td class="label">RUT:</td>
                            <td>{{ $rutClientePdf }}</td>
                        </tr>
                        <tr>
                            <td class="label">Dirección:</td>
                            <td>{{ $direccionClientePdf }}</td>
                        </tr>
                        <tr>
                            <td class="label">Comuna:</td>
                            <td>{{ $comunaClientePdf ?: '---' }}</td>
                        </tr>
                    </table>
                </td>
                <td width="50%">
                    <table width="100%">
                        <tr>
                            <td class="label">Contacto:</td>
                            <td>{{ $cotizacion->oportunidad->contacto->nombre ?? $cotizacion->cliente->nombre_contacto ?? '' }}
                            </td>
                        </tr>
                        <tr>
                            <td class="label">Teléfono:</td>
                            <td>{{ $cotizacion->oportunidad->contacto->telefono ?? $cotizacion->cliente->telefono ?? '' }}
                            </td>
                        </tr>
                        <tr>
                            <td class="label">Email:</td>
                            <td>{{ $cotizacion->oportunidad->contacto->email ?? $cotizacion->cliente->correo ?? '' }}
                            </td>
                        </tr>
                        @unless ($esPersonaNatural ?? false)
                        <tr>
                            <td class="label">Giro:</td>
                            <td>{{ $giroClientePdf }}</td>
                        </tr>
                        @endunless
                    </table>
                </td>
                </td>
            </tr>
        </table>

        <table
            style="width: 100%; margin-top: 10px; border-top: 1px solid #e5e7eb; padding-top: 5px; font-size: 11px; color: #4b5563;">
            <tr>
                <td style="text-align: left;">
                    <span style="font-weight: bold;">Vendedor:</span> {{ $vendedor->name ?? 'Ejecutivo de Ventas' }}
                </td>
                <td style="text-align: center;">
                    <span style="font-weight: bold;">Email:</span> {{ $vendedor->email ?? '-' }}
                </td>
                <td style="text-align: right;">
                    @if(!empty($vendedor->telefono))
                        <span style="font-weight: bold;">Teléfono:</span> {{ $vendedor->telefono }}
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <table class="table-detalle">
        <thead>
            <tr>
                <th width="5%" class="text-center">#</th>
                <th width="5%" class="text-center">Cant.</th>
                <th width="30%">Producto</th>
                <th width="10%">Talla</th>
                <th width="10%">Color</th>
                <th width="15%">Personalización</th>
                <th width="10%" class="text-right">Unitario</th>
                <th width="15%" class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($cotizacion->detalles as $idx => $item)
                <tr>
                    <td class="text-center text-gray-500">{{ $idx + 1 }}</td>
                    <td class="text-center font-bold">{{ $item->cantidad }}</td>
                    <td>
                        <span
                            style="font-weight: bold; display: block;">{{ $item->producto ?? $item->sku ?? 'Producto sin nombre' }}</span>
                    </td>
                    <td>{{ $item->talla ?? '-' }}</td>
                    <td>{{ $item->color ?? '-' }}</td>
                    <td style="font-size: 10px; color: #666;">
                        {{ $item->tipo_personalizacion ?? '-' }}
                    </td>
                    <td class="text-right">$ {{ number_format($item->precio_unitario, 0, ',', '.') }}</td>
                    <td class="text-right font-bold">$
                        {{ number_format($item->total_neto ?? ($item->precio_unitario * $item->cantidad), 0, ',', '.') }}
                    </td>
                </tr>
            @endforeach

            @for($i = count($cotizacion->detalles); $i < 5; $i++)
                <tr>
                    <td colspan="8" style="height: 25px;"></td>
                </tr>
            @endfor
        </tbody>
    </table>

    <div class="totals-section">
        <div style="float: left; width: 50%; font-size: 11px; color: #555;">
            <strong>Observaciones:</strong><br>
            {{ $cotizacion->observaciones ?? 'Sin observaciones.' }}
            <br><br>
            <strong>Condición de pago:</strong> {{ $cotizacion->condiciones_pago ?? 'Contado / Transferencia' }}<br>
            <strong>Plazo de entrega:</strong> {{ $cotizacion->plazo_produccion ?? '30 días hábiles aprox.' }}<br>
            <strong>Despacho:</strong> {{ $cotizacion->despacho ?? 'Por pagar' }}
        </div>

        <table class="totals-table">
            <tr>
                <td class="text-right"><strong>Subtotal Neto</strong></td>
                <td class="text-right">$ {{ number_format($cotizacion->total_neto, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td class="text-right" style="color: #666;">IVA (19%)</td>
                <td class="text-right" style="color: #666;">$ {{ number_format($cotizacion->iva, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td class="text-right"><strong>TOTAL A PAGAR</strong></td>
                <td class="text-right" style="font-size: 14px; color: #000;"><strong>$
                        {{ number_format($cotizacion->total, 0, ',', '.') }}</strong></td>
            </tr>
        </table>
        <div style="clear: both;"></div>
    </div>

    <table class="signature-payment-table">
        <tr>
            <td style="width: 30%;">
                @php($pago = config('empresa.pago'))
                @if (!empty($pago['cuenta']))
                <div class="payment-info">
                    <strong class="payment-title">Información de Pago:</strong>
                    Tienda Bomberos SpA<br>
                    Rut: 77.753.969-8<br>
                    @if (!empty($pago['banco'])){{ $pago['banco'] }}<br>@endif
                    N°{{ $pago['cuenta'] }}<br>
                    @if (!empty($pago['email'])){{ $pago['email'] }}@endif
                </div>
                @endif
            </td>
            <td style="width: 40%; text-align: center;">
                <!-- Firma (la imagen incluye nombre y cargo) -->
                <img src="{{ public_path('firma_gerente.png') }}" style="width: 250px;">
            </td>
            <td style="width: 30%;"></td>
        </tr>
    </table>

    <div class="footer">
        Tienda Bomberos SpA - Longitudinal Seis 599, Independencia, Santiago - contacto@tiendabomberos.cl
    </div>
</body>

</html>