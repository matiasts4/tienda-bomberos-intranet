<?php

namespace App\Http\Controllers;

use App\Models\PteSku;
use App\Models\PteStock;
use App\Models\PteMovimiento; // <--- VITAL PARA EL HISTORIAL
use App\Models\PteSkuGenerico; // <--- Para el puente con el módulo comercial
use App\Services\ProductCatalogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth; // <--- VITAL PARA SABER QUIÉN FUE
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str; // <--- AGREGADO: Para generar UUIDs
use Exception;
use Throwable;

class SkuController extends Controller
{
    public function __construct(private readonly ProductCatalogService $catalogService)
    {
    }

    /**
     * Listar todos los productos con sus relaciones (JSON puro de la BD).
     */
    public function index()
    {
        $productos = PteSku::with([
            'skuGenerico.unidadNegocio',
            'skuGenerico.origen',
            'skuGenerico.grpFamilia',
            'skuGenerico.familia',
            'skuGenerico.subfamilia',
            'skuGenerico.familiaTipo',
            'skuGenerico.familiaFormato',
            'skuGenerico.genero',
            'color',
            'talla',
            'stock'
        ])->get();

        return response()->json($productos);
    }

    /**
     * Crear un nuevo producto (Manual).
     *
     * @deprecated Este endpoint es legacy. Utilice POST /api/sku-genericos para crear plantillas base.
     */
    public function store(Request $request)
    {
        Log::info('Legacy endpoint POST /api/productos invocado.', [
            'user_id' => Auth::id(),
            'sku' => $request->sku,
        ]);

        // 1. Validaciones básicas de formulario
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:150',
            'unidad_negocio_id' => 'required',
            // ... resto de validaciones si necesitas ...
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // Reject missing input before opening a transaction: returning inside it
        // left the connection with an active transaction on this validation path.
        $skuGenerado = $request->sku;
        if (!$skuGenerado) {
            return response()->json(['message' => 'El SKU es requerido'], 400);
        }

        DB::beginTransaction();

        try {

            // -------------------------------------------------------
            // --- VALIDACIÓN DE DUPLICADOS (NUEVO) ---
            // -------------------------------------------------------
            if (PteSku::where('sku', $skuGenerado)->exists()) {
                DB::rollBack();
                return response()->json([
                    'message' => "¡Atención! El SKU {$skuGenerado} ya existe en el inventario. No se pueden crear duplicados."
                ], 422);
            }
            // -------------------------------------------------------

            // B. Crear el Producto (SKU)
            $generoModel = \App\Models\PteGenero::find($request->genero_id);
            $genreName = ($generoModel && !in_array($generoModel->nombre, ['Generico', 'Unisex'])) ? $generoModel->nombre : '';
            
            $finalName = trim($request->nombre);
            if ($genreName && !Str::endsWith($finalName, $genreName)) {
                $finalName .= ' ' . $genreName;
            }

            // B2. Auto-crear el SKU Genérico si no existe (puente con módulo Comercial)
            $skuBase = substr($skuGenerado, 0, 11) . '0000'; // 10 dígitos base + género + 0000
            $generico = PteSkuGenerico::firstOrCreate(
                ['sku_base' => $skuBase],
                [
                    'nombre'               => $finalName,
                    'precio_venta'         => $request->precio_venta,
                    'imagen_url'           => null,
                    'unidad_negocio_id'    => $request->unidad_negocio_id,
                    'origen_id'            => $request->origen_id,
                    'grp_familia_id'       => $request->grp_familia_id,
                    'familia_id'           => $request->familia_id,
                    'subfamilia_id'        => $request->subfamilia_id,
                    'familia_tipo_id'      => $request->familia_tipo_id,
                    'familia_formato_id'   => $request->familia_formato_id,
                    'genero_id'            => $request->genero_id,
                    'activo'               => true,
                    'permite_personalizar' => filter_var($request->input('permite_personalizar', true), FILTER_VALIDATE_BOOLEAN),
                ]
            );

            if (!$generico->activo) {
                DB::rollBack();
                return response()->json([
                    'message' => 'El producto base está inactivo. Reactívalo desde el Catálogo antes de crear variantes.'
                ], 409);
            }

            if ($generico->modo_kit) {
                DB::rollBack();
                return response()->json(['message' => 'El kit comercial no tiene variantes físicas propias. Crea las variantes en sus productos componentes.'], 422);
            }

            $producto = new PteSku();
            $producto->sku = $skuGenerado;
            $producto->sku_generico_id = $generico->id;
            $producto->nombre = $finalName;
            $producto->descripcion = $request->descripcion;
            $producto->precio_venta = $request->precio_venta;
            $producto->color_id = $request->color_id;
            $producto->talla_id = $request->talla_id;

            $producto->save();

            // C. Crear el Stock Inicial
            PteStock::create([
                'sku' => $skuGenerado,
                'cantidad' => $request->cantidad ?? 0,
                'stock_critico' => $request->stock_critico ?? 5
            ]);

            // D. Registrar movimiento de "Inventario Inicial"
            if ($request->cantidad > 0) {
                PteMovimiento::create([
                    'sku' => $skuGenerado,
                    'fecha_hora' => now(),
                    'tipo_movimiento' => 'INVENTARIO_INICIAL',
                    'usuario_id' => Auth::id(),
                    'cantidad' => $request->cantidad,
                    'saldo_anterior' => 0,
                    'saldo_nuevo' => $request->cantidad,
                    // Generamos un ID de lote único también para la creación inicial
                    'id_lote' => (string) Str::uuid()
                ]);
            }

            DB::commit();
            return response()->json(['message' => 'Producto creado con éxito', 'data' => $producto], 201)
                ->header('X-Deprecated', 'Endpoint legacy. Utilice POST /api/sku-genericos.');

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('No se pudo crear el producto PTE.', ['exception' => $e]);
            return response()->json(['message' => 'No se pudo crear el producto. Inténtalo nuevamente.'], 500);
        }
    }

