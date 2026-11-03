<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\BodegaController;
use App\Http\Controllers\Api\EncuestaController;

// Módulo PTE / Inventario

use App\Http\Controllers\StockController;
use App\Http\Controllers\SkuController;
use App\Http\Controllers\CatalogoController;
use App\Http\Controllers\MovimientoController;

// Módulo Comercial
use App\Http\Controllers\OportunidadController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\Api\EstadoController;
use App\Http\Controllers\Api\CotizacionController;
use App\Http\Controllers\AlertasController;
use App\Http\Controllers\EventoController;
use App\Http\Controllers\Api\OrderRequestController;
use App\Http\Controllers\Api\ComercialSkuGenericoController;
use App\Http\Controllers\ContactoController;
use App\Http\Controllers\ProspeccionController;
use App\Http\Controllers\ProveedorController;

//Módulo Fábrica

use App\Http\Controllers\F_OrdenProduccionController;
use App\Http\Controllers\F_ControlCalidadController;
use App\Http\Controllers\F_ReparacionController;
use App\Http\Controllers\F_MpMateriaPrimaController;
use App\Http\Controllers\F_MpProveedorController;
use App\Http\Controllers\F_FichaTecnicaController;
use App\Http\Controllers\F_MpEspecificacionController;
use App\Http\Controllers\F_MpStockController;
use App\Http\Controllers\MpMaterialController;
use App\Http\Controllers\MpGestionController;
use App\Http\Controllers\Api\CuttingOrderController;
use App\Http\Controllers\Api\PackageController;

// Ordenes de Corte & Paquetes (Nueva implementación Fábrica)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/cutting-orders', [CuttingOrderController::class, 'index']);
    Route::post('/cutting-orders', [CuttingOrderController::class, 'store']);
    Route::get('/cutting-orders/next-code/generate', [CuttingOrderController::class, 'generateNextCode']);
    Route::get('/cutting-orders/{id}', [CuttingOrderController::class, 'show']);
    Route::put('/cutting-orders/{id}', [CuttingOrderController::class, 'update']);
    Route::delete('/cutting-orders/{id}', [CuttingOrderController::class, 'destroy']);

    // Paquetes
    Route::get('/packages/next-code', [PackageController::class, 'getNextCode']);
    Route::get('/packages', [PackageController::class, 'index']);
    Route::post('/packages', [PackageController::class, 'store']);
    Route::put('/packages/{package}', [PackageController::class, 'updateStatus']);
    Route::put('/packages/{package}/status', [PackageController::class, 'updateStatus']);
    Route::put('/packages/{package}/partial-qc', [PackageController::class, 'updatePartialQc']);
    Route::post('/packages/{package}/quality-control', [PackageController::class, 'registerQualityControl']);
    Route::get('/packages/{package}/pdf', [PackageController::class, 'exportPdf']);
});

