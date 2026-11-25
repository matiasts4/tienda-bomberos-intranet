<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Detalle de Paquete {{ $package->code }}</title>
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

        .info-section {
            background-color: #f9fafb;
            padding: 15px;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            margin-bottom: 25px;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
        }

        .info-table td {
            vertical-align: top;
            padding: 3px;
        }

        .label {
            font-weight: bold;
            color: #4b5563;
            width: 100px;
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
        
        .status-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 10px;
            border: 1px solid #ccc;
            background: #eee;
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
            <h1>Detalle de Paquete N° {{ $package->code }}</h1>
        </div>

        <!-- Fecha Abajo Derecha -->
        <p class="date-line">
            Santiago,
            {{ \Illuminate\Support\Str::ucfirst(\Carbon\Carbon::now()->locale('es')->isoFormat('dddd DD MMMM YYYY')) }}
        </p>
    </div>

    <div class="info-section">
        <table class="info-table">
            <tr>
                <td width="50%">
                    <table width="100%">
                        <tr>
                            <td class="label">Destino:</td>
                            <td><strong>{{ $package->destination }}</strong></td>
                        </tr>
                        <tr>
                            <td class="label">Transporte:</td>
                            <td>{{ $package->transport_type }}</td>
                        </tr>
                    </table>
                </td>
                <td width="50%">
                    <table width="100%">
                        <tr>
                            <td class="label">Días Estimados:</td>
                            <td>{{ $package->estimated_delivery_days }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
        
        @if($package->notes)
            <div style="margin-top: 10px; border-top: 1px solid #e5e7eb; padding-top: 5px; font-size: 11px;">
                <span class="label">Notas: </span> {{ $package->notes }}
            </div>
        @endif
    </div>

    <h3 style="font-size: 14px; margin-bottom: 10px; border-bottom: 1px solid #eee; padding-bottom: 5px;">Contenido del Paquete</h3>
    
    <table class="table-detalle">
        <thead>
            <tr>
                <th width="5%" class="text-center">#</th>
                <th width="40%">Producto</th>
                <th width="15%" class="text-center">Talla</th>
                <th width="20%">Color</th>
                <th width="20%" class="text-right">Cantidad</th>
            </tr>
        </thead>
        <tbody>
            @php $totalUnidades = 0; @endphp
            @foreach($package->items as $idx => $item)
                @php 
                    $qty = $item->pivot->quantity ?? $item->quantity; 
                    $totalUnidades += $qty;
                @endphp
                <tr>
                    <td class="text-center text-gray-500">{{ $idx + 1 }}</td>
                    <td>
                        <span style="font-weight: bold; display: block;">{{ $item->product_type }}</span>
                        @if($item->cuttingOrder)
                            <span style="font-size: 10px; color: #666;">Orden #{{ $item->cuttingOrder->code }}</span>
                        @endif
                    </td>
                    <td class="text-center">{{ $item->size ?: '-' }}</td>
                    <td>{{ $item->color ?: 'Sin color' }}</td>
                    <td class="text-right font-bold">{{ $qty }}</td>
                </tr>
            @endforeach

            @if($package->items->isEmpty())
                <tr>
                    <td colspan="5" class="text-center" style="padding: 20px; color: #666;">No hay ítems en este paquete.</td>
                </tr>
            @endif
        </tbody>
        @if($package->items->isNotEmpty())
            <tfoot>
                <tr>
                    <td colspan="4" class="text-right" style="padding: 10px; font-weight: bold;">TOTAL UNIDADES:</td>
                    <td class="text-right font-bold" style="padding: 10px; font-size: 14px;">{{ $totalUnidades }}</td>
                </tr>
            </tfoot>
        @endif
    </table>

    <div class="footer">
        Tienda Bomberos SpA - Longitudinal Seis 599, Independencia, Santiago - contacto@tiendabomberos.cl
    </div>
</body>

</html>
