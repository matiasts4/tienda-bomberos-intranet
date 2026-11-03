<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CotizacionUpdateRequest;
use App\Models\Cotizacion;
use App\Models\Estado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\QuotePersonalization;
use App\Services\KitPersonalization;
use App\Support\DeliveryDeadline;
use App\Support\NoVigente;

class CotizacionController extends Controller
{
    public function index(Request $request)
    {
        $q = Cotizacion::query()->with(['cliente.parent', 'cliente.comuna', 'contacto', 'cuerpo.comuna', 'compania.cuerpo.comuna', 'oportunidad.contacto', 'ejecutivo', 'estado', 'detalles', 'historial', 'gestiones.user', 'ultimaGestion', 'archivos', 'creador', 'traspasos', 'noVigentePor:id,name']);

        if ($request->filled('estado_id')) {
            $q->where('estado_id', (int) $request->input('estado_id'));
        }

        if ($request->filled('cliente_id')) {
            if ($request->boolean('include_children')) {
                $clienteId = $request->input('cliente_id');
                $q->where(function ($query) use ($clienteId) {
                    $query->where('cliente_id', $clienteId)
                        ->orWhereHas('cliente', function ($c) use ($clienteId) {
                            $c->where('parent_id', $clienteId);
                        });
                });
            } else {
                $q->where('cliente_id', $request->input('cliente_id'));
            }
        }

        if ($request->filled('cuerpo_id')) {
            $cuerpoId = $request->input('cuerpo_id');
            $q->where(function ($query) use ($cuerpoId) {
                $query->where('cuerpo_id', $cuerpoId)
                    ->orWhereHas('compania', function ($c) use ($cuerpoId) {
                        $c->where('cuerpo_id', $cuerpoId);
                    });
            });
        }

        if ($request->filled('compania_id')) {
            $q->where('compania_id', $request->input('compania_id'));
        }

        if ($request->filled('oportunidad_id')) {
            $q->where('oportunidad_id', (int) $request->input('oportunidad_id'));
        }

        if ($request->filled('ejecutivo_id')) {
            $q->where('user_id', (int) $request->input('ejecutivo_id'));
        }

        // A quién va dirigida: bomberos | empresa | persona_natural
        if ($request->filled('tipo_destinatario')) {
            $q->deTipoDestinatario((string) $request->input('tipo_destinatario'));
        }

        if ($request->filled('from')) {
            $q->whereDate('fecha_creacion', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $q->whereDate('fecha_creacion', '<=', $request->input('to'));
        }

        // Búsqueda rápida por N° de cotización. El tablero muestra `numero` o, si
        // está vacío, el ID como "CT-0042"; se aceptan ambas formas (con o sin
        // "CT-", "#" y ceros a la izquierda). Sobre el ID la coincidencia es
        // exacta para que "42" no traiga también la 420 ni la 1042.
        if ($request->filled('numero')) {
            $raw = trim($request->string('numero')->toString());
            $termino = preg_replace('/^(?:#|CT-?)\s*/i', '', $raw);
            $q->where(function ($w) use ($raw, $termino) {
                $w->where('numero', 'like', '%' . $raw . '%');
                if ($termino !== '' && $termino !== $raw) {
                    $w->orWhere('numero', 'like', '%' . $termino . '%');
                }
                if (ctype_digit($termino) && strlen($termino) <= 10) {
                    $w->orWhere('id', (int) $termino);
                }
            });
        }

        if ($request->filled('search')) {
            $s = '%' . $request->string('search')->toString() . '%';
            $q->where(function ($w) use ($s) {
                $w->where('numero', 'like', $s)
                    ->orWhere('id', 'like', $s)
                    ->orWhereHas(
                        'cliente',
                        fn($c) =>
                        $c->where('nombre_empresa', 'like', $s)
                            ->orWhere('rut_empresa', 'like', $s)
                    );
            });
        }

        // El tablero es compartido: todos los usuarios ven las cotizaciones de
        // todos, y quien quiera acotar usa el filtro ejecutivo_id de arriba.
        // (Antes los no-admin quedaban restringidos a las propias, lo que además
        // anulaba el filtro por ejecutivo para esos roles.)

        $perPage = min(100, max(1, (int) $request->input('per_page', 20)));

        return response()->json($q->orderByDesc('id')->paginate($perPage));
    }

    public function show(Cotizacion $cotizacion)
    {
        // cliente.contactos: fallback de "datos del contacto" cuando la
        // cotización no tiene oportunidad (o esta no tiene contacto asignado).
        $cotizacion->load(['cliente.parent', 'cliente.comuna', 'cliente.contactos', 'contacto', 'cuerpo.comuna', 'compania.cuerpo.comuna', 'oportunidad.contacto', 'ejecutivo', 'estado', 'detalles.skuGenerico', 'creador', 'traspasos', 'archivos']);
        return response()->json($cotizacion);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'cliente_id' => ['nullable', 'string', 'max:15', 'exists:clientes,id'],
            'contacto_id' => ['nullable', 'integer', 'exists:contactos,id'],
            'cuerpo_id' => ['nullable', 'string', 'max:15', 'exists:cuerpos,id'],
            'compania_id' => ['nullable', 'string', 'max:15', 'exists:companias,id'],
            'oportunidad_id' => ['nullable', 'integer', 'exists:oportunidades,id'],
            'fecha_creacion' => ['nullable', 'date'],
            'fecha_vencimiento' => ['nullable', 'date'],
            'observaciones' => ['nullable', 'string'],
            'origen' => ['nullable', 'string', 'max:50'],
            'nombre' => ['required', 'string', 'max:255'],
            'plazo_produccion' => ['nullable', 'string', 'max:120'],
            'condiciones_pago' => ['nullable', 'string', 'max:120'],
            'despacho' => ['nullable', 'string', 'max:120'],
        ]);