//Auth user info
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:sanctum')->group(function () {
    // SKU / Productos
    Route::get('/productos', [SkuController::class, 'index']);
    Route::post('/productos/bulk', [SkuController::class, 'storeBulk'])->middleware('role:superadmin');
    Route::post('/productos', [SkuController::class, 'store'])->middleware('role:superadmin');
    Route::get('/productos/{sku}', [SkuController::class, 'show']);
    Route::put('/productos/{id}', [SkuController::class, 'update'])->middleware('role:superadmin');
    Route::delete('/productos/{id}', [SkuController::class, 'destroy'])->middleware('role:superadmin');
    Route::patch('/productos/{id}/deactivate', [SkuController::class, 'deactivate'])->middleware('role:superadmin');

    // Admin SKU Genéricos
    Route::get('/sku-genericos/admin', [\App\Http\Controllers\Api\SkuGenericoAdminController::class, 'index']);
    Route::get('/sku-genericos/admin/{id}', [\App\Http\Controllers\Api\SkuGenericoAdminController::class, 'show']);
    Route::get('/sku-genericos/{id}/archive-preview', [\App\Http\Controllers\Api\SkuGenericoAdminController::class, 'archivePreview']);

    Route::middleware('role:superadmin')->group(function () {
        Route::post('/sku-genericos', [\App\Http\Controllers\Api\SkuGenericoAdminController::class, 'store']);
        Route::put('/sku-genericos/{id}', [\App\Http\Controllers\Api\SkuGenericoAdminController::class, 'update']);
        Route::delete('/sku-genericos/{id}', [\App\Http\Controllers\Api\SkuGenericoAdminController::class, 'destroy']);
        Route::post('/sku-genericos/{id}/publish', [\App\Http\Controllers\Api\SkuGenericoAdminController::class, 'publish']);
        Route::post('/sku-genericos/{id}/reactivate', [\App\Http\Controllers\Api\SkuGenericoAdminController::class, 'reactivate']);
        Route::post('/sku-genericos/{id}/upload', [\App\Http\Controllers\Api\SkuGenericoAdminController::class, 'uploadAssets']);
        Route::post('/sku-genericos/{id}/add-variants', [\App\Http\Controllers\Api\SkuGenericoAdminController::class, 'addVariants']);
        Route::patch('/sku-genericos/{id}/variants/{sku}/name', [\App\Http\Controllers\Api\SkuGenericoAdminController::class, 'renameVariant']);
        Route::post('/sku-genericos/{id}/variants/archive', [\App\Http\Controllers\Api\SkuGenericoAdminController::class, 'archiveVariants']);
        Route::post('/sku-genericos/{id}/variants/reactivate', [\App\Http\Controllers\Api\SkuGenericoAdminController::class, 'reactivateVariants']);
    });

    // Reporte de inventario 
    Route::get('/inventario', [SkuController::class, 'inventario']);
    Route::get('/inventario/reporte', [SkuController::class, 'reporteInventario']);

    // Stock (legacy / obsoleto)
    Route::get('/stock/{sku}', [StockController::class, 'consultarStock']);
    Route::post('/stock/ingreso', [StockController::class, 'agregarStock'])->middleware('role:superadmin');
});

// MOVIMIENTOS (Protegidos)
Route::middleware('auth:sanctum')->group(function () {
    // 1. PRIMERO LAS RUTAS ESPECÍFICAS (TEXTO FIJO)
    Route::get('/movimientos/ingresos', [MovimientoController::class, 'indexIngresos']);
    Route::get('/movimientos/general', [\App\Http\Controllers\BitacoraController::class, 'historialGeneral']);
    Route::get('/movimientos/usuarios', [\App\Http\Controllers\BitacoraController::class, 'usuarios']);
    Route::get('/movimientos/lote-items', [\App\Http\Controllers\BitacoraController::class, 'loteItems']);
    Route::get('/movimientos/pdf/mensual', [\App\Http\Controllers\BitacoraController::class, 'monthlyPdf'])->middleware('throttle:10,1');
    Route::get('/movimientos/pdf/semanal', [\App\Http\Controllers\BitacoraController::class, 'weeklyPdf'])->middleware('throttle:10,1');
    Route::get('/movimientos/pdf/individual', [MovimientoController::class, 'descargarPdfIndividual']);

    // Rutas de escritura y gestión
    Route::post('/movimientos/ingresos', [MovimientoController::class, 'storeIngreso'])->middleware('role:superadmin');
    Route::post('/movimientos/ingresos/{id}/anular', [MovimientoController::class, 'anularIngreso'])->middleware('role:superadmin');
    Route::post('/movimientos/salida', [MovimientoController::class, 'storeSalidaCotizacion'])->middleware('role:superadmin');
    Route::post('/movimientos/importar', [MovimientoController::class, 'importarExcel'])->middleware('role:superadmin');
});

// Rutas públicas o semi-públicas (lectura)
Route::get('/movimientos/pdf', [\App\Http\Controllers\BitacoraController::class, 'disabledGeneralPdf']);
Route::get('/movimientos/{id}', [MovimientoController::class, 'show']);
Route::get('/productos/{sku}/movimientos', [MovimientoController::class, 'historialPorSku']);
// Nueva ruta para PDF por SKU
Route::get('/productos/{sku}/movimientos/pdf', [MovimientoController::class, 'descargarPdfPorSku']);

