<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Stock Crítico</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #333; }
        
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

        .header h1 { margin: 10px 0 5px 0; font-size: 18px; text-transform: uppercase; color: #cc0000; }
        .header p { margin: 0; color: #666; font-size: 10px; }
        
        .section-container { margin-bottom: 25px; page-break-inside: avoid; }
        
        .section-header { background-color: #f3f4f6; padding: 8px; border: 1px solid #ddd; border-bottom: none; display: flex; justify-content: space-between; }
        .section-title { font-weight: bold; font-size: 13px; text-transform: uppercase; color: #444; }
        
        .badge { padding: 3px 6px; border-radius: 4px; font-weight: bold; font-size: 9px; color: white; display: inline-block; min-width: 50px; text-align: center; }
        .bg-green { background-color: #10b981; }
        .bg-red { background-color: #dc2626; }    
        .bg-orange { background-color: #d97706; } 

        .table-detalle { width: 100%; border-collapse: collapse; border: 1px solid #ddd; }
        .table-detalle th { background-color: #e5e7eb; padding: 5px; text-align: left; font-size: 10px; border-bottom: 1px solid #bbb; }
        .table-detalle td { padding: 5px; border-bottom: 1px solid #eee; font-size: 11px; vertical-align: middle; }
        
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-mono { font-family: monospace; }
        .font-bold { font-weight: bold; }
        .text-red { color: #dc2626; }
        
        .footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 9px; color: #999; border-top: 1px solid #eee; padding-top: 5px; }
    </style>
</head>
<body>
    <div class="header">
        <img src="{{ public_path('tb.logo BLACK.png') }}" class="logo" alt="Logo">
        
        <h1>Alerta de Stock Crítico</h1>
        <p>Generado el: {{ $fecha }}</p>
    </div>

    {{-- ========================================================================
         SECCIÓN 1: PRODUCTOS TERMINADOS (PTE)
         Solo se muestra si $pte NO ES NULL (es decir, si se seleccionó en el filtro)
       ======================================================================== --}}
    @if(isset($pte) && (is_array($pte) || $pte instanceof \Countable) && count($pte) > 0)
    <div class="section-container">
        <div class="section-header">
            <span class="section-title">Productos Terminados (PTE)</span>
            <span style="font-size: 10px; color: #666;">Total Críticos: {{ count($pte) }}</span>
        </div>

        <table class="table-detalle">
            <thead>
                <tr>
                    <th width="15%">SKU</th>
                    <th width="45%">Producto</th>
                    <th width="10%" class="text-center">Talla</th>
                    <th width="10%" class="text-right">Actual</th>
                    <th width="10%" class="text-right">Crítico</th>
                    <th width="10%" class="text-center">Estado</th>
                </tr>
            </thead>
            <tbody>
                @forelse($pte as $item)
                    @php
                        $isCritical = $item->cantidad <= 0;
                        $badgeColor = $isCritical ? 'bg-red' : 'bg-orange';
                        $badgeText  = $isCritical ? 'AGOTADO' : 'BAJO';
                    @endphp
                    <tr>
                        <td class="font-mono">{{ $item->sku }}</td>
                        <td>{{ $item->skuProducto?->nombre ?? 'Producto No Encontrado' }}</td>
                        <td class="text-center font-bold">{{ $item->skuProducto?->talla?->nombre ?? '-' }}</td>
                        <td class="text-right font-bold {{ $isCritical ? 'text-red' : '' }}">{{ $item->cantidad }}</td>
                        <td class="text-right">{{ $item->stock_critico }}</td>
                        <td class="text-center"><span class="badge {{ $badgeColor }}">{{ $badgeText }}</span></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center" style="padding: 15px; color: #10b981;">
                            <strong>✅ Todo excelente. No hay productos bajo el nivel crítico.</strong>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @endif

    {{-- ========================================================================
         SECCIÓN 2: MATERIAS PRIMAS (MP)
         Solo se muestra si $mp NO ES NULL
       ======================================================================== --}}
    @if(isset($mp) && (is_array($mp) || $mp instanceof \Countable) && count($mp) > 0)
    <div class="section-container">
        <div class="section-header">
            <span class="section-title">Materias Primas (MP)</span>
            <span style="font-size: 10px; color: #666;">Total Críticos: {{ count($mp) }}</span>
        </div>

        <table class="table-detalle">
            <thead>
                <tr>
                    <th width="15%">Código</th>
                    <th width="45%">Material</th>
                    <th width="15%" class="text-right">Total Disp.</th>
                    <th width="15%" class="text-right">Mínimo</th>
                    <th width="10%" class="text-center">Estado</th>
                </tr>
            </thead>
            <tbody>
                @forelse($mp as $item)
                    @php
                        $stockActual = $item->lotes_sum_cantidad_actual ?? 0;
                        $isCritical = $stockActual <= 0;
                        $badgeColor = $isCritical ? 'bg-red' : 'bg-orange';
                        $unidad     = $item->unidad->abreviacion ?? '';
                    @endphp
                    <tr>
                        <td class="font-mono">{{ $item->codigo_interno }}</td>
                        <td>{{ $item->nombre_base }}</td>
                        <td class="text-right font-bold {{ $isCritical ? 'text-red' : '' }}">
                            {{ $stockActual }} <span style="font-size:9px; font-weight:normal;">{{ $item->unidad?->abreviacion ?? '' }}</span>
                        </td>
                        <td class="text-right">{{ $item->stock_minimo }}</td>
                        <td class="text-center"><span class="badge {{ $badgeColor }}">{{ $isCritical ? 'AGOTADO' : 'BAJO' }}</span></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center" style="padding: 15px; color: #10b981;">
                            <strong>✅ Todo excelente. No faltan materias primas.</strong>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @endif

    <div class="footer">
        Documento de control interno - Tienda Bomberos | Reporte Automático
    </div>
</body>
</html>