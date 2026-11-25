<!doctype html>
<html lang="es">
<head><meta charset="UTF-8"><style>
body { font-family: DejaVu Sans, sans-serif; text-align: center; color: #1e293b; margin-top: 40px; }
h1 { font-size: 36px; } h2 { font-size: 20px; font-weight: normal; }
img { width: 280px; height: 280px; } p { margin: 10px 0; } .meta { font-size: 13px; }
</style></head>
<body>
<h1>¡Evalúanos!</h1><h2>Tu opinión es muy importante para nosotros</h2>
<img src="{{ $qrImage }}" alt="QR de evaluación">
<p>Escanea este código con tu celular para calificar nuestra atención.</p>
<p>Atendido por: <strong>{{ $source['vendedor_nombre'] ?: 'Sin vendedor asignado' }}</strong></p>
@if($details['cliente_nombre'])<p>Contacto / Cliente: <strong>{{ $details['cliente_nombre'] }}</strong></p>@endif
@if($details['empresa_cliente'])<p>Empresa: <strong>{{ $details['empresa_cliente'] }}</strong></p>@endif
<p>Venta / Cotización: <strong>{{ $details['nombre_venta'] ?: 'Sin nombre' }}</strong></p>
<p class="meta">{{ $source['source'] === 'sistema' ? 'Cotización del sistema' : 'Cotización externa' }} #{{ $source['referencia'] }}</p>
</body></html>