// Catálogos
Route::get('/catalogos/todos', [CatalogoController::class, 'index']);
Route::get('/catalogos/unidades', [CatalogoController::class, 'unidades']);
Route::get('/catalogos/origenes', [CatalogoController::class, 'origenes']);
Route::get('/catalogos/grupos', [CatalogoController::class, 'gruposFamilia']);
Route::get('/catalogos/familias', [CatalogoController::class, 'familias']);
Route::get('/catalogos/subfamilias', [CatalogoController::class, 'subfamilias']);
Route::get('/catalogos/tipos', [CatalogoController::class, 'tipos']);
Route::get('/catalogos/formatos', [CatalogoController::class, 'formatos']);
Route::get('/catalogos/generos', [CatalogoController::class, 'generos']);
Route::get('/catalogos/colores', [CatalogoController::class, 'colores']);
Route::get('/catalogos/tallas', [CatalogoController::class, 'tallas']);
Route::get('/catalogos/cargos', [CatalogoController::class, 'cargos']);

// ==========================================
// MÓDULO COMERCIAL
// ==========================================

Route::middleware('auth:sanctum')->group(function () {
    // Encuestas
    Route::get('/encuestas', [EncuestaController::class, 'index']);
    Route::get('/encuestas/cotizaciones', [EncuestaController::class, 'quoteOptions']);
    Route::get('/encuestas/contactos', [EncuestaController::class, 'contacts']);
    Route::post('/encuestas/qr-pdf', [EncuestaController::class, 'downloadManualQrPdf'])->middleware('throttle:20,1');
    Route::get('/encuestas/qrs', [EncuestaController::class, 'qrHistory']);
    Route::get('/encuestas/qrs/{qr}/pdf', [EncuestaController::class, 'reprintQr'])->middleware('throttle:20,1');
    Route::get('/encuestas/manual-qr-pdf', [EncuestaController::class, 'downloadManualQrPdf']);

    // Oportunidades
    Route::apiResource('oportunidades', OportunidadController::class)
        ->parameters(['oportunidades' => 'oportunidad']);

    Route::patch(
        'oportunidades/{oportunidad}/estado',
        [OportunidadController::class, 'updateEstado']
    );

    Route::post('oportunidades/{oportunidad}/traspasar', [\App\Http\Controllers\Api\TraspasoResponsableController::class, 'oportunidad']);

    Route::get('oportunidades/{oportunidad}/gestiones', [\App\Http\Controllers\Api\OportunidadGestionController::class, 'index']);
    Route::post('oportunidades/{oportunidad}/gestiones', [\App\Http\Controllers\Api\OportunidadGestionController::class, 'store']);
    // Route removida: pasarAVenta no tiene implementación en el controller.

    // Estados
    Route::get('/estados', [EstadoController::class, 'index']);

    // Ejecutivos
    Route::get('/ejecutivos', [UserController::class, 'ejecutivos']);

    // Clientes
    Route::apiResource('clientes', ClienteController::class);
    Route::post('/clientes/{cliente}/logo', [ClienteController::class, 'uploadLogo']);

    // Contactos
    Route::get('/clientes/{cliente}/contactos', [ContactoController::class, 'index']);
    Route::get('/contactos', [ContactoController::class, 'indexAll']);
    Route::get('/reportes/red-bomberos', [\App\Http\Controllers\Api\ReporteRedBomberosController::class, 'index']);
    // Bitácora de gestión (antes de las rutas con {contacto} para evitar colisión)
    Route::get('/contactos/actividades', [ContactoController::class, 'actividadesGlobal']);
    Route::get('/contactos/conteos', [ContactoController::class, 'conteos']);
    Route::get('/contactos/{contacto}/actividades', [ContactoController::class, 'actividades']);
    // Asignar después a un Cuerpo, Compañía o empresa (y sus leads/cotizaciones abiertos)
    Route::get('/contactos/{contacto}/registros-sin-entidad', [ContactoController::class, 'registrosSinEntidad']);
    Route::post('/contactos/{contacto}/asignar', [ContactoController::class, 'asignar']);
    Route::post('/contactos', [ContactoController::class, 'store']);
    Route::patch('/contactos/{contacto}', [ContactoController::class, 'update']);
    Route::delete('/contactos/{contacto}', [ContactoController::class, 'destroy']);
    Route::get('/cuerpos/{cuerpo}/contactos', [ContactoController::class, 'porCuerpo']);
    Route::get('/companias/{compania}/contactos', [ContactoController::class, 'porCompania']);
    Route::get('/cuerpos/{cuerpo}/companias', [\App\Http\Controllers\CompaniaController::class, 'porCuerpo']);

    // CALENDARIO / EVENTOS (NUEVO)
    Route::get('agenda/avisos', [\App\Http\Controllers\AgendaAvisoController::class, 'index']);
    Route::post('agenda/avisos/registrar', [\App\Http\Controllers\AgendaAvisoController::class, 'register'])->middleware('throttle:60,1');
    Route::apiResource('eventos', EventoController::class);

    // Cuerpos y Compañias
    Route::apiResource('cuerpos', \App\Http\Controllers\CuerpoController::class);
    Route::post('cuerpos/{cuerpo}/logo', [\App\Http\Controllers\CuerpoController::class, 'uploadLogo']);
    Route::apiResource('companias', \App\Http\Controllers\CompaniaController::class);

    // ==========================================
    // MÓDULO PROSPECCIÓN
    // ==========================================
    Route::prefix('prospeccion')->group(function () {
        Route::get('/contactos', [ProspeccionController::class, 'index']);
        Route::get('/contactos/counts', [ProspeccionController::class, 'counts']);
        Route::get('/arbol', [ProspeccionController::class, 'arbol']);
        Route::get('/arbol/cuerpos/{cuerpo}', [ProspeccionController::class, 'arbolCuerpo']);
        Route::get('/gestiones', [ProspeccionController::class, 'gestiones']);
        Route::get('/contactos/{contacto}/historial', [ProspeccionController::class, 'historial']);
        Route::post('/contactos/{contacto}/interaccion', [ProspeccionController::class, 'interaccion']);
        Route::post('/contactos/{contacto}/calificar', [ProspeccionController::class, 'calificar']);
        Route::post('/contactos/{contacto}/derivar', [ProspeccionController::class, 'derivar']);
        Route::delete('/pausas/{pausa}', [ProspeccionController::class, 'levantarPausa']);
        Route::get('/metricas', [ProspeccionController::class, 'metricas']);
        Route::get('/cierres-diarios', [ProspeccionController::class, 'cierresDiarios']);
        Route::get('/tipos', [ProspeccionController::class, 'tipos']);
    });
});

