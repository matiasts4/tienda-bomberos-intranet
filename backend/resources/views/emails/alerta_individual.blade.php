<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alerta Inmediata de Stock</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #f8fafc; color: #334155; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0; }
        .header { background-color: #dc2626; color: white; padding: 20px; text-align: center; }
        .header h1 { margin: 0; font-size: 20px; font-weight: 600; }
        .content { padding: 30px; }
        .content p { font-size: 16px; line-height: 1.5; margin-top: 0; }
        .data-box { background-color: #f1f5f9; border-left: 4px solid #ef4444; padding: 15px; margin-bottom: 20px; border-radius: 0 4px 4px 0; }
        .data-label { font-size: 12px; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px; }
        .data-value { font-size: 18px; font-weight: 600; color: #0f172a; margin-bottom: 12px; }
        .data-value:last-child { margin-bottom: 0; }
        .footer { background-color: #f8fafc; border-top: 1px solid #e2e8f0; padding: 15px; text-align: center; font-size: 12px; color: #94a3b8; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⚠️ Alerta Inmediata de Stock</h1>
        </div>
        <div class="content">
            <p>Hola,</p>
            <p>El sistema ha detectado que el siguiente artículo ha cruzado su umbral de stock crítico en este momento:</p>
            
            <div class="data-box">
                <div class="data-label">TIPO</div>
                <div class="data-value">{{ $tipo === 'pte' ? 'Producto Terminado (PTE)' : 'Materia Prima (MP)' }}</div>
                
                <div class="data-label">SKU / CÓDIGO</div>
                <div class="data-value">{{ $sku }}</div>

                <div class="data-label">NOMBRE</div>
                <div class="data-value">{{ $nombre }}</div>

                <div class="data-label">STOCK ACTUAL</div>
                <div class="data-value" style="color: #dc2626;">{{ $stock_actual }} {{ $unidad ?? 'Unidades' }}</div>

                <div class="data-label">UMBRAL CRÍTICO</div>
                <div class="data-value">{{ $stock_critico }} {{ $unidad ?? 'Unidades' }}</div>
            </div>

            <p>Por favor, gestiona el inventario o aprovisionamiento lo antes posible para evitar quiebres.</p>
        </div>
        <div class="footer">
            Enviado automáticamente por Tienda Bomberos a las {{ date('H:i d/m/Y') }}
        </div>
    </div>
</body>
</html>