        $user = $request->user(); // auth:sanctum
        if (!$user) {
            return response()->json(['message' => 'No autenticado'], 401);
        }

        // Una cotización siempre necesita alguna ancla: un cliente suelto, o el contacto/
        // cuerpo/compañía de la red de bomberos.
        if (empty($data['cliente_id']) && empty($data['contacto_id']) && empty($data['cuerpo_id']) && empty($data['compania_id'])) {
            return response()->json([
                'message' => 'Debes indicar cliente_id, contacto_id, cuerpo_id o compania_id.',
                'errors' => [
                    'cliente_id' => ['Debes indicar al menos un cliente, contacto, cuerpo o compañía.'],
                ],
            ], 422);
        }

        // ✅ Estado por defecto: "Cotización Enviada" (scope=cotizacion)
        $estadoDefaultId = Estado::where('scope', 'cotizacion')
            ->where('nombre', 'Cotización Enviada')
            ->value('id');

        if (!$estadoDefaultId) {
            return response()->json([
                'message' => 'No existe el estado "Cotización Enviada" con scope=cotizacion.',
            ], 500);
        }

        $cotizacion = Cotizacion::create([
            'cliente_id' => $data['cliente_id'] ?? null,
            'contacto_id' => $data['contacto_id'] ?? null,
            'cuerpo_id' => $data['cuerpo_id'] ?? null,
            'compania_id' => $data['compania_id'] ?? null,
            'oportunidad_id' => $data['oportunidad_id'] ?? null,
            'user_id' => $user->id,
            'creado_por_id' => $user->id,
            'estado_id' => $estadoDefaultId,
            'fecha_creacion' => $data['fecha_creacion'] ?? now()->toDateString(),
            'fecha_vencimiento' => $data['fecha_vencimiento'] ?? \App\Support\QuoteExpiry::defaultFor(now()),
            'observaciones' => $data['observaciones'] ?? null,
            'origen' => $data['origen'] ?? 'clientes',
            'nombre' => $data['nombre'] ?? null,
            'plazo_produccion' => $data['plazo_produccion'] ?? '20 dias',
            'condiciones_pago' => $data['condiciones_pago'] ?? 'Contado - Transferencia',
            'despacho' => $data['despacho'] ?? 'Por pagar',

            // ✅ por defecto
            'total_neto' => 0,
            'iva' => 0,
            'total' => 0,
        ]);

        // Si no se proporcionó número, generamos uno automático basado en el ID
        if (!$cotizacion->numero) {
            $cotizacion->update(['numero' => 'CT-' . str_pad($cotizacion->id, 4, '0', STR_PAD_LEFT)]);
        }