// Proveedores
//Route::apiResource('proveedores', ProveedorController::class);

// Geografia
Route::get('/geografia/zonas', [App\Http\Controllers\GeografiaController::class, 'zonas']);
Route::get('/geografia/regiones', [App\Http\Controllers\GeografiaController::class, 'regiones']);
Route::get('/geografia/provincias', [App\Http\Controllers\GeografiaController::class, 'provincias']);
Route::get('/geografia/comunas', [App\Http\Controllers\GeografiaController::class, 'comunas']);

// Encuestas (Público)
Route::post('/encuestas', [EncuestaController::class, 'store']);

// AUTH - Login necesita 'web' para sesión/CSRF. Las demás usan Bearer token.
Route::middleware('web')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);
});

// Estas rutas usan Bearer token (auth:sanctum) — NO necesitan sesión web
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
});

Route::get('/login', function () {
    return response()->json([
        'message' => 'No autenticado. Por favor inicie sesión.'
    ], 401);
})->name('login');

//Usuarios ejecutivos (USERS con role ejecutivo/superadmin)
Route::middleware('auth:sanctum')->get('/ejecutivos', [UserController::class, 'ejecutivos']);
Route::middleware('auth:sanctum')->get('/usuarios/ejecutivos', [UserController::class, 'ejecutivos']);
Route::apiResource('users', UserController::class)->only(['index', 'show'])->middleware('auth:sanctum');
Route::apiResource('users', UserController::class)->only(['store', 'update', 'destroy'])->middleware(['auth:sanctum', 'role:superadmin']);

