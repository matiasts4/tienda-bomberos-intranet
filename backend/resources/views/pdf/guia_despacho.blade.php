<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Guía de Despacho</title>
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
            right: 0;
            width: 260px;
            height: auto;
        }

        .header-title-container {
            position: absolute;
            bottom: 5px;
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
            right: 0;
            margin: 0;
            color: #444;
            font-size: 11px;
            text-align: right;
        }

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

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
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
    </style>
</head>

<body>
    <div class="header">
        <div class="company-info">
            <strong class="company-name">Tienda Bomberos SpA</strong>
            <strong>R.U.T. 77.753.969-8</strong><br>
            Venta de vestuario via com.establecido y online<br>
            Longitudinal Seis 599 – Independencia<br>
            Celular +56 9 99788360
        </div>

        <img src="{{ public_path('tb.logo BLACK.png') }}" class="logo" alt="Logo">

        <div class="header-title-container">
            <h1>Guía de Despacho N° {{ $cotizacion->numero ?? str_pad($cotizacion->id, 4, '0', STR_PAD_LEFT) }}</h1>
        </div>

        <p class="date-line">
            Santiago,
            {{ \Illuminate\Support\Str::ucfirst(\Carbon\Carbon::now()->locale('es')->isoFormat('dddd DD MMMM YYYY')) }}
        </p>
    </div>

    <div class="client-section">
        <h3 style="margin-top: 0; margin-bottom: 10px; border-bottom: 1px solid #ddd; padding-bottom: 5px;">Datos de Envío</h3>
        <table class="client-table">
            <tr>
                <td width="50%">
                    <table width="100%">
                        <tr>
                            <td class="label">Señor(es):</td>
                            <td><strong>{{ $nombreClientePdf }}</strong></td>
                        </tr>
                        <tr>
                            <td class="label">RUT:</td>
                            <td>{{ $rutClientePdf }}</td>
                        </tr>
                        <tr>
                            <td class="label">Contacto:</td>
                            <td>{{ $cotizacion->oportunidad->contacto->nombre ?? $cotizacion->cliente->nombre_contacto ?? '' }}</td>
                        </tr>
                        <tr>
                            <td class="label">Teléfono:</td>
                            <td>{{ $cotizacion->oportunidad->contacto->telefono ?? $cotizacion->cliente->telefono ?? '' }}</td>
                        </tr>
                    </table>
                </td>
                <td width="50%">
                    <table width="100%">
                        <tr>
                            <td class="label">Dirección:</td>
                            <td>{{ $despacho->calle ?? $cotizacion->cliente->direccion ?? '---' }} {{ $despacho->numero_calle ?? '' }}</td>
                        </tr>
                        <tr>
                            <td class="label">Comuna:</td>
                            <td>{{ $despacho->comuna ?? $cotizacion->cliente?->comuna?->nombre ?? '---' }}</td>
                        </tr>
                        <tr>
                            <td class="label">Ciudad:</td>
                            <td>{{ $despacho->ciudad ?? $cotizacion->cliente->ciudad ?? '---' }}</td>
                        </tr>
                        <tr>
                            <td class="label">Agencia:</td>
                            <td><strong>{{ $despacho->agencia ?? '---' }}</strong></td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
        
        @if(!empty($despacho->observacion))
        <div style="margin-top: 10px; padding-top: 10px; border-top: 1px dashed #ccc;">
            <strong>Observación de Despacho:</strong> {{ $despacho->observacion }}
        </div>
        @endif
    </div>

    <table class="table-detalle">
        <thead>
            <tr>
                <th width="5%" class="text-center">#</th>
                <th width="10%" class="text-center">Cant.</th>
                <th width="45%">Producto</th>
                <th width="15%">Talla</th>
                <th width="15%">Color</th>
            </tr>
        </thead>
        <tbody>
            @foreach($cotizacion->detalles as $idx => $item)
                <tr>
                    <td class="text-center text-gray-500">{{ $idx + 1 }}</td>
                    <td class="text-center font-bold">{{ $item->cantidad }}</td>
                    <td>
                        <span style="font-weight: bold; display: block;">{{ $item->producto ?? $item->sku ?? 'Producto sin nombre' }}</span>
                    </td>
                    <td>{{ $item->talla ?? '-' }}</td>
                    <td>{{ $item->color ?? '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div style="margin-top: 50px; text-align: center;">
        <table width="100%">
            <tr>
                <td width="33%"></td>
                <td width="33%" style="text-align: center;">
                    <div style="border-top: 1px solid #333; width: 80%; margin: 0 auto; padding-top: 5px;">
                        Firma Recepción Agencia / Cliente
                    </div>
                </td>
                <td width="33%"></td>
            </tr>
        </table>
    </div>

    <div class="footer">
        Tienda Bomberos SpA - Longitudinal Seis 599, Independencia, Santiago - contacto@tiendabomberos.cl
    </div>
</body>

</html>
