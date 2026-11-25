<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resumen de Vencimientos</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f7f9; color: #334155; margin: 0; padding: 20px; }
        .container { max-width: 650px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
        .header { background-color: #1e293b; color: white; padding: 30px 20px; text-align: center; }
        .header h1 { margin: 0; font-size: 24px; font-weight: 600; }
        .header p { margin: 5px 0 0; opacity: 0.8; font-size: 14px; }
        .content { padding: 30px; }
        .intro { font-size: 16px; margin-bottom: 25px; }
        .item-card { border: 1px solid #f1f5f9; background-color: #fff; border-radius: 8px; padding: 15px; margin-bottom: 15px; border-left: 4px solid #3b82f6; }
        .item-type { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 5px; }
        .item-title { font-size: 17px; font-weight: 600; color: #0f172a; margin-bottom: 5px; }
        .item-desc { font-size: 14px; color: #475569; margin-bottom: 8px; }
        .item-ref { font-size: 13px; color: #94a3b8; font-style: italic; }
        .footer { background-color: #f8fafc; border-top: 1px solid #e2e8f0; padding: 20px; text-align: center; font-size: 12px; color: #64748b; }
        .highlight { color: #3b82f6; font-weight: 600; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🕒 Próximos Vencimientos</h1>
            <p>Resumen para el día <span style="color: #60a5fa;">{{ $fecha }}</span> (en 3 días)</p>
        </div>
        <div class="content">
            <p class="intro">Hola <strong>{{ $user->name }}</strong>,</p>
            <p class="intro">Tienes <strong>{{ $items->count() }}</strong> compromiso(s) o vencimiento(s) programados para dentro de 3 días. Aquí tienes el detalle:</p>
            
            @foreach($items as $item)
                <div class="item-card" style="border-left-color: {{ 
                    $item['tipo'] === 'Cotización' ? '#ef4444' : 
                    ($item['tipo'] === 'Evento / Agenda' ? '#3b82f6' : 
                    ($item['tipo'] === 'Pago' ? '#8b5cf6' : '#06b6d4')) 
                }};">
                    <div class="item-type">{{ $item['tipo'] }}</div>
                    <div class="item-title">{{ $item['titulo'] }}</div>
                    <div class="item-desc">{{ $item['descripcion'] }}</div>
                    <div class="item-ref">{{ $item['referencia'] }}</div>
                </div>
            @endforeach

            <p style="margin-top: 30px; font-size: 14px; color: #64748b; text-align: center;">
                Recuerda revisar tu <a href="{{ config('app.url') }}/comercial/agenda" style="color: #3b82f6; text-decoration: none; font-weight: 500;">Agenda Comercial</a> para más detalles.
            </p>
        </div>
        <div class="footer">
            Este es un recordatorio automático enviado por el sistema de Tienda Bomberos.<br>
            © {{ date('Y') }} Tienda Bomberos. Todos los derechos reservados.
        </div>
    </div>
</body>
</html>