// ==========================================
// BODEGA MP
// ==========================================
Route::prefix('bodega')->group(function () {

    // 1. LISTAR EXISTENCIAS
    Route::get('/existencias', [MpGestionController::class, 'index']);

    // 2. DETALLE DE LOTES
    Route::get('/material/{id}/lotes', [MpGestionController::class, 'getLotesByMaterial']);
    Route::get('/proveedores', [MpGestionController::class, 'indexProveedores']);
    Route::post('/crear-material', [MpGestionController::class, 'storeMaterial']);
    Route::post('/crear-proveedor', [MpGestionController::class, 'storeProveedor']);
    Route::put('/proveedor/{id}', [MpGestionController::class, 'updateProveedor']);
    Route::delete('/proveedor/{id}', [MpGestionController::class, 'destroyProveedor']);

    Route::get('/formulario-datos', [MpGestionController::class, 'getDatosFormulario']);
    Route::post('/ingreso', [MpGestionController::class, 'registrarIngreso']);
    Route::post('/sync-catalogo', [MpGestionController::class, 'syncCatalogoProveedor']);
    Route::get('/catalogo-completo', [MpGestionController::class, 'getCatalogoCompleto']);

    // CRUD / Legacy
    Route::get('/materiales', [MpGestionController::class, 'index']);
    Route::get('/materiales/{id}', [MpMaterialController::class, 'show']);
    Route::put('/material/{id}', [MpGestionController::class, 'updateMaterial']);
    Route::delete('/material/{id}', [MpGestionController::class, 'destroyMaterial']);
    Route::put('/lote/{id}', [MpGestionController::class, 'updateLote']);
    Route::delete('/lote/{id}', [MpGestionController::class, 'destroyLote']);
});

