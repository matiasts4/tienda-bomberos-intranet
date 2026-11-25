<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Stock Crítico</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f7f9; color: #334155; margin: 0; padding: 20px; }
        .container { max-width: 650px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
        .header { background-color: #0f172a; color: white; padding: 30px 20px; text-align: center; }
        .header h1 { margin: 0; font-size: 24px; font-weight: 600; }
        .header p { margin: 5px 0 0; opacity: 0.8; font-size: 14px; }
        .content { padding: 35px 30px; text-align: center; }
        .icon-container { width: 64px; height: 64px; background-color: #f1f5f9; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 20px; }
        .icon-container svg { width: 32px; height: 32px; color: #3b82f6; }
        .intro { font-size: 16px; margin-bottom: 15px; line-height: 1.6; }
        .highlight-box { background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin: 25px 0; text-align: left; }
        .highlight-box h3 { margin-top: 0; font-size: 15px; color: #0f172a; margin-bottom: 10px; }
        .highlight-box p { margin: 0; font-size: 14px; color: #475569; }
        .footer { background-color: #f8fafc; border-top: 1px solid #e2e8f0; padding: 20px; text-align: center; font-size: 12px; color: #64748b; }
        .btn { display: inline-block; background-color: #3b82f6; color: white; text-decoration: none; padding: 12px 24px; border-radius: 6px; font-weight: 600; font-size: 14px; margin-top: 10px; transition: background-color 0.2s; }
        .btn:hover { background-color: #2563eb; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Reporte de Inventario</h1>
            <p>Generado el {{ $fecha }}</p>
        </div>
        <div class="content">
            <div class="icon-container">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
            </div>
            
            <p class="intro">
                Hola,<br><br>
                Se ha generado exitosamente el reporte de stock que solicitaste. Hemos adjuntado a este correo un documento PDF con todos los detalles detallados sobre el estado actual de los niveles de inventario.
            </p>

            <div class="highlight-box">
                <h3>Acerca de este reporte</h3>
                <p>Este documento incluye información sobre productos que se encuentran cerca o por debajo de su punto de re-orden, ordenados por nivel de criticidad. Te recomendamos revisarlo a la brevedad para coordinar abastecimientos.</p>
            </div>

            <p style="font-size: 14px; color: #64748b; margin-top: 30px;">
                También puedes monitorear el inventario en tiempo real ingresando al sistema.
            </p>
            
            <a href="{{ config('app.url') }}/alertas" class="btn">Abrir Monitor en Vivo</a>
        </div>
        <div class="footer">
            Este es un reporte automático generado desde Tienda Bomberos.<br>
            © {{ date('Y') }} Tienda Bomberos. Todos los derechos reservados.
        </div>
    </div>
</body>
</html>
