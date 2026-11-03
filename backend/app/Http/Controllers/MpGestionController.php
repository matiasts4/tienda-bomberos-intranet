<?php

namespace App\Http\Controllers;

use App\Models\MpMaterial;
use App\Models\MpLote;
use App\Models\MpMovimiento;
use App\Models\MpProveedor;
use App\Models\MpColor;
use App\Models\MpTipo;
use App\Models\MpUnidad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class MpGestionController extends Controller
{
    /**
     * 1. LISTAR MATERIALES (Para TabExistencias)
     */
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 20);
        $search = $request->input('search');
        $tipoFiltro = $request->input('tipo'); // 'TELA' o 'INSUMO'

        $query = MpMaterial::query()
            ->with(['color', 'unidad', 'tipo'])
            ->withSum([
                'lotes' => function ($q) {
                    $q->where('estado', 'DISPONIBLE');
                }
            ], 'cantidad_actual');

        // 1. Filtrado por Tipo (Uniendo con la tabla mp_tipos)
        if ($tipoFiltro) {
            $query->whereHas('tipo', function ($q) use ($tipoFiltro) {
                $q->where('nombre', 'like', "%{$tipoFiltro}%");
            });
        }

        // 2. Filtrado por Búsqueda (Texto)
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nombre_base', 'like', "%{$search}%")
                    ->orWhere('codigo_interno', 'like', "%{$search}%")
                    ->orWhereHas('color', function ($cq) use ($search) {
                        $cq->where('nombre', 'like', "%{$search}%");
                    });
            });
        }

        // 3. Ejecutar Paginación
        $paginator = $query->paginate($perPage);

        // 4. Transformar los datos (Aplanar)
        $itemsTransformados = $paginator->getCollection()->map(function ($m) {
            $stockTotal = $m->lotes_sum_cantidad_actual ?? 0;
            return [
                'id' => $m->id,
                'nombre' => $m->nombre_base,
                'sku' => $m->codigo_interno,
                'tipo' => $m->tipo ? $m->tipo->nombre : 'N/A',
                'color' => $m->color ? $m->color->nombre : 'Sin Color',
                'color_hex' => $m->color ? $m->color->codigo_hex : 'transparent',
                'unidad' => $m->unidad ? $m->unidad->abreviacion : 'Ud',
                'stock_total' => (float) $stockTotal,
                'stock_minimo' => $m->stock_minimo,
                'alerta_stock' => $stockTotal <= $m->stock_minimo
            ];
        });

        // 5. Retornar en formato estandar de paginación
        $pagData = $paginator->toArray();
        $pagData['transformed_data'] = $itemsTransformados; // O simplemente reemplazar 'data'

        return response()->json($pagData);
    }

    public function indexProveedores(): JsonResponse
    {
        return response()->json(MpProveedor::orderBy('nombre_fantasia')->get());
    }

    /**
     * 2. DATOS PARA FORMULARIOS (Para TabCatalogos y TabRecepcion)
     */
    public function getDatosFormulario(): JsonResponse
    {
        return response()->json([
            'proveedores' => MpProveedor::select('id', 'nombre_fantasia')->orderBy('nombre_fantasia')->get(),
            'colores' => MpColor::select('id', 'nombre')->orderBy('nombre')->get(),
            'unidades' => MpUnidad::select('id', 'nombre', 'abreviacion')->get(),
            'tipos' => MpTipo::select('id', 'nombre')->get(),

            // Enviamos el tipo_id para la lógica de Insumos vs Telas en el frontend
            'materiales' => MpMaterial::with(['color', 'unidad', 'proveedores'])
                ->select('id', 'nombre_base', 'codigo_interno', 'unidad_id', 'tipo_id', 'color_id')
                ->orderBy('nombre_base')->get()
                ->map(function ($m) {
                    return [
                        'id' => $m->id,
                        'nombre_base' => $m->nombre_base,
                        'codigo_interno' => $m->codigo_interno, // El SKU
                        'color_base' => $m->color ? $m->color->nombre : '',
                        'unidad_medida' => $m->unidad ? $m->unidad->abreviacion : 'Ud',
                        'tipo_id' => $m->tipo_id, // 1=Tela, 2=Insumo (CRÍTICO PARA EL FRONTEND)
                        'catalogo_proveedores' => $m->proveedores->map(function ($p) {
                            return [
                                'id' => $p->id,
                                'nombre' => $p->nombre_fantasia,
                                'sku_externo' => $p->pivot->sku_proveedor,
                                'precio' => (float) $p->pivot->precio_referencia,
                                'moneda' => $p->pivot->moneda
                            ];
                        })
                    ];
                }),
        ]);
    }

    /**
     * NUEVO: SINCRONIZAR CATÁLOGO DE PROVEEDOR
     * Permite asociar un material a un proveedor con su precio y SKU propio.
     */
    public function syncCatalogoProveedor(Request $request): JsonResponse
    {
        $request->validate([
            'material_id' => 'required|exists:mp_materiales,id',
            'proveedor_id' => 'required|exists:mp_proveedores,id',
            'sku_proveedor' => 'nullable|string',
            'precio' => 'required|numeric|min:0',
            'moneda' => 'string|max:3'
        ]);

        $material = MpMaterial::findOrFail($request->material_id);

        $material->proveedores()->syncWithoutDetaching([
            $request->proveedor_id => [
                'sku_proveedor' => $request->sku_proveedor,
                'precio_referencia' => $request->precio,
                'moneda' => $request->moneda ?? 'CLP'
            ]
        ]);

        return response()->json(['message' => 'Catálogo actualizado correctamente']);
    }

    /**
     * NUEVO: LISTAR TODO EL CATÁLOGO (Para la tabla de gestión)
     */
    public function getCatalogoCompleto(): JsonResponse
    {
        // Obtener todas las relaciones existentes en la tabla pivote
        // con los detalles del material y del proveedor.
        $catalogo = DB::table('mp_proveedor_productos')
            ->join('mp_materiales', 'mp_proveedor_productos.material_id', '=', 'mp_materiales.id')
            ->join('mp_proveedores', 'mp_proveedor_productos.proveedor_id', '=', 'mp_proveedores.id')
            ->select(
                'mp_proveedor_productos.id as pivot_id', // Si tuvieras ID en pivote
                'mp_proveedor_productos.proveedor_id',
                'mp_proveedor_productos.material_id',
                'mp_proveedor_productos.sku_proveedor',
                'mp_proveedor_productos.precio_referencia',
                'mp_proveedor_productos.moneda',
                'mp_materiales.nombre_base as material_nombre',
                'mp_materiales.codigo_interno as material_sku',
                'mp_proveedores.nombre_fantasia as proveedor_nombre'
            )
            ->orderBy('mp_materiales.nombre_base')
            ->get();

        return response()->json($catalogo);
    }

    /**
     * 3. REGISTRAR INGRESO (Recepción Inteligente)
     */
    public function registrarIngreso(Request $request): JsonResponse
    {
        $request->validate([
            'proveedor_id' => 'required|exists:mp_proveedores,id',
            'factura' => 'required|string',
            'fecha_documento' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.material_id' => 'required|exists:mp_materiales,id',
            'items.*.cantidad' => 'required|numeric|min:0.01',
            // El lote no es 'required' estricto aquí porque lo podemos autogenerar si falta
        ]);

        return DB::transaction(function () use ($request) {
            $resultados = [];

            foreach ($request->items as $index => $item) {

                // 1. Obtener Material para validar Tipo
                $materialInfo = MpMaterial::find($item['material_id']);

                // 2. Lógica Inteligente de Lote (Si el frontend mandó vacío)
                $loteProveedor = $item['lote_proveedor'] ?? null;

                if (empty($loteProveedor) || trim($loteProveedor) === '') {
                    if ($materialInfo->tipo_id == 2) {
                        // TIPO 2 (INSUMO): Siempre lote GENERICO
                        $loteProveedor = 'GENERICO';
                    } else {
                        // TIPO 1 (TELA): Si no trae, generamos SL (Sin Lote) + Fecha
                        $loteProveedor = 'SL-' . date('ymd');
                    }
                }

                // 3. Generar ID Único de Barras (R-Timestamp-Index)
                // Este es el que va en el código QR del rollo físico
                $codigoUnico = 'R-' . time() . '-' . $index . rand(10, 99);


                // [NUEVO] AUTO-SYNC RELACIÓN PROVEEDOR-PRODUCTO
                // Si este proveedor nunca ha vendido este material, creamos la relación vacía
                // para que aparezca en el Catálogo y se pueda configurar precio/sku después.
                $existeRelacion = DB::table('mp_proveedor_productos')
                    ->where('proveedor_id', $request->proveedor_id)
                    ->where('material_id', $item['material_id'])
                    ->exists();

                if (!$existeRelacion) {
                    DB::table('mp_proveedor_productos')->insert([
                        'proveedor_id' => $request->proveedor_id,
                        'material_id' => $item['material_id'],
                        'sku_proveedor' => null,
                        'precio_referencia' => null,
                        'moneda' => 'CLP',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $lote = MpLote::create([
                    'material_id' => $item['material_id'],
                    'proveedor_id' => $request->proveedor_id, // <--- PERSISTIR PROVEEDOR EN EL LOTE

                    'codigo_barra_unico' => $codigoUnico,
                    'codigo_lote_proveedor' => strtoupper(trim($loteProveedor)),
                    'factura_referencia' => $request->factura,
                    'fecha_ingreso' => $request->fecha_documento, // Fecha de la factura, no hoy
                    'cantidad_inicial' => $item['cantidad'],
                    'cantidad_actual' => $item['cantidad'],
                    'ubicacion' => $item['ubicacion'] ?? 'BODEGA-RECEPCION',
                    'estado' => 'DISPONIBLE'
                ]);

                MpMovimiento::create([
                    'lote_id' => $lote->id,
                    'proveedor_id' => $request->proveedor_id, // <--- PERSISTIR PROVEEDOR EN EL MOVIMIENTO
                    'tipo_movimiento' => 'INGRESO_COMPRA',
                    'cantidad' => $item['cantidad'],
                    'usuario_id' => 1, // Puedes cambiar esto por Auth::id()
                    'documento_respaldo' => $request->factura,
                    'observacion' => 'Ingreso inicial por compra Factura ' . $request->factura
                ]);

                $resultados[] = $lote;
            }
            return response()->json(['message' => 'Ingreso registrado correctamente', 'total_bultos' => count($resultados)]);
        });
    }

    /**
     * 4. CREAR PROVEEDOR
     */
    public function storeProveedor(Request $request)
    {
        $validated = $request->validate([
            'nombre_fantasia' => 'required|string',
            'rut' => 'nullable|string',
            'contacto_nombre' => 'nullable|string',
            'contacto_email' => 'nullable|email',
            'contacto_telefono' => 'nullable|string',
        ]);

        $proveedor = MpProveedor::create([
            'nombre_fantasia' => $validated['nombre_fantasia'],
            'rut_empresa' => $validated['rut'],
            'contacto_nombre' => $validated['contacto_nombre'],
            'email' => $validated['contacto_email'],
            'telefono' => $validated['contacto_telefono'],
        ]);

        return response()->json(['message' => 'Proveedor creado', 'data' => $proveedor]);
    }

    /**
     * ACTUALIZAR PROVEEDOR
     */
    public function updateProveedor(Request $request, $id)
    {
        $validated = $request->validate([
            'nombre_fantasia' => 'required|string',
            'rut' => 'nullable|string',
            'contacto_nombre' => 'nullable|string',
            'contacto_email' => 'nullable|email',
            'contacto_telefono' => 'nullable|string',
        ]);

        $proveedor = MpProveedor::findOrFail($id);
        $proveedor->update([
            'nombre_fantasia' => $validated['nombre_fantasia'],
            'rut_empresa'     => $validated['rut'] ?? null,
            'contacto_nombre' => $validated['contacto_nombre'] ?? null,
            'email'           => $validated['contacto_email'] ?? null,
            'telefono'        => $validated['contacto_telefono'] ?? null,
        ]);

        return response()->json(['message' => 'Proveedor actualizado', 'data' => $proveedor]);
    }

    /**
     * ELIMINAR PROVEEDOR
     */
    public function destroyProveedor($id)
    {
        $proveedor = MpProveedor::findOrFail($id);

        // Validación: No eliminar si tiene lotes asociados
        if ($proveedor->lotes()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar el proveedor porque tiene lotes de mercadería asociados.'
            ], 422);
        }

        $proveedor->delete();
        return response()->json(['message' => 'Proveedor eliminado correctamente']);
    }

    /**
     * 5. CREAR MATERIAL
     */
    public function storeMaterial(Request $request)
    {
        $validated = $request->validate([
            'nombre_base' => 'required|string|max:255',
            'codigo_interno' => 'required|string|unique:mp_materiales,codigo_interno',
            'tipo_id' => 'required|exists:mp_tipos,id',
            'unidad_id' => 'required|exists:mp_unidades,id',
            'color_id' => 'nullable|exists:mp_colores,id',
            'stock_minimo' => 'numeric|min:0',
        ]);
        $material = MpMaterial::create($validated);
        return response()->json(['message' => 'Material creado', 'data' => $material]);
    }

    /**
     * 6. DETALLE DE LOTES (Con validación de Tipo)
     */
    public function getLotesByMaterial($id)
    {
        $material = MpMaterial::with([
            'unidad',
            'lotes' => function ($q) {
                $q->with('proveedor') // <--- CARGAR RELACIÓN PROVEEDOR
                    ->where('cantidad_actual', '>', 0)
                    ->where('estado', 'DISPONIBLE')
                    ->orderBy('fecha_ingreso', 'asc');
            }
        ])->findOrFail($id);

        return response()->json([
            'material' => [
                'nombre' => $material->nombre_base,
                'codigo' => $material->codigo_interno,
                'unidad' => $material->unidad ? $material->unidad->abreviacion : 'Ud',
                'tipo_id' => $material->tipo_id,
            ],
            'lotes' => $material->lotes->map(function ($lote) {
                return [
                    'id' => $lote->id,
                    'lote_proveedor' => $lote->codigo_lote_proveedor,
                    'codigo_barra' => $lote->codigo_barra_unico,
                    'cantidad' => (float) $lote->cantidad_actual,
                    'ubicacion' => $lote->ubicacion,
                    'fecha_ingreso' => \Carbon\Carbon::parse($lote->fecha_ingreso)->format('d/m/Y'),
                    'dias_en_bodega' => \Carbon\Carbon::parse($lote->fecha_ingreso)->diffInDays(now()),
                    'proveedor' => $lote->proveedor ? $lote->proveedor->nombre_fantasia : 'Origen Desconocido' // <--- ENVIAR AL FRONTEND
                ];
            })
        ]);
    }

    /**
     * 7. ACTUALIZAR UN LOTE
     */
    public function updateLote(Request $request, $id)
    {
        $request->validate(['cantidad' => 'numeric|min:0', 'ubicacion' => 'string']);
        $lote = MpLote::findOrFail($id);

        if ($lote->cantidad_actual != $request->cantidad) {
            MpMovimiento::create([
                'lote_id' => $lote->id,
                'tipo_movimiento' => 'AJUSTE_INVENTARIO',
                'cantidad' => $request->cantidad - $lote->cantidad_actual,
                'usuario_id' => 1,
                'observacion' => 'Ajuste manual desde existencias'
            ]);
        }
        $lote->cantidad_actual = $request->cantidad;
        $lote->ubicacion = $request->ubicacion;
        $lote->save();
        return response()->json(['message' => 'Lote actualizado']);
    }

    /**
     * 8. ACTUALIZAR MATERIAL (Stock Crítico)
     */
    public function updateMaterial(Request $request, $id)
    {
        $request->validate([
            'stock_minimo' => 'required|numeric|min:0'
        ]);

        $material = MpMaterial::findOrFail($id);
        $material->stock_minimo = $request->stock_minimo;
        $material->save();

        return response()->json(['message' => 'Material actualizado']);
    }

    /**
     * 9. ELIMINAR MATERIAL COMPLETO
     */
    public function destroyMaterial($id)
    {
        return DB::transaction(function () use ($id) {
            $material = MpMaterial::findOrFail($id);
            $lotesIds = $material->lotes()->pluck('id');
            MpMovimiento::whereIn('lote_id', $lotesIds)->delete();
            MpLote::where('material_id', $id)->delete();
            $material->proveedores()->detach();
            $material->delete();

            return response()->json(['message' => 'Material eliminado correctamente']);
        });
    }

    /**
     * 10. ELIMINAR UN LOTE (Protegido para Insumos)
     */
    public function destroyLote($id)
    {
        $lote = MpLote::with('material')->findOrFail($id);

        if ($lote->material->tipo_id == 2) {
            return response()->json([
                'message' => 'No se puede eliminar un lote de Insumo. Ajuste el stock a 0 con el lápiz.'
            ], 422);
        }

        return DB::transaction(function () use ($lote) {
            $lote->movimientos()->delete();
            $lote->delete();
            return response()->json(['message' => 'Lote eliminado']);
        });
    }
}