// ==========================================
// ALERTAS / REPORTES
// ==========================================
Route::prefix('alertas')->group(function () {
    // 1. Obtener datos para las tablas (PTE, MP y Config)
    Route::get('/criticos', [AlertasController::class, 'getCriticos']);

    // 2. Guardar la configuración (horarios, emails automáticos)
    Route::post('/config', [AlertasController::class, 'updateConfig']);

    // 3. Enviar reporte manual ("Enviar Ahora")
    Route::post('/enviar', [AlertasController::class, 'enviarReporte']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/order-requests', [OrderRequestController::class, 'index']);
    Route::get('/order-requests/count', [OrderRequestController::class, 'countPending']);
    Route::get('/order-requests/receipt', [OrderRequestController::class, 'downloadReceiptPdf']);
    Route::get('/order-requests/quotes', [OrderRequestController::class, 'quotes']);
    Route::post('/order-requests', [OrderRequestController::class, 'store'])->middleware('role:superadmin,ejecutivo,bodega');
    Route::post('/order-requests/bulk-confirm', [OrderRequestController::class, 'bulkConfirm'])->middleware('role:superadmin');
    Route::post('/order-requests/bulk-reject', [OrderRequestController::class, 'bulkReject'])->middleware('role:superadmin');
    Route::post('/order-requests/{id}/confirm', [OrderRequestController::class, 'confirm'])->middleware('role:superadmin');
    Route::post('/order-requests/{id}/reject', [OrderRequestController::class, 'reject'])->middleware('role:superadmin');
    Route::get('/order-requests/{id}/pdf', [OrderRequestController::class, 'downloadRequestPdf']);
    Route::get('/order-requests/quote/{id}/pdf', [OrderRequestController::class, 'downloadQuoteRequestPdf']);
    Route::get('/order-requests/{id}/dispatch-pdf', [OrderRequestController::class, 'downloadDispatchPdf']);
    Route::get('/order-requests/quote/{id}/dispatch-pdf', [OrderRequestController::class, 'downloadDispatchPdfByCotizacion']);

});

// Cotizaciones options (no requiere autenticación)
Route::get('/cotizaciones/options', [\App\Http\Controllers\Api\CotizacionOptionsController::class, 'index']);

// Comercial SKUs Genericos (no requiere autenticación)
Route::get('/comercial-skus', [ComercialSkuGenericoController::class, 'index']);

// Cotizaciones (requieren autenticación para $request->user())
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/cotizaciones', [CotizacionController::class, 'index']);
    Route::post('/cotizaciones', [CotizacionController::class, 'store']);
    Route::get('/cotizaciones/{cotizacion}', [CotizacionController::class, 'show']);
    Route::patch('/cotizaciones/{cotizacion}', [CotizacionController::class, 'update']);
    Route::delete('/cotizaciones/{cotizacion}', [CotizacionController::class, 'destroy']);
    Route::put('/cotizaciones/{id}/estado', [CotizacionController::class, 'updateEstado']);
    Route::get('/cotizaciones/{id}/qr', [EncuestaController::class, 'generateQr']);
    Route::patch('/cotizaciones/{cotizacion}/estado', [CotizacionController::class, 'updateEstado']);
    Route::patch('/cotizaciones/{cotizacion}/personalizacion-completada', [CotizacionController::class, 'marcarPersonalizacionCompletada']);
    Route::post('/cotizaciones/{cotizacion}/send-personalizacion', [CotizacionController::class, 'sendToPersonalizacion']);
    Route::put('/cotizaciones/{cotizacion}/personalizacion-configuracion', [CotizacionController::class, 'savePersonalizacion']);
    Route::post('/cotizaciones/{cotizacion}/gestion', [CotizacionController::class, 'storeGestion']);
    Route::post('/cotizaciones/{cotizacion}/traspasar', [\App\Http\Controllers\Api\TraspasoResponsableController::class, 'cotizacion']);
    Route::post('/cotizaciones/{cotizacion}/archivos', [CotizacionController::class, 'uploadArchivo']);

    Route::get('/cotizacion-items/{item}/personalizacion', [\App\Http\Controllers\Api\CotizacionItemPersonalizacionController::class, 'show']);
    Route::put('/cotizacion-items/{item}/personalizacion', [\App\Http\Controllers\Api\CotizacionItemPersonalizacionController::class, 'update']);
    Route::post('/cotizacion-items/{item}/personalizacion/upload', [\App\Http\Controllers\Api\CotizacionItemPersonalizacionController::class, 'uploadMatrizImage']);
    Route::post('/cotizacion-items/{item}/personalizacion/upload-names', [\App\Http\Controllers\Api\CotizacionItemPersonalizacionController::class, 'uploadNames']);
});

Route::get('/factory-logs', [App\Http\Controllers\Api\FactoryLogController::class, 'index']);
Route::middleware('auth:sanctum')->get('/cotizaciones/{cotizacion}/pdf', [CotizacionController::class, 'exportPdf']);
Route::middleware('auth:sanctum')->post('/cotizaciones/{cotizacion}/anular', [CotizacionController::class, 'anular']);
Route::get('/insumos', [App\Http\Controllers\Api\InsumoController::class, 'index']);