    /**
     * Crear productos en masa (Multi-color, Multi-talla, Multi-género).
     * Recibe los atributos base + arrays de IDs y genera todas las combinaciones.
     *
     * @deprecated Este endpoint es legacy. Utilice POST /api/sku-genericos para plantillas y POST /api/sku-genericos/{id}/add-variants para variantes.
     */
    public function storeBulk(Request $request)
    {
        Log::info('Legacy endpoint POST /api/productos/bulk invocado.', [
            'user_id' => Auth::id(),
            'nombre' => $request->nombre,
        ]);

        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:150',
            'precio_venta' => 'required|numeric',
            'colores' => 'required|array|min:1',
            'colores.*' => 'integer',
            'tallas' => 'required|array|min:1',
            'tallas.*' => 'integer',
            'generos' => 'required|array|min:1',
            'generos.*' => 'integer',
            'unidad_negocio_id' => 'required|integer',
            'origen_id' => 'required|integer',
            'grp_familia_id' => 'required|integer',
            'familia_id' => 'required|integer',
            'subfamilia_id' => 'required|integer',
            'familia_tipo_id' => 'required|integer',
            'familia_formato_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            // Construir prefijo del SKU (10 dígitos base)
            $u  = str_pad($request->unidad_negocio_id, 1, '0', STR_PAD_LEFT);
            $o  = str_pad($request->origen_id, 1, '0', STR_PAD_LEFT);
            $g  = str_pad($request->grp_familia_id, 1, '0', STR_PAD_LEFT);
            $f  = str_pad($request->familia_id, 1, '0', STR_PAD_LEFT);
            $sf = str_pad($request->subfamilia_id, 2, '0', STR_PAD_LEFT);
            $ti = str_pad($request->familia_tipo_id, 2, '0', STR_PAD_LEFT);
            $ffVal = (int) $request->familia_formato_id;
            $ff = str_pad($ffVal, 2, '0', STR_PAD_LEFT);

            $skuPrefix = "{$u}{$o}{$g}{$f}{$sf}{$ti}{$ff}";
            $stockCritico = $request->stock_critico ?? 5;

            // --- VALIDACIÓN DE PRODUCTO EXISTENTE (NUEVO) ---
            // Si ya existe un genérico con esta base + el primer género solicitado,
            // asumimos que el "Producto" ya fue creado y debe gestionarse desde el catálogo.
            // Para ser precisos, chequeamos si existe el registro base en PteSkuGenerico.
            foreach ($request->generos as $generoId) {
                $skuBase = $skuPrefix . str_pad($generoId, 1, '0', STR_PAD_LEFT) . '0000';
                if (PteSkuGenerico::where('sku_base', $skuBase)->exists()) {
                    DB::rollBack();
                    return response()->json([
                        'message' => "¡Atención! El producto '{$request->nombre}' ya existe en el Catálogo Comercial. No se pueden crear duplicados desde aquí. Por favor, usa el Catálogo para añadir nuevas tallas o colores a este producto."
                    ], 422);
                }
            }

            $creados = 0;
            $duplicados = 0;

            foreach ($request->generos as $generoId) {
                $genStr = str_pad($generoId, 1, '0', STR_PAD_LEFT);

                // Obtener nombre del género para anexarlo al nombre del producto
                $generoModel = \App\Models\PteGenero::find($generoId);
                $genreName = ($generoModel && !in_array($generoModel->nombre, ['Generico', 'Unisex'])) ? $generoModel->nombre : '';
                
                $finalName = trim($request->nombre);
                if ($genreName && !Str::endsWith($finalName, $genreName)) {
                    $finalName .= ' ' . $genreName;
                }

                // Auto-crear genérico para cada género
                $skuBase = $skuPrefix . $genStr . '0000';
                $generico = PteSkuGenerico::firstOrCreate(
                    ['sku_base' => $skuBase],
                    [
                        'nombre'               => $finalName,
                        'precio_venta'         => $request->precio_venta,
                        'imagen_url'           => null,
                        'unidad_negocio_id'    => $request->unidad_negocio_id,
                        'origen_id'            => $request->origen_id,
                        'grp_familia_id'       => $request->grp_familia_id,
                        'familia_id'           => $request->familia_id,
                        'subfamilia_id'        => $request->subfamilia_id,
                        'familia_tipo_id'      => $request->familia_tipo_id,
                        'familia_formato_id'   => $request->familia_formato_id,
                        'genero_id'            => $generoId,
                        'activo'               => true,
                        'permite_personalizar' => filter_var($request->input('permite_personalizar', true), FILTER_VALIDATE_BOOLEAN),
                    ]
                );

                foreach ($request->colores as $colorId) {
                    $colorName = \App\Models\PteColor::find($colorId)->nombre ?? '';
                    foreach ($request->tallas as $tallaId) {
                        $colStr = str_pad($colorId, 2, '0', STR_PAD_LEFT);
                        $taStr  = str_pad($tallaId, 2, '0', STR_PAD_LEFT);
                        $skuFinal = $skuPrefix . $genStr . $colStr . $taStr;

                        // Saltar duplicados silenciosamente
                        if (PteSku::where('sku', $skuFinal)->exists()) {
                            $duplicados++;
                            continue;
                        }

                        $tallaName = \App\Models\PteTalla::find($tallaId)->nombre ?? '';
                        $variantName = trim($finalName . ' ' . $colorName);
                        if ($tallaName && strtolower($tallaName) !== 'generico') {
                            $variantName .= ' ' . $tallaName;
                        }

                        PteSku::create([
                            'sku' => $skuFinal,
                            'sku_generico_id' => $generico->id,
                            'nombre' => $variantName,
                            'descripcion' => $request->descripcion,
                            'precio_venta' => $request->precio_venta,
                            'color_id' => $colorId,
                            'talla_id' => $tallaId,
                        ]);

                        PteStock::create([
                            'sku' => $skuFinal,
                            'cantidad' => 0,
                            'stock_critico' => $stockCritico,
                        ]);

                        $creados++;
                    }
                }
            }

            DB::commit();
            return response()->json([
                'message' => "Generación masiva completada",
                'resumen' => [
                    'creados' => $creados,
                    'duplicados' => $duplicados,
                ]
            ], 201)->header('X-Deprecated', 'Endpoint legacy. Utilice POST /api/sku-genericos.');

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('No se pudo completar la generación masiva de productos PTE.', ['exception' => $e]);
            return response()->json(['message' => 'No se pudo completar la generación masiva. Inténtalo nuevamente.'], 500);
        }
    }

    /**
     * Ver un producto específico.
     */
    public function show($sku)
    {
        $producto = PteSku::with(['stock'])->where('sku', $sku)->first();

        if (!$producto) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }

        return response()->json($producto);
    }

    /**
     * ACTUALIZAR PRODUCTO
     * Aquí está la lógica del historial de ajustes manuales e ID de Lote.
     *
     * Solo se modifican los campos enviados. `cantidad` fija el disponible y
     * `cantidad_anterior` es el valor que el usuario veía en pantalla: si el
     * stock cambió desde entonces (una reserva, un rechazo, un ingreso) se
     * responde 409 en vez de sobrescribirlo y perder ese movimiento.
     */
    public function update(Request $request, $id)
    {
        // Nota: $id en la ruta es el SKU string
        $sku = $id;

        $producto = PteSku::where('sku', $sku)->first();

        if (!$producto) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }

        if (($request->has('cantidad') || $request->has('stock_critico')) && $producto->skuGenerico?->modo_kit) {
            return response()->json(['message' => 'El stock del kit pertenece a sus productos hijos. Ajusta sus variantes físicas.'], 422);
        }

        $data = $request->validate([
            'nombre' => 'sometimes|required|string|max:150',
            'precio_venta' => 'sometimes|nullable|numeric|min:0',
            'descripcion' => 'sometimes|nullable|string',
            'cantidad' => 'sometimes|required|integer|min:0',
            'cantidad_anterior' => 'sometimes|nullable|integer',
            'stock_critico' => 'sometimes|nullable|integer|min:0',
            'id_lote' => 'sometimes|nullable|string|max:64',
        ]);

        if (array_key_exists('cantidad', $data) && !isset($data['cantidad_anterior'])) {
            // Clientes antiguos: se acepta, pero queda registro para retirarlo.
            Log::warning('PUT /api/productos sin cantidad_anterior: no se puede detectar un stock desactualizado.', [
                'sku' => $sku,
                'user_id' => Auth::id(),
            ]);
        }

        DB::beginTransaction();

        try {
            // Bloquea el stock hasta el commit: ninguna reserva puede colarse
            // entre la comparación y la escritura.
            $stockModel = PteStock::where('sku', $sku)->lockForUpdate()->first();
            $stockAnterior = $stockModel ? (int) $stockModel->cantidad : 0;

            if (array_key_exists('cantidad', $data)) {
                if (isset($data['cantidad_anterior']) && (int) $data['cantidad_anterior'] !== $stockAnterior) {
                    DB::rollBack();
                    return response()->json([
                        'message' => "El stock de {$sku} cambió mientras lo editabas (ahora hay {$stockAnterior} disponibles). Revisa el valor y vuelve a guardar.",
                        'sku' => $sku,
                        'cantidad_actual' => $stockAnterior,
                        'reserved_stock' => $stockModel ? (int) $stockModel->reserved_stock : 0,
                    ], 409);
                }

                $nuevoStock = (int) $data['cantidad'];

                // 1. DETECCIÓN DE CAMBIO DE STOCK (HISTORIAL)
                if ($stockAnterior !== $nuevoStock) {
                    PteMovimiento::create([
                        'sku' => $sku,
                        'fecha_hora' => now(),
                        'tipo_movimiento' => 'AJUSTE_MANUAL',
                        'usuario_id' => Auth::id(),
                        'cantidad' => abs($nuevoStock - $stockAnterior),
                        'saldo_anterior' => $stockAnterior,
                        'saldo_nuevo' => $nuevoStock,

                        // --- AQUÍ ESTÁ LA MAGIA DEL BATCH ID ---
                        // Recibe el ID del frontend (bulk) o genera uno nuevo (individual)
                        'id_lote' => $data['id_lote'] ?? (string) Str::uuid(),
                    ]);
                }
            }

            // 2. Actualizar datos del SKU solo con lo enviado
            $camposSku = array_intersect_key($data, array_flip(['nombre', 'precio_venta', 'descripcion']));
            if (array_key_exists('precio_venta', $camposSku) && $camposSku['precio_venta'] !== null) {
                $camposSku['precio_venta'] = (int) round((float) $camposSku['precio_venta']);
            }
            if ($camposSku) {
                $producto->update($camposSku);
            }

            // 3. Actualizar la tabla de Stocks
            $camposStock = [];
            if (array_key_exists('cantidad', $data)) {
                $camposStock['cantidad'] = (int) $data['cantidad'];
            }
            if (isset($data['stock_critico'])) {
                $camposStock['stock_critico'] = (int) $data['stock_critico'];
            }

            if ($stockModel) {
                if ($camposStock) {
                    $stockModel->update($camposStock);
                }
            } else {
                PteStock::create(array_merge(['sku' => $sku, 'cantidad' => 0], $camposStock));
            }

            DB::commit();
            return response()->json(['message' => 'Producto actualizado correctamente']);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('No se pudo actualizar el producto PTE.', [
                'sku' => $sku,
                'exception' => $e,
            ]);
            return response()->json(['message' => 'No se pudo actualizar el producto. Inténtalo nuevamente.'], 500);
        }
    }

    /**
     * Compatibilidad con clientes antiguos: DELETE archiva, nunca borra.
     */
    public function destroy($id)
    {
        try {
            $producto = $this->catalogService->archiveVariant((string) $id);
            return response()->json([
                'message' => "Producto '{$producto->nombre}' archivado. El historial y stock fueron preservados.",
                'archived' => true,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        } catch (Throwable $exception) {
            Log::error('No se pudo archivar la variante PTE.', ['sku' => $id, 'exception' => $exception]);
            return response()->json(['message' => 'No se pudo archivar el producto. Inténtalo nuevamente.'], 500);
        }
    }

    /**
     * Desactivar un producto (Soft-delete lógico).
     * Preserva el historial completo, solo lo oculta del inventario activo.
     */
    public function deactivate($id)
    {
        try {
            $producto = $this->catalogService->archiveVariant((string) $id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        } catch (Throwable $exception) {
            Log::error('No se pudo desactivar la variante PTE.', ['sku' => $id, 'exception' => $exception]);
            return response()->json(['message' => 'No se pudo archivar el producto. Inténtalo nuevamente.'], 500);
        }
        return response()->json([
            'message' => "Producto '{$producto->nombre}' archivado. El historial y stock fueron preservados.",
            'archived' => true,
        ]);
    }

    /**
     * Endpoint especial para la Tabla de Frontend (formato aplanado).
     */
    /**
     * Endpoint especial para la Tabla de Frontend (formato aplanado).
     * Soporta Paginación, Búsqueda y Ordenamiento.
     */
    public function inventario(Request $request)
    {
        $bodegaNombre = "Bodega Central PTE";
        $perPage = $request->input('per_page', 20);
        $sortBy = $request->input('sort_by', 'producto'); // Default sort by Product + Size
        $order = $request->input('order', 'asc');

        // Consulta, filtros y orden compartidos con el reporte de exportación.
        $query = $this->inventoryQuery()
            ->with(['stock', 'skuGenerico.subfamilia', 'skuGenerico.genero', 'talla', 'color']);
        $this->applyInventoryFilters($query, $request);
        $this->applyInventorySort($query, $sortBy, $order);

        // 3. Ejecutar Paginación
        $paginator = $query->paginate($perPage);

        // 5. Calcular Stats Globales (solo productos ACTIVOS y no archivados)
        $activeSkuScope = function ($q) {
            $q->where('activo', true)
              ->where(function ($sub) {
                  $sub->whereDoesntHave('skuGenerico')
                      ->orWhereHas('skuGenerico', function ($g) {
                          $g->where('estado_catalogo', '!=', 'archivado')
                            ->where('modo_kit', false)
                            ->where('activo', true);
                      });
              });
        };

        $totalSkus = PteSku::where($activeSkuScope)->count();
        $agotados = PteStock::whereHas('skuProducto', $activeSkuScope)
            ->where('cantidad', '<=', 0)->count();
        $criticos = PteStock::whereHas('skuProducto', $activeSkuScope)
            ->whereRaw('cantidad <= stock_critico')->where('cantidad', '>', 0)->count();
        $bajoStock = PteStock::whereHas('skuProducto', $activeSkuScope)
            ->whereRaw('cantidad <= (stock_critico * 2)')->whereRaw('cantidad > stock_critico')->count();

        $stats = [
            'total_skus' => $totalSkus,
            'agotados' => $agotados,
            'criticos' => $criticos,
            'bajo_stock' => $bajoStock
        ];

        // 6. Transformar los datos (Aplanar estructura)
        $itemsTransformados = $paginator->getCollection()->map(function ($producto) use ($bodegaNombre) {

            $cantidad = $producto->stock ? $producto->stock->cantidad : 0;
            $reservado = $producto->stock ? $producto->stock->reserved_stock : 0;
            $critico = $producto->stock ? $producto->stock->stock_critico : 5;

            return [
                'sku' => $producto->sku,
                'producto' => $producto->nombre,
                'sku_generico_id' => $producto->sku_generico_id,
                'nombre_generico' => $producto->skuGenerico?->nombre,
                'precio_venta' => $producto->precio_venta ?? 0,
                'genero' => $producto->skuGenerico?->genero?->nombre ?? 'Unisex',
                'categoria' => $producto->skuGenerico?->subfamilia?->nombre ?? 'General',
                'talla' => $producto->talla ? $producto->talla->nombre : '-',
                'color' => $producto->color ? $producto->color->nombre : '-',
                'bodega' => $bodegaNombre,
                'cantidad_fisica' => $cantidad,
                'reserved_stock' => $reservado,
                'stock_critico' => $critico,
                'estado' => $cantidad > 0 ? 'Disponible' : 'Sin Stock'
            ];
        });

        // 7. Retornar respuesta custom
        // Convertimos el paginador a array para tener la estructura standard de Laravel (current_page, data, etc.)
        // Y le adjuntamos nuestros stats custom.
        $paginationData = $paginator->toArray();
        $paginationData['data'] = $itemsTransformados; // Reemplazamos la data cruda con la transformada

        return response()->json([
            'pagination' => $paginationData,
            'stats' => $stats
        ]);
    }

    public function reporteInventario(Request $request)
    {
        $bodegaNombre = "Bodega Central PTE";

        // La exportación usa exactamente la misma búsqueda, filtros y orden que la pantalla.
        $query = $this->inventoryQuery()
            ->with([
                'stock:sku,cantidad,reserved_stock,stock_critico',
                'skuGenerico:id,nombre,subfamilia_id,genero_id',
                'skuGenerico.subfamilia:id,nombre',
                'skuGenerico.genero:id,nombre',
                'talla:id,nombre,orden',
                'color:id,nombre',
            ]);
        $this->applyInventoryFilters($query, $request);
        $this->applyInventorySort($query, $request->input('sort_by', 'producto'), $request->input('order', 'asc'));

        $items = $query->get()->map(function ($producto) use ($bodegaNombre) {
            $cantidad = $producto->stock ? $producto->stock->cantidad : 0;
            $reservado = $producto->stock ? $producto->stock->reserved_stock : 0;
            $critico = $producto->stock ? $producto->stock->stock_critico : 5;
            $talla = $producto->talla?->nombre;

            return [
                'sku' => $producto->sku,
                'producto' => $producto->nombre,
                'prd' => self::nombreSinTalla((string) $producto->nombre, $talla),
                'precio_venta' => $producto->precio_venta ?? 0,
                'genero' => $producto->skuGenerico?->genero?->nombre ?? 'Unisex',
                'plantilla' => $producto->skuGenerico?->nombre,
                'categoria' => $producto->skuGenerico?->subfamilia?->nombre ?? 'General',
                'talla' => $talla ?? '-',
                'num_talla' => self::numeroTalla($talla, $producto->talla?->orden),
                'color' => $producto->color ? $producto->color->nombre : '-',
                'bodega' => $bodegaNombre,
                'cantidad_fisica' => $cantidad,
                'reserved_stock' => $reservado,
                'fisico' => (int) $cantidad + (int) $reservado,
                'stock_critico' => $critico,
                'estado' => $cantidad > 0 ? 'Disponible' : 'Sin Stock'
            ];
        });

        return response()->json($items);
    }

    /** SKUs activos de plantillas no archivadas, con los joins que usan búsqueda, filtros y orden. */
    private function inventoryQuery()
    {
        return PteSku::query()
            ->select('pte_skus.*') // Importante para no sobreescribir IDs
            ->where('pte_skus.activo', true)
            ->leftJoin('pte_stocks', 'pte_skus.sku', '=', 'pte_stocks.sku')
            ->leftJoin('pte_tallas', 'pte_skus.talla_id', '=', 'pte_tallas.id')
            ->leftJoin('pte_colores', 'pte_skus.color_id', '=', 'pte_colores.id')
            ->leftJoin('pte_sku_genericos', 'pte_skus.sku_generico_id', '=', 'pte_sku_genericos.id')
            ->leftJoin('pte_subfamilias', 'pte_sku_genericos.subfamilia_id', '=', 'pte_subfamilias.id')
            ->leftJoin('pte_generos', 'pte_sku_genericos.genero_id', '=', 'pte_generos.id')
            ->where(function ($q) {
                $q->whereNull('pte_sku_genericos.modo_kit')->orWhere('pte_sku_genericos.modo_kit', false);
            })
            ->where(function ($q) {
                $q->whereNull('pte_sku_genericos.estado_catalogo')
                  ->orWhere('pte_sku_genericos.estado_catalogo', '!=', 'archivado');
            })
            ->where(function ($q) {
                $q->whereNull('pte_sku_genericos.activo')
                  ->orWhere('pte_sku_genericos.activo', true);
            });
    }

    private function applyInventoryFilters($query, Request $request): void
    {
        $this->applySmartInventorySearch($query, $request->input('search'));

        if ($request->filled('sku_generico_id')) {
            $query->where('pte_skus.sku_generico_id', $request->integer('sku_generico_id'));
        }

        $tallaFilter = $request->input('talla') ?? $request->input('talla_id');
        if ($tallaFilter && $tallaFilter !== 'todas' && $tallaFilter !== 'all') {
            if (is_numeric($tallaFilter)) {
                $query->where('pte_skus.talla_id', (int) $tallaFilter);
            } else {
                $query->where('pte_tallas.nombre', $tallaFilter);
            }
        }

        switch ($request->input('stock_filter')) {
            case 'agotados':
                $query->where('pte_stocks.cantidad', '<=', 0);
                break;
            case 'criticos':
                $query->whereRaw('pte_stocks.cantidad <= pte_stocks.stock_critico')
                    ->where('pte_stocks.cantidad', '>', 0);
                break;
            case 'bajo_stock':
                $query->whereRaw('pte_stocks.cantidad <= (pte_stocks.stock_critico * 2)')
                    ->whereRaw('pte_stocks.cantidad > pte_stocks.stock_critico');
                break;
        }
    }

    private function applyInventorySort($query, ?string $sortBy, ?string $order): void
    {
        $order = strtolower((string) $order) === 'desc' ? 'desc' : 'asc';

        switch ($sortBy) {
            case 'cantidad_fisica':
                $query->orderBy('pte_stocks.cantidad', $order);
                break;
            case 'reserved_stock':
                $query->orderByRaw("COALESCE(pte_stocks.reserved_stock, 0) {$order}");
                break;
            case 'talla':
                $query->orderBy('pte_tallas.orden', $order)
                    ->orderBy('pte_sku_genericos.nombre', 'asc')
                    ->orderBy('pte_colores.nombre', 'asc');
                break;
            case 'color':
                $query->orderBy('pte_colores.nombre', $order)
                    ->orderBy('pte_tallas.orden', 'asc');
                break;
            case 'precio_venta':
                $query->orderBy('pte_skus.precio_venta', $order);
                break;
            case 'producto':
                $query->orderBy('pte_sku_genericos.nombre', $order)
                    ->orderBy('pte_colores.nombre', 'asc')
                    ->orderBy('pte_tallas.orden', 'asc');
                break;
            default:
                $query->orderBy('pte_skus.sku', $order);
                break;
        }
    }

    /**
     * Nombre del producto sin la talla, para agrupar en tablas dinámicas:
     * "Chaqueta Pilot Unisex Azul Marino XXS" → "Chaqueta Pilot Unisex Azul Marino" y
     * "Geologo ... Azulino Ajustable 1 (XS a M)" → "Geologo ... Azulino".
     * Si la talla no está al final (Única, Genérico) se deja el nombre completo.
     */
    public static function nombreSinTalla(string $nombre, ?string $talla): string
    {
        $nombre = trim($nombre);
        $talla = trim((string) $talla);
        if ($talla === '') {
            return $nombre;
        }

        $sinTalla = preg_replace('/\s+' . preg_quote($talla, '/') . '(\s*\([^)]*\))?\s*$/iu', '', $nombre);

        return ($sinTalla === null || trim($sinTalla) === '') ? $nombre : trim($sinTalla);
    }

    /** "02_XXS": orden de la talla con dos dígitos para que Excel ordene bien. */
    public static function numeroTalla(?string $talla, $orden): string
    {
        $talla = trim((string) $talla);
        if ($talla === '') {
            return '';
        }

        return $orden === null ? $talla : sprintf('%02d_%s', (int) $orden, $talla);
    }

    /**
     * Aplica una búsqueda tolerante al lenguaje de bodega.
     *
     * Cada término debe aparecer en alguno de los campos buscables, pero puede
     * hacerlo en cualquier orden y separado por otras palabras. Las equivalencias
     * son deliberadamente pequeñas para no mezclar atributos distintos (MC != ML).
     */
    private function applySmartInventorySearch($query, ?string $search): void
    {
        $terms = $this->inventorySearchTerms($search);

        foreach ($terms as $term) {
            $alternatives = match ($term) {
                'mc' => ['mc', 'manga corta'],
                'ml' => ['ml', 'manga larga'],
                'mujer' => ['mujer', 'dama'],
                'hombre' => ['hombre', 'varon'],
                default => [$term],
            };

            $query->where(function ($termQuery) use ($alternatives) {
                foreach ($alternatives as $alternative) {
                    $like = '%' . $alternative . '%';

                    $termQuery->orWhere('pte_skus.sku', 'like', $like)
                        ->orWhere('pte_skus.nombre', 'like', $like)
                        ->orWhere('pte_sku_genericos.nombre', 'like', $like)
                        ->orWhere('pte_colores.nombre', 'like', $like)
                        ->orWhere('pte_tallas.nombre', 'like', $like)
                        ->orWhere('pte_subfamilias.nombre', 'like', $like)
                        ->orWhere('pte_generos.nombre', 'like', $like);
                }
            });
        }
    }

    /** @return array<int, string> */
    private function inventorySearchTerms(?string $search): array
    {
        if (!is_string($search) || trim($search) === '') {
            return [];
        }

        $normalized = Str::lower(Str::ascii($search));
        $normalized = preg_replace('/\bmanga\s+corta\b/u', 'mc', $normalized);
        $normalized = preg_replace('/\bmanga\s+larga\b/u', 'ml', $normalized);
        $normalized = preg_replace('/\bm\s*[\/.-]\s*c\b/u', 'mc', $normalized);
        $normalized = preg_replace('/\bm\s*[\/.-]\s*l\b/u', 'ml', $normalized);
        $normalized = preg_replace('/[^a-z0-9]+/u', ' ', $normalized);

        $aliases = [
            'dama' => 'mujer',
            'varon' => 'hombre',
        ];

        return collect(explode(' ', trim((string) $normalized)))
            ->filter()
            ->map(fn(string $term) => $aliases[$term] ?? $term)
            ->unique()
            ->values()
            ->all();
    }
}
