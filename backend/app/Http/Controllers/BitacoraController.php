<?php

namespace App\Http\Controllers;

use App\Models\PteMovimiento;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BitacoraController extends Controller
{
    private const PDF_LIMIT = 3000;

    /** Qué hizo el usuario que registró cada tipo de movimiento; el resto es "registro". */
    public const ROL_ACTOR = [
        'RESERVA_PRODUCTO' => 'solicito',
        'SALIDA_VENTA' => 'aprobo',
        'ANULACION_RESERVA' => 'rechazo',
    ];

    private function claveGrupo(object $row): string
    {
        return $row->id_lote.'|'.$row->usuario_id.'|'.$row->solicitante_id;
    }

    /**
     * Estado de cada solicitud de reserva de la página y quién la resolvió.
     * Dos consultas por página, sin importar cuántas filas haya.
     */
    private function resolucionesDeSolicitudes($rows): array
    {
        $reservas = $rows->filter(fn ($row) => $row->tipo_movimiento === 'RESERVA_PRODUCTO' && $row->id_lote && $row->solicitante_id !== null);
        if ($reservas->isEmpty()) {
            return [];
        }

        $peticiones = DB::table('pte_movimientos as pm')
            ->join('peticiones as p', 'p.id', '=', 'pm.peticion_id')
            ->leftJoin('estados as e', 'e.id', '=', 'p.estado_id')
            ->where('pm.tipo_movimiento', 'RESERVA_PRODUCTO')
            ->whereIn('pm.id_lote', $reservas->pluck('id_lote')->unique()->values())
            ->get(['pm.id_lote', 'pm.usuario_id', 'p.user_id as solicitante_id', 'p.id as peticion_id', 'e.nombre as estado']);

        $resueltas = DB::table('pte_movimientos as pm')
            ->leftJoin('users as u', 'u.id', '=', 'pm.usuario_id')
            ->whereIn('pm.peticion_id', $peticiones->pluck('peticion_id')->unique()->values())
            ->whereIn('pm.tipo_movimiento', ['SALIDA_VENTA', 'ANULACION_RESERVA'])
            ->get(['pm.peticion_id', 'pm.fecha_hora', 'u.name'])
            ->groupBy('peticion_id');

        return $peticiones->groupBy(fn ($p) => $this->claveGrupo($p))->map(function ($grupo) use ($resueltas) {
            $estados = $grupo->pluck('estado')->map(fn ($estado) => match ($estado) {
                'Despachado', 'Aceptado' => 'aprobada',
                'Rechazado' => 'rechazada',
                default => 'pendiente',
            })->unique();
            $resolucion = $grupo->flatMap(fn ($p) => $resueltas->get($p->peticion_id, collect()));
            $ultima = $resolucion->max('fecha_hora');

            return [
                'estado_solicitud' => $estados->count() === 1 ? $estados->first() : 'parcial',
                'resuelto_por' => $resolucion->pluck('name')->filter()->unique()->implode(', ') ?: null,
                'resuelto_en' => $ultima ? CarbonImmutable::parse($ultima)->format('d/m/Y H:i') : null,
            ];
        })->all();
    }

    private function period(Request $request): array
    {
        $request->validate([
            'mes' => ['sometimes', 'required', 'date_format:Y-m'],
            'semana' => ['nullable', 'integer', 'min:1', 'max:6'],
            'fecha' => ['nullable', 'date_format:Y-m-d'],
            'tipo' => ['nullable', 'string', 'max:80'],
            'usuario_id' => ['nullable', 'string', 'regex:/^(?:[1-9][0-9]*|sistema)$/'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);
        $month = $request->input('mes', now()->format('Y-m'));
        $start = CarbonImmutable::createFromFormat('!Y-m', $month);
        $end = $start->addMonth();
        if ($request->filled('semana')) {
            $weekStart = $start->startOfWeek(\Carbon\CarbonInterface::MONDAY)->addWeeks($request->integer('semana') - 1);
            $weekEnd = $weekStart->addWeek();
            $start = $start->max($weekStart);
            $end = $end->min($weekEnd);
            abort_unless($start->lt($end), 422, 'La semana no pertenece al mes seleccionado.');
        }
        if ($request->filled('fecha')) {
            abort_unless(str_starts_with($request->fecha, $month.'-'), 422, 'El día debe pertenecer al mes seleccionado.');
            $day = CarbonImmutable::parse($request->fecha)->startOfDay();
            abort_unless($day->gte($start) && $day->lt($end), 422, 'El día debe pertenecer a la semana seleccionada.');
            $start = $day;
            $end = $start->addDay();
        }
        return [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')];
    }

    public function usuarios(Request $request)
    {
        // Options cover the full month, independent of pagination and other filters.
        $request->validate(['mes' => ['required', 'date_format:Y-m']]);
        $start = CarbonImmutable::createFromFormat('!Y-m', $request->mes);
        $end = $start->addMonth();

        $authors = DB::table('pte_movimientos')
            ->where('fecha_hora', '>=', $start->format('Y-m-d H:i:s'))
            ->where('fecha_hora', '<', $end->format('Y-m-d H:i:s'))
            ->select('usuario_id')->distinct();

        $requesters = DB::table('pte_movimientos as pm')
            ->join('peticiones as p', 'p.id', '=', 'pm.peticion_id')
            ->where('pm.fecha_hora', '>=', $start->format('Y-m-d H:i:s'))
            ->where('pm.fecha_hora', '<', $end->format('Y-m-d H:i:s'))
            ->whereNotNull('p.user_id')
            ->select('p.user_id as usuario_id')->distinct();

        // Historial sin solicitud vinculada: el solicitante es el dueño de la cotización.
        $legacyRequesters = DB::table('pte_movimientos as pm')
            ->join('cotizaciones as c', 'c.id', '=', 'pm.cotizacion_id')
            ->where('pm.fecha_hora', '>=', $start->format('Y-m-d H:i:s'))
            ->where('pm.fecha_hora', '<', $end->format('Y-m-d H:i:s'))
            ->whereNull('pm.peticion_id')
            ->whereNotNull('c.user_id')
            ->select('c.user_id as usuario_id')->distinct();

        $allAuthors = $authors->union($requesters)->union($legacyRequesters);

        $users = DB::query()->fromSub($allAuthors, 'authors')
            ->leftJoin('users as u', 'u.id', '=', 'authors.usuario_id')
            ->select('authors.usuario_id', 'u.name')->orderBy('u.name')->orderBy('authors.usuario_id')->get()
            ->map(fn ($user) => [
                'value' => $user->usuario_id === null ? 'sistema' : (string) $user->usuario_id,
                'label' => $user->usuario_id === null ? 'Sistema / sin usuario' : ($user->name ?? 'Usuario no disponible'),
            ]);
        return response()->json(['data' => $users]);
    }

    public function historialGeneral(Request $request)
    {
        [$start, $end] = $this->period($request);
        $minute = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m-%d %H:%M', fecha_hora)"
            : "DATE_FORMAT(fecha_hora, '%Y-%m-%d %H:%i')";
        // Filter before grouping: the existing fecha_hora index remains usable.
        $groups = DB::table('pte_movimientos as pm')
            ->leftJoin('cotizaciones as c_filter', 'c_filter.id', '=', 'pm.cotizacion_id')
            ->leftJoin('peticiones as p_req', 'p_req.id', '=', 'pm.peticion_id')
            ->where('pm.fecha_hora', '>=', $start)->where('pm.fecha_hora', '<', $end)
            ->when($request->filled('tipo'), fn ($q) => $q->where('pm.tipo_movimiento', $request->tipo))
            ->when($request->filled('usuario_id'), function ($q) use ($request) {
                if ($request->usuario_id === 'sistema') {
                    $q->whereNull('pm.usuario_id');
                } else {
                    $userId = $request->usuario_id;
                    // Quien registró, quien solicitó la reserva o, sin solicitud vinculada, el dueño de la cotización.
                    $q->where(function ($sub) use ($userId) {
                        $sub->where('pm.usuario_id', $userId)
                            ->orWhere('p_req.user_id', $userId)
                            ->orWhere(fn ($legacy) => $legacy->whereNull('pm.peticion_id')->where('c_filter.user_id', $userId));
                    });
                }
            })
            ->select('pm.id_lote', 'pm.tipo_movimiento', 'pm.usuario_id', 'pm.cotizacion_id', 'pm.referencia_externa', 'pm.cliente_nombre', 'p_req.user_id as solicitante_id')
            ->selectRaw("$minute as fecha_minuto, MIN(pm.id) as movimiento_id, MAX(pm.fecha_hora) as ultima_fecha, MIN(pm.sku) as primer_sku, COUNT(*) as total_registros, SUM(pm.cantidad) as total_unidades")
            ->groupBy('pm.id_lote', 'pm.tipo_movimiento', 'pm.usuario_id', 'pm.cotizacion_id', 'pm.referencia_externa', 'pm.cliente_nombre', 'p_req.user_id', 'fecha_minuto');
        $total = DB::query()->fromSub(clone $groups, 'g')->count();
        $perPage = (int) $request->input('per_page', 25);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min((int) $request->input('page', 1), $lastPage);
        $concatCuerpoCo = DB::connection()->getDriverName() === 'sqlite'
            ? "'Cuerpo de Bomberos de ' || cu.nombre || ' – ' || co.nombre"
            : "CONCAT('Cuerpo de Bomberos de ', cu.nombre, ' – ', co.nombre)";
        $concatCuerpo = DB::connection()->getDriverName() === 'sqlite'
            ? "'Cuerpo de Bomberos de ' || cu.nombre"
            : "CONCAT('Cuerpo de Bomberos de ', cu.nombre)";
        $concatCuCo = DB::connection()->getDriverName() === 'sqlite'
            ? "'Cuerpo de Bomberos de ' || cu_co.nombre || ' – ' || co.nombre"
            : "CONCAT('Cuerpo de Bomberos de ', cu_co.nombre, ' – ', co.nombre)";

        $rows = DB::query()->fromSub($groups, 'g')
            ->leftJoin('users as u', 'u.id', '=', 'g.usuario_id')
            ->leftJoin('cotizaciones as c', 'c.id', '=', 'g.cotizacion_id')
            ->leftJoin('users as solicitante_u', 'solicitante_u.id', '=', 'c.user_id')
            ->leftJoin('users as solicitante_p', 'solicitante_p.id', '=', 'g.solicitante_id')
            ->leftJoin('clientes as cl', 'cl.id', '=', 'c.cliente_id')
            ->leftJoin('cuerpos as cu', 'cu.id', '=', 'c.cuerpo_id')
            ->leftJoin('companias as co', 'co.id', '=', 'c.compania_id')
            ->leftJoin('cuerpos as cu_co', 'cu_co.id', '=', 'co.cuerpo_id')
            ->leftJoin('contactos as ct', 'ct.id', '=', 'c.contacto_id')
            ->leftJoin('pte_skus as s', 's.sku', '=', 'g.primer_sku')
            ->select('g.*', 'u.name as usuario', 'solicitante_p.name as solicitante_peticion', 'solicitante_u.name as solicitante_cotizacion', 's.nombre as producto_resumen', 'c.numero as cotizacion_numero')
            ->selectRaw("COALESCE(
                NULLIF(g.cliente_nombre, ''),
                CASE 
                    WHEN cu.nombre IS NOT NULL AND co.nombre IS NOT NULL THEN {$concatCuerpoCo}
                    WHEN cu.nombre IS NOT NULL THEN {$concatCuerpo}
                    WHEN cu_co.nombre IS NOT NULL AND co.nombre IS NOT NULL THEN {$concatCuCo}
                    WHEN co.nombre IS NOT NULL THEN co.nombre
                    ELSE NULL
                END,
                cl.nombre_empresa,
                ct.nombre
            ) as cliente_mostrado")
            ->orderByDesc('ultima_fecha')->orderByDesc('movimiento_id')
            ->offset(($page - 1) * $perPage)->limit($perPage)->get();

        $resoluciones = $this->resolucionesDeSolicitudes($rows);

        $rows = $rows
            ->map(fn ($row) => [
                'movimiento_id' => (int) $row->movimiento_id,
                'id_lote' => $row->id_lote,
                'fecha_hora_grupo' => CarbonImmutable::parse($row->ultima_fecha)->format('d/m/Y H:i'),
                'tipo_movimiento' => $row->tipo_movimiento,
                'usuario' => $row->usuario ?? 'Sistema',
                'rol_actor' => self::ROL_ACTOR[$row->tipo_movimiento] ?? 'registro',
                // Con solicitud vinculada: quien pidió la reserva. Sin ella (historial): el dueño de la cotización.
                'solicitante' => $row->solicitante_id !== null ? $row->solicitante_peticion : ($row->solicitante_cotizacion ?? null),
                ...($row->tipo_movimiento === 'RESERVA_PRODUCTO'
                    ? ($resoluciones[$this->claveGrupo($row)] ?? ['estado_solicitud' => null, 'resuelto_por' => null, 'resuelto_en' => null])
                    : []),
                'cotizacion_id' => $row->cotizacion_id,
                'cotizacion_numero' => $row->cotizacion_numero ?: ($row->cotizacion_id ? (string) $row->cotizacion_id : ($row->referencia_externa ?: null)),
                'referencia_externa' => $row->referencia_externa,
                'cliente_nombre' => $row->cliente_mostrado,
                'producto_resumen' => $row->producto_resumen ?? 'Producto no disponible',
                'total_registros' => (int) $row->total_registros,
                'total_unidades' => (int) $row->total_unidades,
                'items' => null,
            ]);
        return response()->json(['data' => $rows, 'pagination' => [
            'current_page' => $page, 'per_page' => $perPage, 'total' => $total, 'last_page' => $lastPage,
        ]]);
    }

    // A persisted anchor identifies the complete group, including legacy null lot/user IDs.
    public function loteQuery(Request $request)
    {
        $request->validate(['movimiento_id' => ['required', 'integer', 'min:1']]);
        $anchor = PteMovimiento::findOrFail($request->integer('movimiento_id'));
        $requesterId = $anchor->peticion?->user_id;
        $start = CarbonImmutable::parse($anchor->fecha_hora)->startOfMinute();
        return PteMovimiento::query()
            ->where('id_lote', $anchor->id_lote)->where('usuario_id', $anchor->usuario_id)
            // Match the same requester partition used by historialGeneral, including legacy nulls.
            ->when($requesterId !== null,
                fn ($q) => $q->whereHas('peticion', fn ($p) => $p->where('user_id', $requesterId)),
                fn ($q) => $q->whereDoesntHave('peticion', fn ($p) => $p->whereNotNull('user_id')))
            ->where('cotizacion_id', $anchor->cotizacion_id)->where('tipo_movimiento', $anchor->tipo_movimiento)
            ->where('referencia_externa', $anchor->referencia_externa)
            ->where('cliente_nombre', $anchor->cliente_nombre)
            ->where('fecha_hora', '>=', $start->format('Y-m-d H:i:s'))
            ->where('fecha_hora', '<', $start->addMinute()->format('Y-m-d H:i:s'));
    }

    public function loteItems(Request $request)
    {
        $request->validate(['page' => ['sometimes', 'integer', 'min:1']]);
        $paginator = $this->loteQuery($request)
            ->with(['skuProducto.talla', 'skuProducto.color', 'cotizacion:id,numero'])
            ->orderBy('fecha_hora')->orderBy('id')->paginate(25);
        return response()->json([
            'data' => $paginator->getCollection()->map(fn ($mov) => [
                'id' => $mov->id, 'sku' => $mov->sku,
                'producto' => $mov->skuProducto?->nombre ?? 'Producto no disponible',
                'talla' => $mov->skuProducto?->talla?->nombre ?? '-',
                'color' => $mov->skuProducto?->color?->nombre ?? '-',
                'cantidad' => $mov->cantidad,
                'saldo_anterior' => $mov->saldo_anterior, 'saldo_nuevo' => $mov->saldo_nuevo,
                'cotizacion_id' => $mov->cotizacion_id,
                'cotizacion_numero' => $mov->cotizacion?->numero ?: ($mov->cotizacion_id ? (string) $mov->cotizacion_id : ($mov->referencia_externa ?: null)),
                'referencia_externa' => $mov->referencia_externa,
                'cliente_nombre' => $mov->cliente_nombre,
            ]),
            'pagination' => ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'total' => $paginator->total(), 'per_page' => 25],
        ]);
    }

    public function disabledGeneralPdf()
    {
        return response()->json(['message' => 'El PDF general fue deshabilitado. Selecciona un mes en la bitácora para descargar su PDF.'], 410);
    }

    public function monthlyPdf(Request $request)
    {
        $request->validate(['mes' => ['required', 'date_format:Y-m'], 'fecha' => ['prohibited']]);
        [$start, $end] = $this->period($request);
        $query = PteMovimiento::query()->where('fecha_hora', '>=', $start)->where('fecha_hora', '<', $end)
            ->when($request->filled('tipo'), fn ($q) => $q->where('tipo_movimiento', $request->tipo))
            ->when($request->filled('usuario_id'), function ($q) use ($request) {
                if ($request->usuario_id === 'sistema') {
                    $q->whereNull('usuario_id');
                } else {
                    $userId = $request->usuario_id;
                    // Mismo criterio que la bitácora en pantalla.
                    $q->where(function ($sub) use ($userId) {
                        $sub->where('usuario_id', $userId)
                            ->orWhereHas('peticion', fn ($pq) => $pq->where('user_id', $userId))
                            ->orWhere(fn ($legacy) => $legacy->whereNull('peticion_id')
                                ->whereHas('cotizacion', fn ($cq) => $cq->where('user_id', $userId)));
                    });
                }
            });
        // Bound DomPDF memory. Never silently truncate a monthly audit report.
        $count = (clone $query)->limit(self::PDF_LIMIT + 1)->pluck('id')->count();
        abort_if($count === 0, 404, 'No hay movimientos con los filtros seleccionados.');
        abort_if($count > self::PDF_LIMIT, 422, 'El período supera los 3.000 movimientos por PDF. Selecciona una semana o filtra por tipo de movimiento.');
        $movimientos = $query->with(['skuProducto.talla', 'usuario:id,name', 'peticion.user:id,name', 'cotizacion.ejecutivo:id,name', 'cotizacion.cliente', 'cotizacion.cuerpo', 'cotizacion.compania.cuerpo', 'cotizacion.contacto'])
            ->orderBy('fecha_hora')->orderBy('id')->limit(self::PDF_LIMIT + 1)->get();
        abort_if($movimientos->count() > self::PDF_LIMIT, 422, 'El período supera los 3.000 movimientos por PDF. Selecciona una semana o filtra por tipo.');
        return Pdf::loadView('pdf.bitacora_mensual', [
            'movimientos' => $movimientos, 'mes' => $request->mes,
            'tipo' => $request->input('tipo') ?: 'Todos los tipos',
            'semana' => $request->integer('semana') ?: null,
            'periodo' => CarbonImmutable::parse($start)->format('d/m/Y').' al '.CarbonImmutable::parse($end)->subDay()->format('d/m/Y'),
        ])->setPaper('a4', 'landscape')->download('bitacora-'.$request->mes.($request->filled('semana') ? '-semana-'.$request->integer('semana') : '').'.pdf');
    }

    public function weeklyPdf(Request $request)
    {
        $request->validate(['semana' => ['required', 'integer', 'min:1', 'max:6']]);
        return $this->monthlyPdf($request);
    }
}