// ==========================================
// MÓDULO PERSONALIZACIÓN
// ==========================================
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/personalizaciones/available-products', [\App\Http\Controllers\Api\PersonalizacionController::class, 'getAvailableProducts']);
    Route::post('/personalizaciones/bulk', [\App\Http\Controllers\Api\PersonalizacionController::class, 'storeBulk']);
    Route::post('/personalizaciones/manual', [\App\Http\Controllers\Api\PersonalizacionController::class, 'storeManual']);
    Route::post('/personalizaciones/assets', [\App\Http\Controllers\Api\PersonalizacionController::class, 'uploadAsset']);
    Route::post('/personalizaciones/{personalizacion}/quality-control', [\App\Http\Controllers\Api\PersonalizacionController::class, 'qualityControl']);
    Route::post('/personalizaciones/{personalizacion}/control_calidad', [\App\Http\Controllers\Api\PersonalizacionController::class, 'qualityControl']);
    Route::post('/personalizaciones/{personalizacion}/split', [\App\Http\Controllers\Api\PersonalizacionController::class, 'splitItem']);
    Route::post('/personalizaciones/{personalizacion}/merge', [\App\Http\Controllers\Api\PersonalizacionController::class, 'mergeItems']);
    Route::post('/personalizaciones/{personalizacion}/archivos/{tipo}/confirm', [\App\Http\Controllers\Api\PersonalizacionController::class, 'confirmArchivo']);
    Route::post('/personalizaciones/{personalizacion}/archivos/upsert', [\App\Http\Controllers\Api\PersonalizacionController::class, 'upsertArchivo']);
    Route::delete('/personalizaciones/cotizacion/{cotizacion_id}', [\App\Http\Controllers\Api\PersonalizacionController::class, 'destroyByCotizacion']);
    Route::apiResource('personalizaciones', \App\Http\Controllers\Api\PersonalizacionController::class);

    Route::get('/cotizacion-items/{item}/personalizacion', [\App\Http\Controllers\Api\CotizacionItemPersonalizacionController::class, 'show']);
    Route::put('/cotizacion-items/{item}/personalizacion', [\App\Http\Controllers\Api\CotizacionItemPersonalizacionController::class, 'update']);
    Route::post('/cotizacion-items/{item}/personalizacion/upload', [\App\Http\Controllers\Api\CotizacionItemPersonalizacionController::class, 'uploadMatrizImage']);

    // Despachos
    Route::get('/despachos/kanban', [\App\Http\Controllers\DespachoController::class, 'getKanbanData']);
    Route::match(['patch', 'post'], '/despachos/cotizacion/{cotizacion_id}/estado', [\App\Http\Controllers\DespachoController::class, 'updateEstado']);
    Route::get('/despachos/cotizacion/{cotizacion_id}/guia', [\App\Http\Controllers\DespachoController::class, 'downloadGuiaPdf']);
});

// ==========================================
// MÓDULO MARKETPLACE
// ==========================================

// --- Login del Dealer (público) ---
Route::middleware('throttle:10,1')->post('/marketplace/login', [\App\Http\Controllers\Api\MarketplaceAuthController::class, 'login']);

// --- Rutas del Portal Dealer (protegidas por middleware marketplace) ---
Route::middleware(\App\Http\Middleware\MarketplaceAuth::class)->prefix('marketplace')->group(function () {
    Route::get('/me', [\App\Http\Controllers\Api\MarketplaceAuthController::class, 'me']);
    Route::post('/logout', [\App\Http\Controllers\Api\MarketplaceAuthController::class, 'logout']);
    Route::get('/cuerpos', [\App\Http\Controllers\CuerpoController::class, 'index']);
    Route::get('/catalog', [\App\Http\Controllers\Api\MarketplaceCatalogController::class, 'index']);
    Route::get('/catalog/categories', [\App\Http\Controllers\Api\MarketplaceCatalogController::class, 'categories']);
    Route::get('/catalog/{id}', [\App\Http\Controllers\Api\MarketplaceCatalogController::class, 'show']);
    Route::post('/quotes', [\App\Http\Controllers\Api\MarketplaceQuoteController::class, 'store']);
});

