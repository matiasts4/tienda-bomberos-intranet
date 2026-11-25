<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><title>Historial de SKU {{ $sku }}</title>
<style>
body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #182338; }
h1 { font-size: 17px; margin-bottom: 4px; } p { margin: 5px 0 12px; }
table { width: 100%; border-collapse: collapse; table-layout: fixed; }
th { background: #edf1f5; text-align: left; } th, td { padding: 7px; border-bottom: 1px solid #d8dee7; vertical-align: top; overflow-wrap: break-word; }
tr { page-break-inside: avoid; } .secondary { color: #46546a; font-size: 8px; } .number { text-align: right; }
</style></head>
<body>
<h1>{{ $producto }}</h1><p>SKU {{ $sku }} · Historial de movimientos · Saldos de stock disponible</p>
<table><thead><tr>
<th style="width:12%">Fecha y hora</th><th style="width:14%">Movimiento</th><th style="width:19%">Cotización / Cliente</th>
<th style="width:18%">Responsables</th><th style="width:7%" class="number">Unidades</th><th style="width:12%" class="number">Disponible</th><th style="width:18%">Detalle</th>
</tr></thead><tbody>
@foreach($movimientos as $mov)
<tr>
<td>{{ \Carbon\Carbon::parse($mov['fecha_hora'])->format('d/m/Y H:i:s') }}</td>
<td>{{ str_replace('_', ' ', $mov['tipo_movimiento']) }}</td>
<td>
@if($mov['cotizacion_id'])
{{ $mov['cotizacion_numero'] ?: 'Cotización interna' }}<br>ID {{ $mov['cotizacion_id'] }}
@elseif($mov['referencia_externa'])
Externa N.º {{ $mov['referencia_externa'] }}
@else
Sin cotización
@endif
@if($mov['referencia_inconsistente'])<br>Referencias inconsistentes (externa: {{ $mov['referencia_externa'] ?: '—' }})@endif
@if($mov['cotizacion_nombre'])<br>{{ $mov['cotizacion_nombre'] }}@endif
<br><span class="secondary">{{ $mov['cliente_mostrado'] ?: 'Cliente no registrado' }}</span>
</td>
<td>{{ $mov['rol_actor'] }}: {{ $mov['usuario_nombre'] }}
@if($mov['solicitante'])<br><span class="secondary">Solicitó: {{ $mov['solicitante'] }}</span>@endif
@if($mov['resuelto_por'])<br><span class="secondary">{{ $mov['resolucion_tipo'] === 'ANULACION_RESERVA' ? 'Rechazó' : 'Aprobó' }}: {{ $mov['resuelto_por'] }} · {{ \Carbon\Carbon::parse($mov['resuelto_en'])->format('d/m/Y H:i') }}</span>@endif
</td>
<td class="number">{{ $mov['cantidad'] }}</td>
<td class="number">{{ $mov['saldo_anterior'] }} → {{ $mov['saldo_nuevo'] }}<br><span class="secondary">{{ $mov['variacion_disponible'] === 0 ? 'Sin cambio' : ($mov['variacion_disponible'] > 0 ? '+' : '').$mov['variacion_disponible'] }}</span></td>
<td><span class="secondary">Movimiento {{ $mov['id'] }}
@if($mov['peticion_id']) · Solicitud {{ $mov['peticion_id'] }}@endif
@if($mov['estado_solicitud_actual'])<br>Estado actual: {{ $mov['estado_solicitud_actual'] }}@endif
@if($mov['id_lote'])<br>Lote: {{ $mov['id_lote'] }}@endif
</span>
@if($mov['observacion'])<br>{{ $mov['observacion'] }}@endif
@if($mov['observacion_solicitud'] && $mov['observacion_solicitud'] !== $mov['observacion'])<br>Solicitud: {{ $mov['observacion_solicitud'] }}@endif
</td>
</tr>
@endforeach
</tbody></table>
</body></html>