        return response()->json(
            $cotizacion->fresh()->load(['cliente.parent', 'cliente.comuna', 'oportunidad.contacto', 'contacto', 'cuerpo.comuna', 'compania.cuerpo.comuna', 'ejecutivo', 'estado', 'detalles']),
            201
        );
    }

    /**
     * "12.597" / "50,388" / "$ 12.597" => 12597 / 50388
     */
    private function parseMoneyToInt($v): ?int
    {
        if ($v === null)
            return null;
        $digits = preg_replace('/[^\d]/', '', (string) $v);
        if ($digits === '' || $digits === null)
            return null;
        return (int) $digits;
    }

    public function update(CotizacionUpdateRequest $request, Cotizacion $cotizacion)
    {
        if (!$cotizacion->id) {
            return response()->json(['message' => 'Cotización no encontrada.'], 404);
        }

        $data = $request->validated();
        $detalles = $data['detalles'] ?? null;
        // Detalles crudos (sin pasar por validated()) para leer el neto exacto
        // por línea que prepareForValidation calculó antes de redondear.
        $detallesCrudos = $request->input('detalles', []);
        unset($data['detalles']);

        return DB::transaction(function () use ($cotizacion, $data, $detalles, $detallesCrudos, $request) {
            // ✅ SIEMPRE lock y re-fetch
            $cot = Cotizacion::lockForUpdate()->findOrFail($cotizacion->id);
            $preservedDetails = null;
            if ($detalles !== null) {
                $existingDetails = $cot->detalles()->lockForUpdate()->get();
                if ($existingDetails->contains(fn ($detail) => ($detail->configuracion['kit_version'] ?? null) === 1)) {
                    $preservedDetails = KitPersonalization::commercialDetails($detalles, $existingDetails);
                }
            }

            // ✅ si por algún motivo quedó null, lo rellenamos
            if (empty($cot->fecha_creacion)) {
                $cot->fecha_creacion = now()->toDateString();
            }

            // ✅ Auto-set vencimiento 15 días si no existe
            if (empty($cot->fecha_vencimiento)) {
                $cot->fecha_vencimiento = \App\Support\QuoteExpiry::defaultFor($cot->fecha_creacion);
            }

            // La cotización debe seguir teniendo alguna ancla tras el update: si el payload
            // deja cliente_id/contacto_id/cuerpo_id/compania_id todos vacíos, no se aplica
            // ese cambio en particular (se descartan esas claves, el resto del update sigue).
            $ancla = [
                'cliente_id' => array_key_exists('cliente_id', $data) ? $data['cliente_id'] : $cot->cliente_id,
                'contacto_id' => array_key_exists('contacto_id', $data) ? $data['contacto_id'] : $cot->contacto_id,
                'cuerpo_id' => array_key_exists('cuerpo_id', $data) ? $data['cuerpo_id'] : $cot->cuerpo_id,
                'compania_id' => array_key_exists('compania_id', $data) ? $data['compania_id'] : $cot->compania_id,
            ];
            if (!array_filter($ancla)) {
                unset($data['cliente_id'], $data['contacto_id'], $data['cuerpo_id'], $data['compania_id']);
            }

            if (!empty($data)) {
                // Unique numero manual
                if (array_key_exists('numero', $data) && $data['numero'] !== null) {
                    $exists = Cotizacion::where('numero', $data['numero'])
                        ->where('id', '!=', $cot->id)
                        ->exists();

                    if ($exists) {
                        return response()->json([
                            'message' => 'El numero de cotizacion ya existe.',
                            'errors' => ['numero' => ['El numero de cotizacion ya existe.']],
                        ], 422);
                    }
                }

                $cot->fill($data);
                $cot->save();
            } else {
                $cot->save();
            }


            if ($detalles !== null) {
                /**
                 * ✅ IMPORTANTE (SKU opcional):
                 * Este controller asume que YA cambiaste la DB para permitir:
                 * - detalle_cotizaciones.sku NULLABLE
                 * - y/o que NO exista una FK obligatoria a pte_skus(sku)
                 *
                 * Si tu tabla todavía tiene la FK, te va a reventar cuando sku no exista o sea null.
                 */

                if ($preservedDetails === null) {
                    DB::table('detalle_cotizaciones')
                        ->where('cotizacion_id', $cot->id)
                        ->delete();
                }

                $now = now();
                $rows = [];

                // float: acumula el neto EXACTO de cada línea (sin redondear) y
                // redondea una sola vez al final, en vez de sumar netos que ya
                // fueron redondeados individualmente por línea (eso es lo que
                // generaba diferencias de unos pocos pesos contra el cálculo manual).
                $totalNetoCalc = 0.0;

                foreach ($detalles as $idx => $d) {
                    $cantidad = (int) ($d['cantidad'] ?? 0);

                    $precioUnitario = $this->parseMoneyToInt($d['precio_unitario'] ?? null);
                    $totalNetoItem = $this->parseMoneyToInt($d['total_neto'] ?? null);
                    $subtotalItem = $this->parseMoneyToInt($d['subtotal'] ?? null);

                    $totalNetoExacto = $detallesCrudos[$idx]['_total_neto_exacto'] ?? null;

                    if ($totalNetoExacto !== null) {
                        $totalNetoCalc += (float) $totalNetoExacto;
                    } elseif ($totalNetoItem !== null) {
                        $totalNetoCalc += $totalNetoItem;
                    } else {
                        $totalNetoCalc += (($precioUnitario ?? 0) * $cantidad);
                    }

                    $rows[] = [
                        'cotizacion_id' => $cot->id,

                        // ✅ permite cotizar sin SKU exacto
                        'sku' => $d['sku'] ?? null,

                        'n_item' => $d['n_item'] ?? ($idx + 1),
                        'cantidad' => $cantidad,
                        'subtotal' => $subtotalItem,
                        'is_personalizable' => (bool) ($d['is_personalizable'] ?? false),

                        // 👇 estos campos deben existir en tu tabla detalle_cotizaciones,
                        // si no existen, elimínalos de aquí y de tu request.
                        'producto' => $d['producto'] ?? null,
                        'talla' => $d['talla'] ?? null,
                        'color' => $d['color'] ?? null,
                        'genero' => $d['genero'] ?? null,
                        'tipo_personalizacion' => $d['tipo_personalizacion'] ?? null,

                        'precio_unitario' => $precioUnitario,
                        'total_neto' => $totalNetoItem,

                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if (!empty($rows)) {
                    if ($preservedDetails !== null) {
                        foreach ($rows as $row) {
                            $detail = $preservedDetails[(int) $row['n_item']];
                            // Los campos comerciales pueden cambiar; el ID, composición y técnica operativa permanecen.
                            unset($row['cotizacion_id'], $row['created_at'], $row['tipo_personalizacion'], $row['is_personalizable']);
                            $detail->forceFill($row)->save();
                        }
                    } else {
                        DB::table('detalle_cotizaciones')->insert($rows);
                    }
                }

                // ✅ Persistimos totales en cabecera (esto es lo que necesita el Kanban)
                // Único redondeo del agregado (evita el drift de sumar netos ya
                // redondeados por línea).
                $cot->total_neto = (int) round($totalNetoCalc);

                // Si quieres IVA/total final automáticos:
                $cot->iva = (int) round($cot->total_neto * 0.19);
                $cot->total = (int) ($cot->total_neto + $cot->iva);

                $cot->save();
            }

            // ✅ Log historial de modificación (siempre, ya que entró al update)
            \App\Models\HistorialEtapa::create([
                'model_type' => Cotizacion::class,
                'model_id' => $cot->id,
                'user_id' => ($request->user() ? $request->user()->id : 1), // fallback si no auth
                'estado_anterior_id' => $cot->estado_id,
                'estado_nuevo_id' => $cot->estado_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json(
                $cot->load(['cliente.parent', 'oportunidad.contacto', 'ejecutivo', 'estado', 'detalles'])
            );
        });
    }

    public function destroy(Cotizacion $cotizacion)
    {
        return DB::transaction(function () use ($cotizacion) {
            DB::table('detalle_cotizaciones')->where('cotizacion_id', $cotizacion->id)->delete();
            $cotizacion->delete();

            return response()->json(['ok' => true]);
        });
    }

    public function updateEstado(Request $request, Cotizacion $cotizacion)
    {
        $request->validate([
            'estado_id' => ['required', 'integer', 'exists:estados,id'],
        ]);

        return DB::transaction(function () use ($request, $cotizacion) {
            $cot = Cotizacion::lockForUpdate()->findOrFail($cotizacion->id);

            $estadoId = (int) $request->input('estado_id');

            $estado = Estado::find($estadoId);
            if (($estado->scope ?? null) !== 'cotizacion') {
                return response()->json([
                    'message' => 'El estado enviado no pertenece al scope cotizacion.',
                ], 422);
            }

            // Validar restricción para "Ventas" (#4 o busca por nombre)
            if ($estado->nombre === 'Aprobada por Cliente' || $estado->nombre === 'Ventas') {
                // Verificar documentos
                $count = \App\Models\CotizacionArchivo::where('cotizacion_id', $cot->id)
                    ->whereIn('tipo', ['orden_venta', 'facturacion', 'pago'])
                    ->count();

                if ($count === 0) {
                    return response()->json([
                        'message' => 'Debes adjuntar al menos un documento (Orden de Venta, Facturación o Pago) para aprobar la cotización.',
                        'code' => 'MISSING_DOCS'
                    ], 422);
                }
            }

            // ✅ Validar "Rechazada"
            if ($estado->nombre === 'Rechazada' || $estado->nombre === 'Rechazado') {
                if (!$request->filled('motivo_rechazo')) {
                    return response()->json([
                        'message' => 'Debes indicar el motivo del rechazo.',
                        'code' => 'MISSING_REASON'
                    ], 422);
                }
                $cot->motivo_rechazo = $request->input('motivo_rechazo');
                $cot->fecha_rechazo = now();
            }

            $estadoAnteriorId = $cot->estado_id;
            $estadoAnterior = Estado::find($estadoAnteriorId);
            $cambiaEstado = $estadoAnteriorId !== $estadoId;
            $eraNoVigente = $estadoAnterior?->nombre === NoVigente::ESTADO;
            $esSuperadmin = $request->user()->role === 'superadmin';
            $observacionHistorial = null;

            if ($cambiaEstado && $eraNoVigente && !$esSuperadmin
                && in_array($cot->motivo_no_vigente, NoVigente::SOLO_SUPERADMIN, true)) {
                return response()->json([
                    'message' => 'Solo un superadmin puede reactivar una cotización con finalización administrativa.',
                    'code' => 'SUPERADMIN_ONLY',
                ], 403);
            }

            if ($cambiaEstado && $estado->nombre === NoVigente::ESTADO) {
                $datos = $request->validate(NoVigente::reglas());
                $motivo = $datos['motivo_no_vigente'];

                if (in_array($motivo, NoVigente::SOLO_SUPERADMIN, true) && !$esSuperadmin) {
                    return response()->json([
                        'message' => 'La finalización administrativa solo la puede registrar un superadmin.',
                        'code' => 'SUPERADMIN_ONLY',
                    ], 403);
                }

                if (in_array($motivo, NoVigente::SOLO_DESDE_VENTA, true)
                    && !in_array($estadoAnterior?->nombre, DeliveryDeadline::ESTADOS_VENTA, true)) {
                    return response()->json([
                        'message' => 'Solo una cotización en Ventas puede marcarse como Finalizada. Usa otro motivo.',
                        'code' => 'NOT_SOLD',
                    ], 422);
                }

                $otro = $motivo === 'otro' ? $datos['motivo_no_vigente_otro'] : null;
                $cot->fill([
                    'motivo_no_vigente' => $motivo,
                    'motivo_no_vigente_otro' => $otro,
                    'observacion_no_vigente' => $datos['observacion_no_vigente'],
                    'fecha_no_vigente' => now(),
                    'no_vigente_por_id' => $request->user()->id,
                ]);
                $observacionHistorial = NoVigente::glosa($motivo, $otro, $datos['observacion_no_vigente']);
            } elseif ($cambiaEstado && $eraNoVigente) {
                // Vuelve al flujo: el motivo anterior queda solo en el historial.
                $cot->fill([
                    'motivo_no_vigente' => null,
                    'motivo_no_vigente_otro' => null,
                    'observacion_no_vigente' => null,
                    'fecha_no_vigente' => null,
                    'no_vigente_por_id' => null,
                ]);
            }

            $cot->estado_id = $estadoId;
            // El plazo de entrega corre desde que la cotización entra a venta.
            if ($cambiaEstado && in_array($estado->nombre, DeliveryDeadline::ESTADOS_VENTA, true)) {
                $esVentaAnterior = in_array($estadoAnterior?->nombre, DeliveryDeadline::ESTADOS_VENTA, true);
                if (!$esVentaAnterior || !$cot->fecha_venta) {
                    $cot->fecha_venta = now()->toDateString();
                }
            }
            $cot->save();

            // Registrar Historial
            if ($cambiaEstado) {
                \App\Models\HistorialEtapa::create([
                    'model_type' => Cotizacion::class,
                    'model_id' => $cot->id,
                    'user_id' => $request->user()->id,
                    'estado_anterior_id' => $estadoAnteriorId,
                    'estado_nuevo_id' => $estadoId,
                    'observaciones' => $observacionHistorial,
                ]);
            }

            return response()->json([
                'cotizacion' => $cot->fresh()->load(['cliente.parent', 'oportunidad.contacto', 'ejecutivo', 'estado', 'detalles', 'historial', 'gestiones.user', 'ultimaGestion', 'archivos', 'noVigentePor:id,name']),
            ]);
        });
    }

    public function uploadArchivo(Request $request, Cotizacion $cotizacion)
    {
        $request->validate([
            'archivo' => ['required', 'file', 'max:10240'], // 10MB
            'tipo' => ['required', 'string', 'in:orden_venta,facturacion,pago,boucher_despacho'],
        ]);

        // El Boucher Despacho acredita el envío al cliente: solo cabe en una venta Finalizada.
        if ($request->input('tipo') === 'boucher_despacho' && $cotizacion->motivo_no_vigente !== 'finalizada') {
            return response()->json([
                'message' => 'El Boucher Despacho solo se adjunta a cotizaciones No Vigentes con motivo Finalizada.',
                'errors' => ['tipo' => ['La cotización no está Finalizada.']],
            ], 422);
        }

        $file = $request->file('archivo');
        $path = $file->store('cotizaciones/' . $cotizacion->id, 'public');

        $archivo = \App\Models\CotizacionArchivo::create([
            'cotizacion_id' => $cotizacion->id,
            'tipo' => $request->input('tipo'),
            'nombre_archivo' => $file->getClientOriginalName(),
            'url' => \Illuminate\Support\Facades\Storage::url($path),
        ]);

        return response()->json($archivo, 201);
    }

    public function exportPdf(Request $request, Cotizacion $cotizacion)
    {
        // Qué fecha imprime el encabezado: "actual" (hoy, por defecto) o "creacion".
        // Solo se acepta este enum: el cliente no puede inyectar una fecha arbitraria.
        $data = $request->validate([
            'fecha' => ['nullable', 'in:actual,creacion'],
        ]);

        // Las cotizaciones antiguas pueden no tener fecha_creacion: caemos a created_at.
        $fechaPdf = ($data['fecha'] ?? null) === 'creacion'
            ? ($cotizacion->fecha_creacion ?? $cotizacion->created_at ?? now())
            : now();

        $cotizacion->load([
            'cliente.parent.comuna',
            'cliente.comuna',
            'oportunidad.contacto',
            'contacto',
            'cuerpo.region',
            'cuerpo.comuna',
            'compania.cuerpo.region',
            'compania.cuerpo.comuna',
            'ejecutivo',
            'estado',
            'detalles',
        ]);

        // Usar el ejecutivo almacenado en la cotización (o el usuario logueado como fallback)
        $vendedor = $cotizacion->ejecutivo ?? request()->user();

        // Ancla directa a Cuerpo/Compañía (esquema nuevo, sin cliente espejo)
        $cuerpo = $cotizacion->cuerpo ?? $cotizacion->compania?->cuerpo;

        if ($cuerpo) {
            $nombreClientePdf = 'Cuerpo de Bomberos de ' . $cuerpo->nombre;
            $rutClientePdf = $cuerpo->rut ?? 'Sin RUT';
            $direccionClientePdf = $cuerpo->direccion ?? '---';
            $giroClientePdf = $cuerpo->giro ?: '---';
            // La comuna sale del catálogo de comunas. Antes caía a la región, y
            // por eso las cotizaciones salían con "Región Metropolitana" en el
            // campo Comuna. Si el cuerpo aún no tiene comuna asignada, preferimos
            // dejarlo vacío antes que imprimir un dato incorrecto.
            $comunaClientePdf = $cuerpo->comuna->nombre ?? '';
        } elseif ($cotizacion->cliente) {
            // Esquema legacy: cliente-espejo con prefijo "CB " y jerarquía vía parent_id
            $cliente = $cotizacion->cliente;
            $parent = $cliente->parent; // Pre-loaded en load()

            // Si tiene parent (Cuerpo), usamos los datos del parent para facturación/encabezado
            $targetCliente = $parent ?? $cliente;

            $nombreClientePdf = $targetCliente->nombre_empresa ?? $targetCliente->nombre_contacto ?? 'Sin Nombre';
            $rutClientePdf = $targetCliente->rut_empresa ?? $targetCliente->rut ?? 'Sin RUT';
            $direccionClientePdf = $targetCliente->direccion ?? '---';
            $giroClientePdf = $targetCliente->giro ?? '---';

            // Comuna real del cliente (relación al catálogo de comunas). Nunca
            // caemos a región/provincia: eso imprimía una región donde debía ir
            // una comuna.
            $comunaClientePdf = $targetCliente->comuna->nombre ?? '';

            // Si es cuerpo de bomberos (CB ...), intentar expandir si el nombre es corto
            // Aplica tanto para cliente directo como para parent si viene abreviado
            if (str_starts_with($nombreClientePdf, 'CB ')) {
                $parte = substr($nombreClientePdf, 3); // "Antuco"
                $nombreClientePdf = "Cuerpo de Bomberos de " . $parte;

                // Si no tiene comuna, asumimos que es la parte del nombre
                if (empty($comunaClientePdf)) {
                    $comunaClientePdf = $parte;
                }
            }
        } else {
            // Sin cliente-espejo ni cuerpo/compañía: cotización anclada solo al contacto
            $nombreClientePdf = $cotizacion->contacto->nombre ?? 'Sin Nombre';
            $rutClientePdf = $cotizacion->contacto->rut ?? 'Sin RUT';
            $direccionClientePdf = '---';
            $giroClientePdf = '---';
            $comunaClientePdf = '';
        }

        // Usamos el facade de Barryvdh\DomPDF
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.cotizacion', [
            'cotizacion' => $cotizacion,
            'vendedor' => $vendedor,
            'nombreClientePdf' => $nombreClientePdf,
            'rutClientePdf' => $rutClientePdf,
            'direccionClientePdf' => $direccionClientePdf,
            'giroClientePdf' => $giroClientePdf,
            'comunaClientePdf' => $comunaClientePdf,
            'esPersonaNatural' => $cotizacion->tipo_destinatario === Cotizacion::DESTINATARIO_PERSONA_NATURAL,
            'fechaPdf' => $fechaPdf,
        ]);

        $nombreArchivo = "cotizacion_{$cotizacion->id}.pdf";

        return $pdf->stream($nombreArchivo);
    }

    public function storeGestion(Request $request, Cotizacion $cotizacion)
    {
        $data = $request->validate([
            'glosa' => ['nullable', 'string', 'max:255'],
            'fecha_vencimiento_nueva' => ['required', 'date'],
        ]);

        return DB::transaction(function () use ($request, $cotizacion, $data) {
            $cot = Cotizacion::lockForUpdate()->findOrFail($cotizacion->id);

            // Crear registro de gestión
            \App\Models\CotizacionGestion::create([
                'cotizacion_id' => $cot->id,
                'user_id' => $request->user() ? $request->user()->id : 1,
                'glosa' => $data['glosa'] ?? '',
                'fecha_gestion' => now(),
                'fecha_vencimiento_nueva' => $data['fecha_vencimiento_nueva'],
            ]);

            // Actualizar fecha de vencimiento de la cotización
            $cot->fecha_vencimiento = $data['fecha_vencimiento_nueva'];
            $cot->save();

            return response()->json(
                $cot->fresh()->load(['cliente.parent', 'oportunidad.contacto', 'ejecutivo', 'estado', 'detalles', 'historial', 'gestiones.user', 'ultimaGestion'])
            );
        });
    }

    public function savePersonalizacion(Request $request, Cotizacion $cotizacion)
    {
        QuotePersonalization::authorize();
        $data = $request->validate([
            'items' => 'present|array|max:500',
            'items.*.id' => 'required|integer|distinct',
            'items.*.grupos' => 'present|array',
            'items.*.kit' => 'sometimes|array',
            'enviar' => 'required|boolean',
        ]);
        return DB::transaction(function () use ($request, $cotizacion, $data) {
            $quote = Cotizacion::lockForUpdate()->findOrFail($cotizacion->id);
            QuotePersonalization::assertEditable($quote);
            $items = $quote->detalles()->lockForUpdate()->get()
                ->reject(fn ($item) => QuotePersonalization::informational($item))->keyBy('id');
            $expected = $items->keys()->sort()->values()->all();
            $received = collect($data['items'])->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
            abort_unless($expected === $received, 422, 'La lista de productos cambió o no se cargó completa. Vuelve a abrir la cotización antes de guardar.');
            foreach ($data['items'] as $input) {
                $item = $items[$input['id']];
                $config = \App\Services\KitPersonalization::isKit($item)
                    ? \App\Services\KitPersonalization::normalize($item, $input['kit'] ?? [], $data['enviar']) : $input['grupos'];
                $item->tipo_personalizacion = QuotePersonalization::validateConfig($item, $config, $data['enviar']);
                $item->configuracion = $config;
                $item->save();
            }
            $quote->update([
                'estado_personalizacion' => $data['enviar'] ? 'configured' : null,
                'personalizacion_completada' => $data['enviar'],
            ]);
            if ($data['enviar']) return $this->sendToPersonalizacion($request, $quote);
            return response()->json(['ok' => true, 'message' => 'Borrador guardado. Todavía no se ha enviado al taller.']);
        });
    }

    public function marcarPersonalizacionCompletada(Request $request, Cotizacion $cotizacion)
    {
        QuotePersonalization::authorize();
        return DB::transaction(function () use ($cotizacion) {
            $quote = Cotizacion::lockForUpdate()->findOrFail($cotizacion->id);
            QuotePersonalization::assertEditable($quote);
            foreach ($quote->detalles as $item) {
                if (QuotePersonalization::informational($item)) continue;
                QuotePersonalization::validateConfig($item, $item->configuracion ?? [], true);
            }
            $quote->update(['personalizacion_completada' => true, 'estado_personalizacion' => 'configured']);
            return response()->json(['ok' => true, 'cotizacion' => $quote]);
        });
    }

    public function sendToPersonalizacion(Request $request, Cotizacion $cotizacion)
    {
        QuotePersonalization::authorize();
        // 2. Wrap in transaction
        return DB::transaction(function () use ($cotizacion) {
            $cot = Cotizacion::lockForUpdate()->findOrFail($cotizacion->id);
            // Comprobar DESPUÉS de adquirir el bloqueo: un segundo envío no crea duplicados.
            QuotePersonalization::assertEditable($cot);
            abort_unless($cot->estado_personalizacion === 'configured', 422, 'La cotización no está configurada o ya fue enviada.');
            $botUserId = 1; // Fallback System User if Auth fails
            $currentUserId = auth()->id() ?? $botUserId;

            // Mapas para resolución de SKU (nombre => id)
            $mapGeneros = \App\Models\PteGenero::pluck('id', 'nombre')->toArray();
            $mapColores = \App\Models\PteColor::pluck('id', 'nombre')->toArray();
            $mapTallas = \App\Models\PteTalla::pluck('id', 'nombre')->toArray();

            // 3. For every detail with a configuracion, create a Kanban card in 'pendiente_diseno' (Por Hacer)
            $items = \App\Models\DetalleCotizacion::where('cotizacion_id', $cot->id)
                ->lockForUpdate()
                ->get();
            $createdCount = 0;

            foreach ($items as $item) {
                if (QuotePersonalization::informational($item)) continue;
                $parent = $item;
                foreach (\App\Services\KitPersonalization::operationalItems($parent) as $item) {
                    $item->tipo_personalizacion = QuotePersonalization::validateConfig($item, $item->configuracion ?? [], true);
                    // Determinamos el proceso inicial y el proceso encadenado
                    $initialType = 'bordado';
                    $nextProcess = null;

                    if ($item->tipo_personalizacion === 'none' || !$item->configuracion) {
                        continue;
                    }

                    if ($item->tipo_personalizacion === 'print' || $item->tipo_personalizacion === 'estampado') {
                        $initialType = 'estampado';
                    } elseif ($item->tipo_personalizacion === 'embroidery_then_print' || $item->tipo_personalizacion === 'bordado_luego_estampado') {
                        $initialType = 'bordado';
                        $nextProcess = 'estampado';
                    } elseif ($item->tipo_personalizacion === 'print_then_embroidery' || $item->tipo_personalizacion === 'estampado_luego_bordado') {
                        $initialType = 'estampado';
                        $nextProcess = 'bordado';
                    } elseif ($item->tipo_personalizacion === 'mixed' || $item->tipo_personalizacion === 'mixto') {
                        $initialType = 'mixto';
                        $nextProcess = 'mixto_pendiente';
                    }

                    $personalizacion = \App\Models\Personalizacion::create([
                        'usuario_creador_id' => $currentUserId,
                        'cotizacion_id' => $cot->id,
                        'numero_item' => $item->n_item,
                        'sku' => $item->kit_key ? $item->sku : $this->resolveFinalSku($item, $mapGeneros, $mapColores, $mapTallas),
                        'nombre_producto' => $item->producto ?? 'Personalización Desde Comercial',
                        'cantidad' => $item->cantidad,
                        'tipo' => $initialType,
                        'siguiente_proceso' => $nextProcess,
                        'estado' => 'pendiente_diseno',
                        'prioridad' => 'media',
                    ]);

                    // Volcar configuración JSON a tablas 3NF + guardar config completa para el Kanban
                    $config = $item->configuracion; // Es un array de grupos
                    $personalizacion->configuracion = ['grupos' => $config];
                    if ($item->kit_key) $personalizacion->configuracion = ['grupos' => $config, 'kit_origen' => ['item_id' => $parent->id, 'key' => $item->kit_key, 'kit' => $parent->producto, 'sku_generico_id' => $item->kit_definition['sku_generico_id']]];
                    $personalizacion->save();

                    foreach ($config as $grupo) {
                        // Variantes (Tallas/Colores)
                        if (!empty($grupo['tallas'])) {
                            foreach ($grupo['tallas'] as $t) {
                                \App\Models\PersonalizacionVariante::create([
                                    'personalizacion_id' => $personalizacion->id,
                                    'talla' => $t['talla'] ?? null,
                                    'color' => $t['color'] ?? null,
                                    'genero' => $t['genero'] ?? 'Unisex',
                                    'cantidad' => $t['cantidad'] ?? 0,
                                ]);
                            }
                        }

                        // Archivos (Matrices/Visuales) — logo images are stored in valor_fijo
                        if (!empty($grupo['matrices'])) {
                            foreach ($grupo['matrices'] as $m) {
                                $imageUrl = $m['valor_fijo'] ?? $m['image_url'] ?? null;
                                if (!empty($imageUrl) && is_string($imageUrl) && (str_starts_with($imageUrl, 'http') || str_starts_with($imageUrl, '/storage'))) {
                                    \App\Models\PersonalizacionArchivo::create([
                                        'personalizacion_id' => $personalizacion->id,
                                        'tipo_archivo' => 'mockup_visual',
                                        'url_archivo' => $imageUrl,
                                        'confirmado_fisico' => false,
                                    ]);
                                }
                            }
                        }

                        // Variables (Lista de Nombres) — preservar como archivo JSON de referencia
                        if (!empty($grupo['variables'])) {
                            // Guardar las variables como un registro de archivo con metadata
                            \App\Models\PersonalizacionArchivo::create([
                                'personalizacion_id' => $personalizacion->id,
                                'tipo_archivo' => 'lista_nombres',
                                'url_archivo' => json_encode($grupo['variables']),
                                'confirmado_fisico' => false,
                            ]);
                        }

                        // Si hay archivo adjunto de nombres/variables (planilla subida)
                        if (!empty($grupo['archivo_adjunto'])) {
                            \App\Models\PersonalizacionArchivo::create([
                                'personalizacion_id' => $personalizacion->id,
                                'tipo_archivo' => 'planilla_nombres',
                                'url_archivo' => $grupo['archivo_adjunto'],
                                'confirmado_fisico' => false,
                            ]);
                        }
                    }

                    $createdCount++;
                }
            }

            // 4. Mark the quote as sent to hide the dispatch button
            if ($createdCount > 0) {
                $cot->estado_personalizacion = 'sent_to_personalization';
                $message = "Se enviaron $createdCount ítem(s) al módulo de Personalización.";
            } else {
                $cot->estado_personalizacion = 'direct_delivery';
                $cot->personalizacion_completada = true;
                $message = "Cotización confirmada como Entrega Directa (sin personalización). Lista para despacho en bodega.";
                \App\Services\PersonalizationWorkflow::ensureDispatch($cot);
            }
            $cot->save();

            return response()->json([
                'ok' => true,
                'message' => $message,
                'cotizacion' => $cot->fresh()->load(['cliente.parent', 'oportunidad.contacto', 'ejecutivo', 'estado', 'detalles', 'historial', 'gestiones.user', 'ultimaGestion', 'archivos'])
            ]);
        });
    }

    public function anular(Request $request, Cotizacion $cotizacion)
    {
        $request->validate([
            'motivo' => 'required|string|max:' . NoVigente::MAX_OBSERVACION,
        ]);

        // Anular es salir del flujo con motivo "Anulada": la tarjeta va a No Vigentes.
        $estadoNoVigente = NoVigente::estado();

        if (!$estadoNoVigente) {
            return response()->json([
                'ok' => false,
                'message' => 'El estado "' . NoVigente::ESTADO . '" no existe en el sistema.'
            ], 422);
        }

        $estadoAnteriorId = $cotizacion->estado_id;
        $estadoAnterior = $cotizacion->estado;
        if ($estadoAnterior?->nombre === NoVigente::ESTADO || in_array($estadoAnterior?->nombre, NoVigente::ESTADOS_ANTERIORES, true)) {
            return response()->json([
                'ok' => false,
                'message' => 'La cotización ya no está vigente.',
            ], 422);
        }

        $cotizacion->update([
            'estado_id' => $estadoNoVigente->id,
            'motivo_no_vigente' => 'anulada',
            'motivo_no_vigente_otro' => null,
            'observacion_no_vigente' => $request->motivo,
            'fecha_no_vigente' => now(),
            'no_vigente_por_id' => auth()->id(),
        ]);

        // Registrar en historial
        $cotizacion->historial()->create([
            'user_id' => auth()->id(),
            'estado_anterior_id' => $estadoAnteriorId,
            'estado_nuevo_id' => $estadoNoVigente->id,
            'observaciones' => NoVigente::glosa('anulada', null, $request->motivo),
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Cotización anulada correctamente.',
            'cotizacion' => $cotizacion->load(['estado', 'historial.user', 'historial.estadoAnterior', 'historial.estadoNuevo'])
        ]);
    }
    private function resolveFinalSku($item, $mapGeneros, $mapColores, $mapTallas)
    {
        $sku = $item->sku ?? '';
        if (!$sku)
            return null;

        // Si ya tiene 15 dígitos y no es 'genérico' (no termina en 00000), lo dejamos tal cual
        if (strlen($sku) === 15 && !str_ends_with($sku, '00000')) {
            return $sku;
        }

        // Buscamos la primera configuración de variante disponible entre sus grupos
        $found = null;
        $config = $item->configuracion; // Array de grupos desde JSON
        if (!empty($config)) {
            foreach ($config as $grupo) {
                if (!empty($grupo['tallas'])) {
                    $found = (object) $grupo['tallas'][0];
                    break;
                }
            }
        }

        if (!$found) {
            return $sku; // Retorna el genérico si no hay nada configurado
        }

        // Resolución de IDs (nombres a IDs numéricos)
        $gId = $mapGeneros[$found->genero] ?? 3; // 1=M, 2=F, 3=U (default)
        $cId = $mapColores[$found->color] ?? 0;   // 0=Genérico
        $tId = $mapTallas[$found->talla] ?? 0;    // 0=Genérico

        // Construir el SKU final (10 base + 5 variante)
        $base = substr($sku, 0, 10);

        // El sufijo debe ser exactamente 5 dígitos: G CC TT
        $suffix = sprintf("%1d%02d%02d", (int) $gId, (int) $cId, (int) $tId);

        return $base . $suffix;
    }
}