// --- Admin del Marketplace (protegidas por Sanctum) ---
Route::middleware('auth:sanctum')->prefix('admin/marketplace')->group(function () {
    // Marketplaces CRUD
    Route::post('/marketplaces/upload-asset', [\App\Http\Controllers\Api\MarketplaceAdminController::class, 'uploadAsset']);
    Route::apiResource('marketplaces', \App\Http\Controllers\Api\MarketplaceAdminController::class);
    Route::post('/marketplaces/{id}/sync-products', [\App\Http\Controllers\Api\MarketplaceAdminController::class, 'syncProducts']);

    // Productos
    Route::get('/products/export', [\App\Http\Controllers\Api\MktProductAdminController::class, 'exportExcel']);
    Route::post('/products/bulk-delete', [\App\Http\Controllers\Api\MktProductAdminController::class, 'bulkDestroy']);
    Route::get('/products/all-ids', [\App\Http\Controllers\Api\MktProductAdminController::class, 'allIds']);
    Route::apiResource('products', \App\Http\Controllers\Api\MktProductAdminController::class);
    Route::post('/products/import-excel', [\App\Http\Controllers\Api\MktProductAdminController::class, 'importExcel']);
    Route::post('/products/import-odoo-excel', [\App\Http\Controllers\Api\MktProductAdminController::class, 'importOdooExcel']);
    Route::post('/products/import-variants', [\App\Http\Controllers\Api\MktProductAdminController::class, 'importVariantsExcel']);
    Route::post('/products/upload-images-zip', [\App\Http\Controllers\Api\MktProductAdminController::class, 'uploadImagesZip']);
    Route::post('/products/upload-image', [\App\Http\Controllers\Api\MktProductAdminController::class, 'uploadSingleImage']);
    Route::post('/products/{id}/assign-images', [\App\Http\Controllers\Api\MktProductAdminController::class, 'assignImages']);

    // Categorías
    Route::apiResource('categories', \App\Http\Controllers\Api\MktCategoryAdminController::class);

    // Cotizaciones (lectura, gestión de estado y eliminación)
    Route::get('/quotes', [\App\Http\Controllers\Api\MarketplaceQuoteController::class, 'index']);
    Route::get('/quotes/{id}', [\App\Http\Controllers\Api\MarketplaceQuoteController::class, 'show']);
    Route::put('/quotes/{id}', [\App\Http\Controllers\Api\MarketplaceQuoteController::class, 'update']);
    Route::delete('/quotes/{id}', [\App\Http\Controllers\Api\MarketplaceQuoteController::class, 'destroy']);
    Route::post('/quotes/bulk-delete', [\App\Http\Controllers\Api\MarketplaceQuoteController::class, 'bulkDestroy']);
    Route::post('/quotes/bulk-status', [\App\Http\Controllers\Api\MarketplaceQuoteController::class, 'bulkStatus']);

    // Settings
    Route::get('/settings', [\App\Http\Controllers\Api\MktSettingsController::class, 'index']);
    Route::put('/settings', [\App\Http\Controllers\Api\MktSettingsController::class, 'update']);

    // Gestión de Usuarios de Marketplace (Bomberos / Clientes)
    Route::get('/users/template', [\App\Http\Controllers\Api\MktUserAdminController::class, 'downloadTemplate']);
    Route::get('/marketplaces/{id}/users', [\App\Http\Controllers\Api\MktUserAdminController::class, 'index']);
    Route::post('/marketplaces/{id}/users', [\App\Http\Controllers\Api\MktUserAdminController::class, 'store']);
    Route::post('/marketplaces/{id}/users/import', [\App\Http\Controllers\Api\MktUserAdminController::class, 'importExcel']);
    Route::get('/marketplaces/{id}/users/export', [\App\Http\Controllers\Api\MktUserAdminController::class, 'exportExcel']);
    Route::get('/users/{id}', [\App\Http\Controllers\Api\MktUserAdminController::class, 'show']);
    Route::put('/users/{id}', [\App\Http\Controllers\Api\MktUserAdminController::class, 'update']);
    Route::patch('/users/{id}/toggle-active', [\App\Http\Controllers\Api\MktUserAdminController::class, 'toggleActive']);
    Route::post('/users/{id}/reset-password', [\App\Http\Controllers\Api\MktUserAdminController::class, 'resetPassword']);
    Route::delete('/users/{id}', [\App\Http\Controllers\Api\MktUserAdminController::class, 'destroy']);
});

// ── Proxy de archivos de storage (CORS habilitado para PDF export) ────────
// php artisan serve sirve /storage/ como estáticos SIN headers CORS.
// Esta ruta pasa por el middleware CORS de Laravel → permite fetch() desde Next.js.
Route::get('/storage-proxy/{path}', function (string $path) {
    $path = str_replace(['\\', "\0"], ['/', ''], $path);
    if (str_contains($path, '../') || str_starts_with($path, '/')) {
        abort(403, 'Invalid path');
    }
    
    $disk = \Illuminate\Support\Facades\Storage::disk('public');
    if (!$disk->exists($path)) {
        abort(404);
    }
    
    return response()->file($disk->path($path), [
        'Access-Control-Allow-Origin' => '*',
        'Cache-Control' => 'public, max-age=86400',
    ]);
})->where('path', '.*');
