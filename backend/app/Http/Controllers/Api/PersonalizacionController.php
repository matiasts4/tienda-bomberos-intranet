<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Personalizacion;
use App\Models\PteMovimiento;
use App\Models\Cotizacion;
use App\Models\Despacho;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PersonalizacionController extends Controller
{
    /**
     * Listar todas las personalizaciones (para el Kanban).
     *
     * Query params:
     *  - estado      : filtrar por estado
     *  - search      : texto libre (sku / nombre)
     *  - per_page    : tarjetas por página (default 100, max 200)
     *  - page        : página actual (default 1)
     *  - slim        : 1 = omite configuracion.layers del listado (recomendado para Kanban)
     */
    public function index(Request $request)
    {
        // ── 1. Sólo las columnas necesarias para las tarjetas Kanban ─────────
        $select = [
            'id',
            'movimiento_inventario_id',
            'cotizacion_id',
            'usuario_creador_id',
            'sku',
            'nombre_producto',
            'cantidad',
            'estado',
            'tipo',
            'siguiente_proceso',
            'proceso_previo',
            'numero_item',
            'prioridad',
            'notas',
            'fecha_inicio_proceso',
            'fecha_inicio_qc',
            'personalizacion_padre_id',
            'bloqueado_qc',
            'ciclos_qc',
            'operario_asignado_id',
            'created_at',
            'updated_at',
        ];

        // ── 2. Eager loading reducido: sólo lo imprescindible para el Kanban ─
        $query = Personalizacion::select($select)
            ->with([
                'usuarioCreador:id,name',
                'operarioAsignado:id,name',
                'archivos',
                'variantes',
                'cotizacion:id,numero,cliente_id,cuerpo_id,compania_id,user_id',
                'cotizacion.cliente:id,nombre_empresa,correo,parent_id',
                'cotizacion.cuerpo:id,nombre',
                'cotizacion.compania:id,nombre,cuerpo_id',
                'cotizacion.compania.cuerpo:id,nombre',
                'movimientoInventario:id,cotizacion_id,sku,tipo_movimiento',
                'movimientoInventario.skuProducto:sku,nombre,sku_generico_id,color_id,talla_id',
                'movimientoInventario.skuProducto.color:id,nombre',
                'movimientoInventario.skuProducto.talla:id,nombre',
                'movimientoInventario.skuProducto.skuGenerico:id,familia_id,subfamilia_id,genero_id',
                'movimientoInventario.skuProducto.skuGenerico.familia:id,nombre',
                'movimientoInventario.skuProducto.skuGenerico.subfamilia:id,nombre',
                'movimientoInventario.skuProducto.skuGenerico.genero:id,nombre',
            ])
            ->orderBy('created_at', 'desc')->orderBy('id', 'desc');

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('proceso_tipo')) {
            $proceso = $request->proceso_tipo;
            // Map process type if needed
            $procesoMap = [
                'embroidery' => 'bordado',
                'print' => 'estampado',
            ];
            $proceso = $procesoMap[$proceso] ?? $proceso;

            // For workshop modules (bordado/estampado), filter by specific tipo OR mixed AND workshop states
            $query->where(function ($q) use ($proceso) {
                $q->where('tipo', $proceso)
                    ->orWhere('tipo', 'mixto');
            })
                ->whereIn('estado', ['taller_por_hacer', 'taller_en_proceso', 'control_calidad', 'taller_completado', 'completado']);
        } else {
            // For the hub (no proceso_tipo), show only the design-phase states and the completed/manual ones.
            $query->whereIn('estado', ['pendiente_diseno', 'disenando', 'aprobacion', 'en_correccion', 'manual', 'completado']);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('sku', 'like', "%{$search}%")
                    ->orWhere('nombre_producto', 'like', "%{$search}%");
            });
        }

        // ── 3. Paginación ────────────────────────────────────────────────────
        $perPage = max(1, min((int) $request->get('per_page', 100), 200));
        $paginated = $query->paginate($perPage);

        // ── 4. Pre-fetch DetalleCotizacion en batch (elimina N+1) ────────────
        //   Sólo para los registros que no tienen talla via movimientoInventario
        $cotizacionIds = $paginated->getCollection()
            ->whereNull('movimientoInventario')        // sin movimiento → via cotización
            ->pluck('cotizacion_id')
            ->filter()
            ->unique()
            ->values();

        $tallasMap = [];
        if ($cotizacionIds->isNotEmpty()) {
            $tallasMap = \App\Models\DetalleCotizacion::whereIn('cotizacion_id', $cotizacionIds)
                ->whereIn('sku', $paginated->getCollection()->pluck('sku')->unique())
                ->get(['cotizacion_id', 'sku', 'talla'])
                ->mapWithKeys(fn($d) => ["{$d->cotizacion_id}_{$d->sku}" => $d->talla])
                ->toArray();
        }

        $isSlim = $request->boolean('slim', true); // slim=true por omisión para el Kanban

        $colorMapById = \App\Models\PteColor::pluck('nombre', 'id')->toArray();
        $colorIdByName = array_flip(array_map('strtolower', $colorMapById));
        $tallaMapById = \App\Models\PteTalla::pluck('nombre', 'id')->toArray();
        $tallaIdByName = array_flip(array_map('strtolower', $tallaMapById));

        $genericSkus = $paginated->getCollection()
            ->pluck('sku')
            ->filter(fn($sku) => is_string($sku) && strlen($sku) >= 10)
            ->map(fn($sku) => $this->toGenericSku($sku))
            ->filter()
            ->unique()
            ->values();

        $genericSkuMap = [];
        if ($genericSkus->isNotEmpty()) {
            $genericSkuMap = \App\Models\PteSkuGenerico::whereIn('sku_base', $genericSkus)
                ->with(['familia:id,nombre', 'subfamilia:id,nombre'])
                ->get(['id', 'sku_base', 'familia_id', 'subfamilia_id', 'imagen_url', 'imagen_back_url', 'imagen_side_url'])
                ->keyBy('sku_base')
                ->toArray();
        }

        $items = $paginated->getCollection()->map(function ($item) use ($tallasMap, $isSlim, $colorMapById, $genericSkuMap, $colorIdByName, $tallaIdByName) {
            $skuProd = $item->movimientoInventario?->skuProducto;
            $variante = $item->variantes->first();

            $item->color = $skuProd?->color?->nombre
                ?? $variante?->color
                ?? $this->extractColorFromSku($item->sku, $colorMapById)
                ?? 'Generico';
            $item->genero = $skuProd?->skuGenerico?->genero?->nombre
                ?? $variante?->genero
                ?? $this->extractGeneroFromSku($item->sku)
                ?? 'Unisex';
            $genericSku = $this->toGenericSku($item->sku);
            $gen = ($genericSku && isset($genericSkuMap[$genericSku])) ? $genericSkuMap[$genericSku] : null;
            
            $item->familia = $skuProd?->skuGenerico?->familia?->nombre ?? $gen['familia']['nombre'] ?? null;
            $item->subfamilia = $skuProd?->skuGenerico?->subfamilia?->nombre ?? $gen['subfamilia']['nombre'] ?? null;
            $item->talla = $skuProd?->talla?->nombre
                ?? $variante?->talla
                ?? $tallasMap["{$item->cotizacion_id}_{$item->sku}"]
                ?? null;
            
            $item->image_url = $gen['imagen_url'] ?? null;
            $item->image_back_url = $gen['imagen_back_url'] ?? null;
            $item->image_side_url = $gen['imagen_side_url'] ?? null;

            // ── Construir sku_display con color+talla reales si el SKU es genérico ──
            $item->sku_display = $this->buildVariantSku($item->sku, $variante, $colorIdByName, $tallaIdByName);

            // ── 5. API Slimming: saca las imágenes base64 de layers ──────────
            //   Sólo se trunca si `content` es un base64 (legacy).
            //   Si ya es una URL (/storage/...), se pasa tal cual — es un string corto.
            if ($isSlim && is_array($item->configuracion)) {
                $config = $item->configuracion;

                if (!empty($config['layers'])) {
                    $config['layers'] = array_map(function ($l) {
                        $content = $l['content'] ?? null;
                        $isBase64 = $content && str_starts_with($content, 'data:');

                        $layer = is_array($l) ? $l : [];
                        // Mantener URL corta; descartar base64 pesado
                        if ($isBase64) {
                            $layer['content'] = null;
                        }

                        return $layer;
                    }, $config['layers']);
                }

                // También limpiar imágenes base64 dentro de definicion_diseno
                if (!empty($config['definicion_diseno']) && is_array($config['definicion_diseno'])) {
                    foreach ($config['definicion_diseno'] as $zona => &$data) {
                        if (isset($data['image']) && str_starts_with($data['image'], 'data:')) {
                            $data['image'] = null; // se carga completo en show()
                        }
                    }
                    unset($data);
                }

                $item->configuracion = $config;
            }

            // Liberar relaciones cargadas que ya no se necesitan en la respuesta
            $item->unsetRelation('movimientoInventario');

            return $item;
        });

        return response()->json([
            'data' => $items,
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'per_page' => $paginated->perPage(),
            'total' => $paginated->total(),
        ]);
    }

    public function storeBulk(Request $request)
    {
        \App\Services\PersonalizationWorkflow::authorize();
        $request->validate([
            'products' => 'required|array',
            'products.*.sku' => 'required|string',
            'products.*.nombre_producto' => 'required|string',
            'products.*.cantidad' => 'required|integer|min:1',
            'products.*.movimiento_inventario_id' => 'required|integer'
        ]);

        $created = [];
        $userId = Auth::id() ?? 1;

        // Pre-cargar mapas de color y talla por nombre para resolver SKUs genéricos
        $colorMapById = \App\Models\PteColor::pluck('nombre', 'id')->toArray();
        $colorIdByName = array_flip(array_map('strtolower', $colorMapById));
        $tallaMapById = \App\Models\PteTalla::pluck('nombre', 'id')->toArray();
        $tallaIdByName = array_flip(array_map('strtolower', $tallaMapById));

        DB::beginTransaction();
        try {
            foreach ($request->products as $prod) {
                $sku = $prod['sku'];
                $movimiento = \App\Models\PteMovimiento::with(['skuProducto.color', 'skuProducto.talla'])
                    ->findOrFail($prod['movimiento_inventario_id']);

                // Si el SKU es genérico (termina en 0000), intentar resolverlo
                // usando la información del skuProducto del movimiento
                if (strlen($sku) >= 15 && substr($sku, -4) === '0000') {
                    $movimiento = \App\Models\PteMovimiento::with([
                        'skuProducto.color',
                        'skuProducto.talla',
                    ])->find($prod['movimiento_inventario_id']);

                    if ($movimiento?->skuProducto) {
                        $colorId = $movimiento->skuProducto->color_id ?? 0;
                        $tallaId = $movimiento->skuProducto->talla_id ?? 0;

                        if ($colorId > 0 || $tallaId > 0) {
                            $base = substr($sku, 0, 11);
                            $sku = $base
                                . str_pad($colorId, 2, '0', STR_PAD_LEFT)
                                . str_pad($tallaId, 2, '0', STR_PAD_LEFT);
                        }
                    }
                }

                $personalizacion = Personalizacion::create([
                    'usuario_creador_id' => $userId,
                    'movimiento_inventario_id' => $prod['movimiento_inventario_id'],
                    'sku' => $sku,
                    'nombre_producto' => $prod['nombre_producto'],
                    'cantidad' => $prod['cantidad'],
                    'tipo' => 'bordado',
                    'estado' => 'pendiente_diseno',
                    'prioridad' => 'media',
                ]);

                // Crear variante inicial
                \App\Models\PersonalizacionVariante::create([
                    'personalizacion_id' => $personalizacion->id,
                    'talla' => $movimiento?->skuProducto?->talla?->nombre ?? 'S',
                    'color' => $movimiento?->skuProducto?->color?->nombre ?? 'Negro',
                    'genero' => $movimiento?->skuProducto?->genero?->nombre ?? 'Unisex',
                    'cantidad' => $prod['cantidad'],
                ]);

                $created[] = $personalizacion;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al crear personalizaciones masivas'], 500);
        }

        return response()->json($created, 201);
    }

    public function storeManual(Request $request)
    {
        \App\Services\PersonalizationWorkflow::authorize();
        $data = $request->validate([
            'cotizacion_id' => 'nullable|integer|exists:cotizaciones,id',
            'cliente_nombre' => 'required_without:cotizacion_id|nullable|string',
            'sku' => 'required|string',
            'producto_nombre' => 'required|string',
            'cantidad' => 'required|integer|min:1',
            'talla' => 'required|string',
            'color' => 'required|string',
            'tipo' => 'required|string|in:embroidery,print,mixed',
            'matrices' => 'nullable|array',
            'matrices.*.posicion' => 'required_with:matrices|string',
            'matrices.*.tecnica' => 'required_with:matrices|string',
            'matrices.*.tipo_contenido' => 'required_with:matrices|string',
            'matrices.*.valor_fijo' => 'nullable|string',
            'matrices.*.color' => 'nullable|string',
        ]);

        $userId = Auth::id() ?? 1;

        // Calcular el próximo numero_item
        $query = Personalizacion::query();
        if (!empty($data['cotizacion_id'])) {
            $query->where('cotizacion_id', $data['cotizacion_id']);
        } else {
            $query->whereNull('cotizacion_id');
        }
        $maxItem = $query->max('numero_item') ?? 0;

        try {
            DB::beginTransaction();

            $config = [];
            if (empty($data['cotizacion_id']) && !empty($data['cliente_nombre'])) {
                $config['cliente_manual'] = $data['cliente_nombre'];
            }
            if (!empty($data['matrices'])) {
                $config['grupos'] = [
                    [
                        'nombre_grupo' => 'Grupo A',
                        'matrices' => $data['matrices'],
                        'tallas' => [['talla' => $data['talla'], 'color' => $data['color'], 'genero' => 'Unisex', 'cantidad' => (int) $data['cantidad']]],
                    ]
                ];
            }

            $personalizacion = Personalizacion::create([
                'usuario_creador_id' => $userId,
                'cotizacion_id' => $data['cotizacion_id'] ?? null,
                'sku' => $data['sku'],
                'nombre_producto' => $data['producto_nombre'],
                'cantidad' => (int) $data['cantidad'],
                'estado' => 'manual',
                'tipo' => ['embroidery' => 'bordado', 'print' => 'estampado', 'mixed' => 'mixto'][$data['tipo']],
                'siguiente_proceso' => $data['tipo'] === 'mixed' ? 'mixto_pendiente' : null,
                'prioridad' => 'media',
                'numero_item' => $maxItem + 1,
                'configuracion' => $config
            ]);

            // Insertar variantes
            \App\Models\PersonalizacionVariante::create([
                'personalizacion_id' => $personalizacion->id,
                'talla' => $data['talla'],
                'color' => $data['color'],
                'genero' => 'Unisex',
                'cantidad' => (int) $data['cantidad'],
            ]);

            DB::commit();
            return response()->json($personalizacion, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al crear: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Detalle completo de una personalización (incluye configuracion sin slimming).
     * Usado cuando el frontend abre un item para editar.
     */
    public function show($id)
    {
        $item = Personalizacion::with([
            'usuarioCreador:id,name,email',
            'archivos',
            'variantes',
            'cotizacion:id,numero,total,plazo_produccion,cliente_id,contacto_id,cuerpo_id,compania_id,user_id',
            'cotizacion.user:id,name,email',
            'cotizacion.cliente:id,nombre_empresa,correo,parent_id',
            'cotizacion.cliente.parent:id,nombre_empresa,correo',
            'cotizacion.contacto:id,nombre,telefono,email',
            'cotizacion.cuerpo:id,nombre',
            'cotizacion.compania:id,nombre,cuerpo_id',
            'cotizacion.compania.cuerpo:id,nombre',
            'movimientoInventario:id,cotizacion_id,sku,tipo_movimiento',
            'movimientoInventario.skuProducto:sku,nombre,sku_generico_id,color_id,talla_id',
            'movimientoInventario.skuProducto.color:id,nombre',
            'movimientoInventario.skuProducto.talla:id,nombre',
            'movimientoInventario.skuProducto.skuGenerico:id,familia_id,subfamilia_id,genero_id',
            'movimientoInventario.skuProducto.skuGenerico.familia:id,nombre',
            'movimientoInventario.skuProducto.skuGenerico.subfamilia:id,nombre',
            'movimientoInventario.skuProducto.skuGenerico.genero:id,nombre',
        ])->findOrFail($id);

        // Append computed convenience fields (same as index)
        $skuProd = $item->pteMovimiento?->skuProducto;
        $colorMapById = \App\Models\PteColor::pluck('nombre', 'id')->toArray();
        $cliente = $item->cotizacion?->cliente;
        $clienteParent = $cliente?->parent;

        $variante = $item->variantes->first();

        $item->color = $skuProd?->color?->nombre
            ?? $variante?->color
            ?? $this->extractColorFromSku($item->sku, $colorMapById)
            ?? 'Generico';
        $item->genero = $skuProd?->genero?->nombre
            ?? $variante?->genero
            ?? $this->extractGeneroFromSku($item->sku)
            ?? 'Unisex';
        $item->talla = $skuProd?->talla?->nombre
            ?? $variante?->talla;

        $genericSku = $this->toGenericSku($item->sku);
        if ($genericSku) {
            $gen = \App\Models\PteSkuGenerico::with(['familia', 'subfamilia'])->where('sku_base', $genericSku)->first(['id', 'sku_base', 'familia_id', 'subfamilia_id', 'imagen_url', 'imagen_back_url', 'imagen_side_url']);
            $item->image_url = $gen?->imagen_url;
            $item->image_back_url = $gen?->imagen_back_url;
            $item->image_side_url = $gen?->imagen_side_url;
            
            $item->familia = $skuProd?->familia?->nombre ?? $gen?->familia?->nombre;
            $item->subfamilia = $skuProd?->subfamilia?->nombre ?? $gen?->subfamilia?->nombre;
        } else {
            $item->image_url = null;
            $item->image_back_url = null;
            $item->image_side_url = null;
        }

        $item->cliente_nombre = trim(implode(' ', array_filter([
            $cliente?->nombres,
            $cliente?->apellidos,
        ])))
            ?: $cliente?->nombre_contacto
            ?: $cliente?->empresa
            ?: $cliente?->nombre_empresa
            ?: trim(implode(' ', array_filter([
                $clienteParent?->nombres,
                $clienteParent?->apellidos,
            ])))
            ?: $clienteParent?->nombre_contacto
            ?: $clienteParent?->nombre_empresa
            ?: $item->configuracion['cliente_manual'] ?? null;

        return response()->json($item);
    }

    /**
     * Construye el SKU con color, talla y GÉNERO reales.
     * Estructura SKU (15 dígitos):
     *   [0-9]  = 10 chars base del producto  (familia, subfamilia, tipo, formato, etc.)
     *   [10]   = 1 char GÉNERO:  0=Unisex, 1=Femenino, 2=Masculino
     *   [11-12]= 2 chars COLOR:  ID de pte_colores (00=Genérico)
     *   [13-14]= 2 chars TALLA:  ID de pte_tallas  (00=Genérico)
     *
     * Si el SKU ya es específico (no termina en 0000), lo retorna tal cual.
     * Si hay múltiples variantes en config usa la primera encontrada.
     */
    private function buildVariantSku(string $sku, $variante, array $colorIdByName, array $tallaIdByName): string
    {
        // Solo aplica a SKUs de 15 chars con sufijo genérico (0000)
        if (strlen($sku) < 15 || substr($sku, -4) !== '0000') {
            return $sku;
        }

        if (!$variante) {
            return $sku;
        }

        $colorNombre = $variante->color;
        $tallaNombre = $variante->talla;
        $generoNombre = $variante->genero;

        // Resolver ID de color
        $colorId = 0;
        if ($colorNombre) {
            $colorId = $colorIdByName[strtolower($colorNombre)] ?? 0;
        }

        // Resolver ID de talla
        $tallaId = 0;
        if ($tallaNombre) {
            $tallaId = $tallaIdByName[strtolower($tallaNombre)] ?? 0;
        }

        // Resolver dígito de género (estándar: 0=Unisex, 1=Masculino, 2=Femenino)
        $generoDigit = 0; // Unisex por defecto
        if ($generoNombre) {
            $g = strtolower(trim($generoNombre));
            if (in_array($g, ['masculino', 'hombre', 'male', 'm', 'masc'])) {
                $generoDigit = 1;
            } elseif (in_array($g, ['femenino', 'mujer', 'female', 'f', 'fem'])) {
                $generoDigit = 2;
            }
        }

        if ($colorId === 0 && $tallaId === 0 && $generoDigit === 0) {
            return $sku; // No se pudo resolver nada
        }

        $base10 = substr($sku, 0, 10); // 10 chars del producto base (sin el dígito de género)
        return $base10
            . $generoDigit
            . str_pad($colorId, 2, '0', STR_PAD_LEFT)
            . str_pad($tallaId, 2, '0', STR_PAD_LEFT);
    }

    private function toGenericSku($sku): ?string
    {
        if (!is_string($sku) || strlen($sku) < 11) {
            return null;
        }

        // sku_base = 10 dígitos producto + 1 dígito género + 0000 (color=00, talla=00)
        return substr($sku, 0, 11) . '0000';
    }

    private function extractGeneroFromSku($sku): ?string
    {
        if (!is_string($sku) || strlen($sku) < 11) {
            return null;
        }

        // Posición 10 (base 0) es el género
        $generoId = (int) $sku[10];

        // Mapeo inverso según el estándar: 0=Unisex, 1=Masculino, 2=Femenino
        $map = [
            0 => 'Unisex',
            1 => 'Masculino',
            2 => 'Femenino'
        ];

        return $map[$generoId] ?? 'Unisex';
    }

    private function extractColorFromSku($sku, array $colorMapById): ?string
    {
        if (!is_string($sku) || strlen($sku) < 13) {
            return null;
        }

        // Sufijo variante: G CC TT. El color corresponde a CC (posiciones 12-13, base 1).
        $colorId = (int) substr($sku, 11, 2);
        if ($colorId <= 0) {
            return null;
        }

        return $colorMapById[$colorId] ?? null;
    }

    private function extractColorFromConfig($config): ?string
    {
        if (is_string($config)) {
            $decoded = json_decode($config, true);
            $config = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($config)) {
            return null;
        }

        if (!empty($config['color']) && is_string($config['color'])) {
            return $config['color'];
        }

        if (empty($config['grupos']) || !is_array($config['grupos'])) {
            return null;
        }

        foreach ($config['grupos'] as $grupo) {
            if (!is_array($grupo) || empty($grupo['tallas']) || !is_array($grupo['tallas'])) {
                continue;
            }

            foreach ($grupo['tallas'] as $talla) {
                if (is_array($talla) && !empty($talla['color']) && is_string($talla['color'])) {
                    return $talla['color'];
                }
            }
        }

        return null;
    }

    /**
     * Subir un asset de diseño (imagen) al disco público.
     * POST /api/personalizaciones/assets
     *
     * Body (multipart): file, personalizacion_id
     * Respuesta: { url: "/storage/personalizaciones/{id}/filename.jpg" }
     */
    public function uploadAsset(Request $request)
    {
        \App\Services\PersonalizationWorkflow::authorize();
        $request->validate([
            'file' => 'required|file|max:15360', // máx 15 MB (Soporta PDF, Imágenes, Matrices)
            'personalizacion_id' => 'required|integer|exists:personalizaciones,id',
        ]);

        $id = $request->personalizacion_id;
        $path = $request->file('file')->store("personalizaciones/{$id}", 'public');

        return response()->json([
            'url' => '/storage/' . $path,
        ], 201);
    }

    /**
     * Dividir un ítem de personalización en dos.
     * Al dividir, recalcula el SKU de cada parte basándose en
     * el color/talla de su configuración.
     */
    public function splitItem(Request $request, $id)
    {
        \App\Services\PersonalizationWorkflow::authorize();
        $request->validate(['remainingQty' => 'required|integer|min:1', 'splitQty' => 'required|integer|min:1']);
        return DB::transaction(function () use ($request, $id) {
            $item = \App\Services\PersonalizationWorkflow::lockItem($id);
            abort_if($item->estado === 'completado' || $item->personalizacion_padre_id || $item->bloqueado_qc, 422,
                'No se puede dividir un trabajo terminado o con reprocesos pendientes.');
            $clone = \App\Services\PersonalizationLots::split($item, (int) $request->remainingQty,
                (int) $request->splitQty, $request->remainingConfig, $request->splitConfig);
            return response()->json(['message' => 'Ítem dividido sin alterar el total.', 'parent' => $item, 'clone' => $clone]);
        });
    }

    /**
     * Crear una nueva personalización.
     */
    public function store(Request $request)
    {
        \App\Services\PersonalizationWorkflow::authorize();
        $request->validate([
            'sku' => 'nullable|string', // AHORA PUEDE SER NULLABLE SI ES MANUAL SIN INVENTARIO
            'producto_nombre' => 'required|string',
            'cantidad' => 'required|integer|min:1',
            'tipo' => 'required|string',
            'cotizacion_id' => 'nullable|exists:cotizaciones,id',
            // pte_movimiento_id es opcional
        ]);

        $personalizacion = Personalizacion::create([
            'usuario_creador_id' => Auth::id() ?? 1,
            'movimiento_inventario_id' => $request->pte_movimiento_id,
            'cotizacion_id' => $request->cotizacion_id,
            'sku' => $request->sku ?? 'CUSTOM',
            'nombre_producto' => $request->producto_nombre,
            'cantidad' => $request->cantidad,
            'tipo' => $request->tipo,
            'estado' => 'pendiente_diseno',
            'prioridad' => $request->prioridad ?? 'media',
            'notas' => $request->notas,
            'configuracion' => $request->configuracion ?? []
        ]);

        return response()->json($personalizacion, 201);
    }

    /**
     * Actualizar estado o configuración.
     */
    public function update(Request $request, $id)
    {
        \App\Services\PersonalizationWorkflow::authorize();
        return DB::transaction(function () use ($request, $id) {
            $item = \App\Services\PersonalizationWorkflow::lockItem($id);
            return response()->json(\App\Services\PersonalizationWorkflow::transition($item, $request->all()));
        });
    }

    /**
     * Procesar Control de Calidad (split por unidades defectuosas).
     */
    public function qualityControl(Request $request, $id)
    {
        \App\Services\PersonalizationWorkflow::authorize();
        $request->validate(['defectQty' => 'required|integer|min:0', 'notes' => 'nullable|string']);
        return DB::transaction(function () use ($request, $id) {
            $item = \App\Services\PersonalizationWorkflow::lockItem($id);
            abort_unless($item->estado === 'taller_en_proceso' && !$item->bloqueado_qc, 409,
                'Este trabajo ya fue revisado o está esperando reprocesos. Recarga el tablero.');
            $defects = (int) $request->defectQty;
            if ($item->cotizacion_id) \App\Services\QuotePersonalization::assertSale($item->cotizacion);
            $total = (int) $item->cantidad;
            abort_if($defects > $total, 422, 'Las unidades defectuosas no pueden superar el total del trabajo.');
            $item->operario_asignado_id = Auth::id();
            $item->notas = trim(($item->notas ?? '') . "\n[QC]: " . ($request->notes ?? ''));
            if ($defects === 0) {
                $item->estado = 'control_calidad';
                $item->fecha_inicio_qc = now();
                $item->save();
                $merged = \App\Services\PersonalizationLots::reunite($item);
                return response()->json(['message' => $merged ? 'Reprocesos reunidos. Control de calidad completo.' : 'Enviado a Control de Calidad.',
                    'auto_merged' => (bool) $merged, 'merged_into' => $merged, 'original' => $merged ?? $item]);
            }
            $item->ciclos_qc = ($item->ciclos_qc ?? 0) + 1;
            if ($defects === $total) {
                $item->fecha_inicio_proceso = now();
                $item->fecha_inicio_qc = null;
                $item->save();
                return response()->json(['message' => 'Todas las unidades continúan en reproceso.', 'original' => $item]);
            }
            $rootId = $item->personalizacion_padre_id ?: $item->id;
            $clone = \App\Services\PersonalizationLots::split($item, $total - $defects, $defects,
                $request->okConfig, $request->defectiveConfig);
            $clone->fill(['estado' => 'taller_en_proceso', 'fecha_inicio_proceso' => now(), 'fecha_inicio_qc' => null,
                'personalizacion_padre_id' => $rootId, 'bloqueado_qc' => false]);
            $clone->save();
            $item->fill(['estado' => 'control_calidad', 'fecha_inicio_qc' => now(),
                'personalizacion_padre_id' => $rootId, 'bloqueado_qc' => true]);
            $item->save();
            return response()->json(['message' => 'Unidades correctas en espera; unidades defectuosas en reproceso.',
                'original' => $item, 'clon' => $clone]);
        });
    }

    /**
     * Fusionar (Merge) ítems que fueron divididos.
     * Solo permitido si están en el mismo estado (etapa) y tienen misma cotización e ítem.
     */
    public function mergeItems(Request $request, $id)
    {
        \App\Services\PersonalizationWorkflow::authorize();
        return DB::transaction(function () use ($id) {
            $target = \App\Services\PersonalizationWorkflow::lockItem($id);
            abort_unless($target->cotizacion_id && $target->numero_item !== null, 422,
                'No se pueden unir pedidos manuales sin una cotización e ítem de origen compartidos.');
            abort_if($target->estado === 'completado' || $target->bloqueado_qc || $target->personalizacion_padre_id, 422,
                'No se pueden unir trabajos terminados ni interferir con reprocesos pendientes.');
            $candidates = Personalizacion::where('cotizacion_id', $target->cotizacion_id)
                ->where('numero_item', $target->numero_item)->where('tipo', $target->tipo)
                ->where('estado', $target->estado)->where('siguiente_proceso', $target->siguiente_proceso)
                ->where('proceso_previo', $target->proceso_previo)->where('nombre_producto', $target->nombre_producto)
                ->whereNull('personalizacion_padre_id')->where('bloqueado_qc', false)
                ->where('id', '!=', $target->id)->orderBy('id')->lockForUpdate()->get();
            abort_if($candidates->isEmpty(), 404, 'No hay trabajos compatibles para fusionar.');
            foreach ($candidates as $candidate) \App\Services\PersonalizationLots::merge($target, $candidate);
            return response()->json(['message' => 'Trabajos reunidos con sus variantes y archivos.', 'new_quantity' => $target->cantidad]);
        });
    }

    /**
     * Confirmar físicamente la recepción de un archivo (Matriz/DTF) en Taller.
     */
    public function confirmArchivo(Request $request, $id, $tipo)
    {
        \App\Services\PersonalizationWorkflow::authorize();
        $personalizacion = Personalizacion::findOrFail($id);
        
        $archivo = $personalizacion->archivos()->where('tipo_archivo', $tipo)->first();
        
        $confirmado = $request->input('confirmado', true);

        if (!$archivo) {
            $archivo = $personalizacion->archivos()->create([
                'tipo_archivo' => $tipo,
                'confirmado_fisico' => $confirmado,
            ]);
        } else {
            $archivo->update([
                'confirmado_fisico' => $confirmado,
            ]);
        }

        return response()->json([
            'message' => 'Recepción de archivo actualizada',
            'archivo' => $archivo
        ]);
    }

    /**
     * Crear o actualizar un archivo de personalización (Matriz, DTF, Resultado Taller).
     */
    public function upsertArchivo(Request $request, $id)
    {
        \App\Services\PersonalizationWorkflow::authorize();
        $request->validate([
            'tipo_archivo' => 'required|string',
            'url_archivo' => 'nullable|string',
            'confirmado_fisico' => 'nullable|boolean',
        ]);

        $personalizacion = Personalizacion::findOrFail($id);
        
        $archivo = $personalizacion->archivos()
            ->where('tipo_archivo', $request->tipo_archivo)
            ->first();
            
        $data = $request->only(['tipo_archivo', 'url_archivo', 'confirmado_fisico']);
        
        if ($archivo) {
            $archivo->update($data);
        } else {
            $archivo = $personalizacion->archivos()->create($data);
        }

        return response()->json([
            'message' => 'Archivo actualizado',
            'archivo' => $archivo
        ]);
    }

    public function destroy($id)
    {
        \App\Services\PersonalizationWorkflow::authorize();
        return DB::transaction(function () use ($id) {
            $item = \App\Services\PersonalizationWorkflow::lockItem($id);
            abort_if($item->cotizacion_id, 422, 'Devuelve la cotización completa a Comercial; eliminar solo una tarjeta dejaría cantidades pendientes.');
            abort_unless(in_array($item->estado, ['manual', 'pendiente_diseno', 'disenando', 'en_correccion', 'aprobacion'], true)
                && !$item->personalizacion_padre_id, 409, 'No se puede eliminar un trabajo que ya llegó a producción.');
            $item->delete();
            return response()->json(['message' => 'Borrador de personalización eliminado.']);
        });
    }

    public function destroyByCotizacion($cotizacion_id)
    {
        \App\Services\PersonalizationWorkflow::authorize();
        return DB::transaction(function () use ($cotizacion_id) {
            $quote = Cotizacion::lockForUpdate()->findOrFail($cotizacion_id);
            $jobs = Personalizacion::where('cotizacion_id', $quote->id)->lockForUpdate()->get();
            abort_if(Despacho::where('cotizacion_id', $quote->id)->exists() || $jobs->contains(fn ($job) =>
                !in_array($job->estado, ['manual', 'pendiente_diseno', 'disenando', 'en_correccion', 'aprobacion'], true)
                || $job->proceso_previo || $job->personalizacion_padre_id), 409,
                'La cotización ya tiene producción o despacho. No se eliminaron sus trabajos.');
            Personalizacion::where('cotizacion_id', $quote->id)->delete();
            $quote->update(['estado_personalizacion' => null, 'personalizacion_completada' => false]);
            return response()->json(['message' => 'Cotización devuelta a Comercial para revisar su configuración.']);
        });
    }

    /**
     * Obtener productos disponibles para personalizar (basado en salidas).
     * Busca en PteMovimiento donde tipo_movimiento = 'SALIDA_VENTA'
     */
    public function getAvailableProducts(Request $request)
    {
        // Traemos las ultimas 100 salidas que no tengan personalización aún
        $movimientos = PteMovimiento::where('tipo_movimiento', 'SALIDA_VENTA')
            ->whereDoesntHave('personalizacion') // Excluir si ya existe una ficha
            ->with(['skuProducto.skuGenerico.familia', 'skuProducto.skuGenerico.subfamilia', 'skuProducto.color', 'skuProducto.talla'])
            ->orderBy('fecha_hora', 'desc')
            ->take(100)
            ->get();

        // Pre-fetch colors for fallback lookup
        $colors = \App\Models\PteColor::pluck('nombre', 'id');

        // Formateamos para el frontend
        $data = $movimientos->map(function ($mov) use ($colors) {
            $sku = $mov->sku;
            $colorName = null;

            // 1. Try direct relationship
            if ($mov->skuProducto && $mov->skuProducto->color) {
                $colorName = $mov->skuProducto->color->nombre;
            }

            // 2. Fallback: Extract from SKU (Anteantepenultimo y Antepenultimo digits)
            // Example: SKU 12345678 -> Length 8. -4 and -3 are 5 and 6.
            if ((!$colorName || $colorName === 'Generico') && strlen($sku) >= 4) {
                $colorIdStr = substr($sku, -4, 2); // Get 2 chars starting at -4
                $colorId = (int) $colorIdStr;
                if (isset($colors[$colorId])) {
                    $colorName = $colors[$colorId];
                }
            }

            return [
                'id' => $mov->id, // ID del movimiento para linkear
                'sku' => $mov->sku,
                'producto_nombre' => $mov->skuProducto ? $mov->skuProducto->nombre : 'Producto Desconocido',
                'cantidad' => abs($mov->cantidad), // Cantidad vendida (es negativa en salida)
                'fecha' => $mov->fecha_hora,
                'cotizacion_id' => $mov->cotizacion_id,
                'familia' => $mov->skuProducto?->skuGenerico?->familia?->nombre ?? null,
                'subfamilia' => $mov->skuProducto?->skuGenerico?->subfamilia?->nombre ?? null,
                'color' => $colorName,
                'talla' => $mov->skuProducto && $mov->skuProducto->talla ? $mov->skuProducto->talla->nombre : null,
            ];
        });

        return response()->json($data);
    }

}
