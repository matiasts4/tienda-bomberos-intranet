<?php

namespace App\Http\Controllers;

use App\Models\Contacto;
use App\Models\ContactoActividad;
use App\Models\Cuerpo;
use App\Models\Estado;
use App\Models\Oportunidad;
use App\Models\Cargo;
use App\Models\Compania;
use App\Models\ProspeccionContacto;
use App\Models\ProspeccionPausa;
use App\Models\ProspeccionTipo;
use App\Support\ArbolProspeccion;
use App\Support\ColaProspeccion;
use App\Support\ResultadoGestion;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProspeccionController extends Controller
{
    /**
     * GET /api/prospeccion/contactos
     *
     * Contactos de la cola, paginados. Además de q/filtro/mes acepta los filtros
     * de la jerarquía (cuerpo_id, compania_id, sin_compania, sin_cuerpo): así el
     * árbol pide solo los contactos de la hoja que se abre.
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 50);
        $perPage = max(1, min($perPage, 200));

        $cola = ColaProspeccion::desde($request);
        [$prioridadSql, $prioridadBindings] = $cola->prioridadSql();

        $results = $cola->contactos()
            // cliente: la empresa de un contacto que no es de la Red de Bomberos (la ficha la muestra).
            ->with(['cuerpos.region', 'companias.cuerpo', 'cliente:id,nombre_empresa,tipo'])
            ->select(
                'contactos.*',
                'pt.slug as ultima_tipo',
                'pc_latest.proxima_llamada as proxima_llamada',
                'pc_latest.notas as ultima_nota',
                'pc_latest.created_at as ultima_interaccion_at',
                'pc_latest.user_id as ultimo_user_id',
                'pc_latest.hora as proxima_hora',
                'pc_latest.objetivo as proximo_objetivo',
                'pc_latest.intento as intentos',
                'pc_latest.motivo as ultimo_motivo',
            )
            ->selectRaw($prioridadSql, $prioridadBindings)
            // Dentro de cada prioridad, lo agendado antes va primero (fecha y hora).
            ->orderBy('prioridad')
            ->orderBy('pc_latest.proxima_llamada')
            ->orderBy('pc_latest.hora')
            ->orderBy('contactos.nombre')
            ->orderBy('contactos.id')
            ->paginate($perPage);

        return response()->json($results);
    }

    /**
     * GET /api/prospeccion/contactos/counts
     *
     * Cuántos contactos hay en cada balde del selector (una sola consulta agregada).
     */
    public function counts(Request $request)
    {
        return response()->json(ColaProspeccion::desde($request)->conteos());
    }

    /**
     * GET /api/prospeccion/arbol
     *
     * Regiones y cuerpos con cuántos contactos de la cola tiene cada uno, según
     * los mismos q/filtro/mes del listado. Las compañías se piden al abrir un cuerpo.
     */
    public function arbol(Request $request)
    {
        return response()->json((new ArbolProspeccion(ColaProspeccion::desde($request)))->regiones());
    }

    /**
     * GET /api/prospeccion/arbol/cuerpos/{cuerpo}
     */
    public function arbolCuerpo(Request $request, Cuerpo $cuerpo)
    {
        return response()->json((new ArbolProspeccion(ColaProspeccion::desde($request)))->cuerpo($cuerpo));
    }

    /**
     * POST /api/prospeccion/contactos/{contacto}/interaccion
     *
     * Registra el resultado de una llamada o mensaje:
     *  - sin respuesta (no_contesta, buzon): suma un intento a la cadencia; con
     *    ColaProspeccion::MAX_INTENTOS seguidos el contacto queda inalcanzable;
     *  - llamar_luego (reagendado sin contacto) y agendar_seguimiento (acordado
     *    en una conversación, exige fecha): aceptan hora y objetivo;
     *  - no_califica (no interesado): motivo, fecha para volver a intentar (vacía
     *    = no volver) y, con `pausar_compania`, pausa a toda la cuenta hasta ese día;
     *  - numero_equivocado: saca al contacto hasta que se corrijan sus datos.
     * Calificar y derivar tienen endpoints propios porque crean más registros.
     */
    public function interaccion(Request $request, Contacto $contacto)
    {
        $data = $request->validate([
            'tipo' => ['required', 'string', 'exists:prospeccion_tipos,slug', Rule::notIn(['calificado', 'derivado'])],
            'canal' => ['nullable', 'string', 'max:50'],
            'notas' => ['nullable', 'string', 'max:1000'],
            'proxima_llamada' => [
                Rule::requiredIf(fn () => in_array($request->input('tipo'), ResultadoGestion::TIPOS_CON_FECHA, true)),
                'nullable', 'date', 'after_or_equal:today',
            ],
            'hora' => ['nullable', 'date_format:H:i'],
            'objetivo' => ['nullable', 'string', 'max:120'],
            'motivo' => ['nullable', Rule::in(array_keys(ResultadoGestion::MOTIVOS_NO_INTERES))],
            'pausar_compania' => ['nullable', 'boolean'],
        ]);

        $tipo = ProspeccionTipo::where('slug', $data['tipo'])->firstOrFail();
        $esNoInteres = $tipo->slug === 'no_califica';
        $pausar = $esNoInteres && $request->boolean('pausar_compania');

        if ($pausar && empty($data['proxima_llamada'])) {
            throw ValidationException::withMessages([
                'proxima_llamada' => 'Indica cuándo volver a intentar: la compañía queda pausada hasta ese día.',
            ]);
        }

        $canal = $data['canal'] ?? 'Teléfono';
        // Un "no" sin motivo viene de una versión anterior del frontend.
        $motivo = $esNoInteres ? ($data['motivo'] ?? 'otro') : null;

        return DB::transaction(function () use ($request, $contacto, $data, $tipo, $canal, $motivo, $pausar) {
            $interaccion = ProspeccionContacto::create([
                'contacto_id' => $contacto->id,
                'user_id' => $request->user()?->id,
                'tipo_id' => $tipo->id,
                'canal' => $canal,
                'notas' => $data['notas'] ?? null,
                'proxima_llamada' => $data['proxima_llamada'] ?? null,
                'hora' => $data['hora'] ?? null,
                'objetivo' => $data['objetivo'] ?? null,
                'motivo' => $motivo,
                'intento' => $this->siguienteIntento($contacto, $tipo->slug),
            ]);

            $pausa = $pausar ? $this->pausarCuenta($contacto, $interaccion, $request->user()?->id) : null;

            ContactoActividad::registrar(
                $contacto,
                ContactoActividad::ACCION_GESTION,
                $this->descripcionGestion($tipo, $canal, $motivo, $interaccion->intento),
                array_filter([
                    'tipo' => $tipo->slug,
                    'canal' => $canal,
                    'notas' => $data['notas'] ?? null,
                    'proxima_llamada' => $data['proxima_llamada'] ?? null,
                    'hora' => $data['hora'] ?? null,
                    'objetivo' => $data['objetivo'] ?? null,
                    'motivo' => $motivo,
                    'intento' => $interaccion->intento,
                    'pausa_id' => $pausa?->id,
                    'interaccion_id' => $interaccion->id,
                ], fn ($v) => $v !== null),
                'prospeccion'
            );

            // La proxima_llamada se renderiza automáticamente en el calendario
            // vía EventoController (query de ProspeccionContacto), no es necesario
            // crear un Evento adicional — eso causaba duplicados.

            return response()->json([
                'ok' => true,
                'interaccion' => $interaccion,
                'pausa' => $pausa,
            ], 201);
        });
    }

    /**
     * POST /api/prospeccion/contactos/{contacto}/derivar
     *
     * "No soy yo, habla con el capitán": el contacto sale de la cola (derivado) y
     * el destino —uno existente o uno nuevo de la misma cuenta— queda con un
     * seguimiento agendado, para llamarlo de parte de quien lo recomendó.
     */
    public function derivar(Request $request, Contacto $contacto)
    {
        $data = $request->validate([
            'derivado_a_id' => ['nullable', 'integer', 'exists:contactos,id', Rule::notIn([$contacto->id])],
            'nuevo' => ['required_without:derivado_a_id', 'nullable', 'array'],
            'nuevo.nombre' => ['required_with:nuevo', 'nullable', 'string', 'max:255'],
            'nuevo.cargo_id' => ['nullable', 'integer', 'exists:cargos,id'],
            'nuevo.telefono' => ['nullable', 'string', 'max:50'],
            'nuevo.email' => ['nullable', 'email', 'max:255'],
            'proxima_llamada' => ['required', 'date', 'after_or_equal:today'],
            'hora' => ['nullable', 'date_format:H:i'],
            'notas' => ['nullable', 'string', 'max:1000'],
            'canal' => ['nullable', 'string', 'max:50'],
        ]);

        $canal = $data['canal'] ?? 'Teléfono';
        $userId = $request->user()?->id;

        return DB::transaction(function () use ($contacto, $data, $canal, $userId) {
            $destino = !empty($data['derivado_a_id'])
                ? Contacto::findOrFail($data['derivado_a_id'])
                : $this->crearContactoEnLaMismaCuenta($contacto, $data['nuevo']);

            $derivacion = ProspeccionContacto::create([
                'contacto_id' => $contacto->id,
                'user_id' => $userId,
                'tipo_id' => ProspeccionTipo::where('slug', 'derivado')->value('id'),
                'canal' => $canal,
                'notas' => $data['notas'] ?? null,
                'derivado_a_id' => $destino->id,
            ]);

            $objetivo = mb_substr('Derivado por ' . $contacto->nombre, 0, 120);
            $seguimiento = ProspeccionContacto::create([
                'contacto_id' => $destino->id,
                'user_id' => $userId,
                'tipo_id' => ProspeccionTipo::where('slug', 'agendar_seguimiento')->value('id'),
                'canal' => $canal,
                'notas' => $data['notas'] ?? null,
                'proxima_llamada' => $data['proxima_llamada'],
                'hora' => $data['hora'] ?? null,
                'objetivo' => $objetivo,
            ]);

            ContactoActividad::registrar(
                $contacto,
                ContactoActividad::ACCION_GESTION,
                'Derivó la conversación a ' . $destino->nombre,
                ['tipo' => 'derivado', 'derivado_a_id' => $destino->id, 'interaccion_id' => $derivacion->id],
                'prospeccion'
            );
            ContactoActividad::registrar(
                $destino,
                ContactoActividad::ACCION_GESTION,
                'Recibió un seguimiento derivado por ' . $contacto->nombre,
                [
                    'tipo' => 'agendar_seguimiento',
                    'proxima_llamada' => $data['proxima_llamada'],
                    'hora' => $data['hora'] ?? null,
                    'interaccion_id' => $seguimiento->id,
                ],
                'prospeccion'
            );

            return response()->json([
                'ok' => true,
                'derivacion' => $derivacion,
                'destino' => $destino->load(['cuerpos.region', 'companias.cuerpo']),
            ], 201);
        });
    }

    /**
     * DELETE /api/prospeccion/pausas/{pausa}
     *
     * Reactiva la cuenta antes de tiempo (por ejemplo, cambió la directiva).
     */
    public function levantarPausa(ProspeccionPausa $pausa)
    {
        if (!$pausa->levantada_at) {
            $pausa->update(['levantada_at' => now()]);
        }

        return response()->json(['ok' => true, 'pausa' => $pausa]);
    }

    /**
     * POST /api/prospeccion/contactos/{contacto}/calificar
     */
    public function calificar(Request $request, Contacto $contacto)
    {
        $data = $request->validate([
            // Lo mínimo para que la oportunidad llegue cotizable: qué necesita.
            'necesidad' => ['nullable', 'string', 'max:500'],
            'notas' => ['required_without:necesidad', 'nullable', 'string', 'max:1000'],
            'cantidad' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'plazo' => ['nullable', Rule::in(array_keys(ResultadoGestion::PLAZOS))],
            'presupuesto' => ['nullable', Rule::in(array_keys(ResultadoGestion::PRESUPUESTOS))],
            'decisor' => ['nullable', 'string', 'max:120'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'canal' => ['nullable', 'string', 'max:50'],
        ]);

        $calificacion = array_filter(
            array_intersect_key($data, array_flip(['necesidad', 'cantidad', 'plazo', 'presupuesto', 'decisor'])),
            fn ($v) => $v !== null && $v !== ''
        );
        $data['notas'] = ResultadoGestion::resumenCalificacion($data);

        return DB::transaction(function () use ($request, $contacto, $data, $calificacion) {
            $tipoCalificado = ProspeccionTipo::where('slug', 'calificado')->first();

            // 1. Log the interaction as 'calificado'
            ProspeccionContacto::create([
                'contacto_id' => $contacto->id,
                'user_id' => $request->user()?->id,
                'tipo_id' => $tipoCalificado?->id,
                'notas' => $data['notas'],
                'canal' => $data['canal'] ?? 'Teléfono',
                'proxima_llamada' => null,
                'datos' => $calificacion ?: null,
            ]);

            // 2. Find the "Nuevo" state for oportunidades
            $estadoNuevo = Estado::where('scope', 'oportunidad')
                ->where('nombre', 'Nuevo')
                ->first() ?: Estado::where('scope', 'oportunidad')->orderBy('orden')->first();

            if (!$estadoNuevo) {
                return response()->json(['error' => 'No hay estados de oportunidad configurados.'], 422);
            }

            // 3. Create the Oportunidad (Normalized: no duplication of contact fields)
            // Si el contacto tiene compañía, esta ya trae su cuerpo; si solo tiene
            // cuerpo (caso más común, ver ImportBomberosSeeder), se usa ese.
            $primeraCompania = $contacto->companias()->first();
            $primerCuerpo = $primeraCompania?->cuerpo_id ? null : $contacto->cuerpos()->first();

            $oportunidad = Oportunidad::create([
                'fecha_ingreso' => now()->toDateString(),
                'cliente_id' => $contacto->cliente_id,
                'contacto_id' => $contacto->id,
                'cuerpo_id' => $primeraCompania?->cuerpo_id ?? $primerCuerpo?->id,
                'compania_id' => $primeraCompania?->id,
                'user_id' => $data['user_id'],
                'creado_por_id' => $request->user()?->id ?? $data['user_id'],
                'estado_id' => $estadoNuevo->id,
                'canal' => $data['canal'] ?? 'Teléfono',
                'mensaje_prospeccion' => $data['notas'],
                'referido_por' => 'Prospección - ' . ($request->user()?->name ?? 'Sistema'),
            ]);

            // Add a gestion
            \App\Models\OportunidadGestion::create([
                'oportunidad_id' => $oportunidad->id,
                'user_id' => $request->user()?->id,
                'tipo_contacto' => 'llamada',
                'glosa' => 'Calificado desde Módulo de Prospección: ' . $data['notas'],
                'fecha_gestion' => now(),
            ]);

            ContactoActividad::registrar(
                $contacto,
                ContactoActividad::ACCION_CALIFICADO,
                'Calificó el contacto y creó la oportunidad #' . $oportunidad->id,
                [
                    'oportunidad_id' => $oportunidad->id,
                    'notas' => $data['notas'],
                    'ejecutivo_asignado_id' => $data['user_id'],
                ],
                'prospeccion'
            );

            return response()->json([
                'ok' => true,
                'oportunidad_id' => $oportunidad->id,
                'oportunidad' => $oportunidad->load(['cliente', 'contacto', 'cuerpo', 'compania', 'estado']),
            ], 201);
        });
    }

    /**
     * GET /api/prospeccion/metricas
     */
    public function metricas(Request $request)
    {
        $userId = $request->user()?->id;

        $mes = $request->query('mes', date('Y-m'));
        $partes = explode('-', $mes);
        $year = intval($partes[0] ?? date('Y'));
        $month = intval($partes[1] ?? date('m'));

        $baseQuery = ProspeccionContacto::whereYear('created_at', $year)
                                        ->whereMonth('created_at', $month);

        if ($userId && $request->user()?->role !== 'superadmin') {
            $baseQuery->where('user_id', $userId);
        }

        $llamadasMes = (clone $baseQuery)->count();

        // Contacto efectivo = hubo conversación. Reagendar sin hablar ("llamar
        // luego") o un número equivocado no lo son.
        $contactadas = (clone $baseQuery)
            ->whereHas('tipo', fn ($q) => $q->whereIn('slug', ColaProspeccion::TIPOS_EFECTIVOS))
            ->count();

        $tasaContacto = $llamadasMes > 0 ? round(($contactadas / $llamadasMes) * 100, 1) : 0;

        $calificados = (clone $baseQuery)
            ->whereHas('tipo', function($q) {
                $q->where('slug', 'calificado');
            })
            ->count();

        $pendientesMes = ProspeccionContacto::whereYear('proxima_llamada', $year)
            ->whereMonth('proxima_llamada', $month)
            ->distinct('contacto_id')
            ->count();

        return response()->json([
            'llamadas' => $llamadasMes,
            'tasa_contacto' => $tasaContacto,
            'calificados' => $calificados,
            'pendientes' => $pendientesMes,
            'mes' => $mes,
        ]);
    }

    /**
     * GET /api/prospeccion/cierres-diarios
     */
    public function cierresDiarios(Request $request)
    {
        $userId = $request->user()?->id;
        $mes = $request->query('mes', date('Y-m'));
        $partes = explode('-', $mes);
        $year = intval($partes[0] ?? date('Y'));
        $month = intval($partes[1] ?? date('m'));

        $query = DB::table('prospeccion_contactos')
            ->select(
                DB::raw('DATE(created_at) as fecha'),
                DB::raw('COUNT(*) as llamadas_total'),
                DB::raw("COUNT(CASE WHEN tipo_id IN (SELECT id FROM prospeccion_tipos WHERE slug IN ('" . implode("', '", ColaProspeccion::TIPOS_EFECTIVOS) . "')) THEN 1 END) as llamadas_efectivas"),
                DB::raw("COUNT(CASE WHEN tipo_id = (SELECT id FROM prospeccion_tipos WHERE slug = 'calificado') THEN 1 END) as calificados"),
                DB::raw("COUNT(CASE WHEN tipo_id IN (SELECT id FROM prospeccion_tipos WHERE slug IN ('agendar_seguimiento', 'llamar_luego')) THEN 1 END) as agendados")
            )
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month);

        if ($userId && $request->user()?->role !== 'superadmin') {
            $query->where('user_id', $userId);
        } else {
            if ($request->filled('user_id') && $request->query('user_id') !== 'all') {
                $query->where('user_id', $request->query('user_id'));
            }
        }

        $resultados = $query->groupBy(DB::raw('DATE(created_at)'))
                            ->orderBy('fecha', 'desc')
                            ->get();

        return response()->json($resultados);
    }

    /**
     * GET /api/prospeccion/contactos/{contacto}/historial
     */
    public function historial(Request $request, Contacto $contacto)
    {
        $historial = ProspeccionContacto::where('contacto_id', $contacto->id)
            ->with(['user:id,name', 'tipo', 'derivadoA:id,nombre'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'tipo' => $item->tipo?->slug,
                    'canal' => $item->canal,
                    'notas' => $item->notas,
                    'proxima_llamada' => $item->proxima_llamada,
                    'hora' => $item->hora ? substr($item->hora, 0, 5) : null,
                    'objetivo' => $item->objetivo,
                    'motivo' => $item->motivo,
                    'motivo_label' => $item->motivo ? (ResultadoGestion::MOTIVOS_NO_INTERES[$item->motivo] ?? $item->motivo) : null,
                    'intento' => $item->intento,
                    'derivado_a' => $item->derivadoA?->only(['id', 'nombre']),
                    'created_at' => $item->created_at->toISOString(),
                    'user_name' => $item->user?->name ?? 'Sistema',
                ];
            });

        return response()->json($historial);
    }

    /**
     * GET /api/prospeccion/tipos
     * Returns the list of interaction types for the frontend.
     */
    public function tipos()
    {
        return response()->json(ProspeccionTipo::orderBy('id')->get());
    }
    /**
     * GET /api/prospeccion/gestiones
     * Returns a paginated history of all interactions.
     */
    public function gestiones(Request $request)
    {
        $perPage = (int) $request->query('per_page', 50);
        $perPage = max(1, min($perPage, 100));

        $query = ProspeccionContacto::with([
            'contacto.cuerpos',
            'contacto.companias',
            'user:id,name',
            'tipo'
        ]);

        if ($request->filled('q')) {
            $term = '%' . $request->query('q') . '%';
            $query->whereHas('contacto', function ($q) use ($term) {
                $q->where('nombre', 'like', $term)
                  ->orWhereHas('cuerpos', function ($cq) use ($term) {
                      $cq->where('nombre', 'like', $term);
                  })
                  ->orWhereHas('companias', function ($cq) use ($term) {
                      $cq->where('nombre', 'like', $term);
                  });
            });
        }

        $user = $request->user();

        if ($user && $user->role !== 'superadmin') {
            $query->where('user_id', $user->id);
        } else {
            if ($request->filled('user_id') && $request->query('user_id') !== 'all') {
                $query->where('user_id', $request->query('user_id'));
            }
        }

        $mes = $request->query('mes');
        if ($mes) {
            $partes = explode('-', $mes);
            if (count($partes) === 2) {
                $query->whereYear('created_at', $partes[0])
                      ->whereMonth('created_at', $partes[1]);
            }
        } else {
            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->query('date_from'));
            }
            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->query('date_to'));
            }
        }

        $results = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json($results);
    }

    /**
     * Intento sin respuesta número N: se cuentan los seguidos desde la última
     * gestión con otro resultado. Los demás tipos no llevan intento.
     */
    private function siguienteIntento(Contacto $contacto, string $slug): ?int
    {
        if (!in_array($slug, ColaProspeccion::TIPOS_SIN_RESPUESTA, true)) {
            return null;
        }

        $anterior = ProspeccionContacto::where('contacto_id', $contacto->id)
            ->with('tipo:id,slug')
            ->orderByDesc('id')
            ->first();

        return $anterior && in_array($anterior->tipo?->slug, ColaProspeccion::TIPOS_SIN_RESPUESTA, true)
            ? ($anterior->intento ?? 1) + 1
            : 1;
    }

    /**
     * Pausa la cuenta del contacto (su compañía o, si no tiene, su cuerpo) hasta
     * el día anterior a la fecha de recontacto: ese día vuelven a la cola el
     * contacto y toda su cuenta.
     */
    private function pausarCuenta(Contacto $contacto, ProspeccionContacto $gestion, ?int $userId): ?ProspeccionPausa
    {
        $compania = $contacto->companias()->first();
        $cuerpoId = $compania ? null : $contacto->cuerpos()->value('cuerpos.id');

        if (!$compania && !$cuerpoId) {
            return null;
        }

        return ProspeccionPausa::create([
            'compania_id' => $compania?->id,
            'cuerpo_id' => $compania ? $compania->cuerpo_id : $cuerpoId,
            'hasta' => Carbon::parse($gestion->proxima_llamada)->subDay()->toDateString(),
            'motivo' => $gestion->motivo,
            'user_id' => $userId,
            'gestion_id' => $gestion->id,
        ]);
    }

    /** Contacto nuevo (el que nos recomendaron) en la misma compañía y cuerpo. */
    private function crearContactoEnLaMismaCuenta(Contacto $origen, array $nuevo): Contacto
    {
        $cargo = !empty($nuevo['cargo_id']) ? Cargo::find($nuevo['cargo_id']) : null;

        $contacto = Contacto::create(array_filter([
            'nombre' => $nuevo['nombre'],
            'cargo_id' => $cargo?->id,
            'cargo' => $cargo?->nombre,
            'telefono' => $nuevo['telefono'] ?? null,
            'email' => $nuevo['email'] ?? null,
        ], fn ($v) => $v !== null && $v !== ''));

        $compania = $origen->companias()->first();
        if ($compania) {
            $contacto->companias()->attach($compania->id);
        }
        // Quien es de una compañía también es de su cuerpo.
        $cuerpoIds = array_unique(array_merge(
            $compania ? Compania::cuerpoIdsDe([$compania->id]) : [],
            $compania ? [] : $origen->cuerpos()->pluck('cuerpos.id')->map(fn ($id) => (string) $id)->all(),
        ));
        if ($cuerpoIds) {
            $contacto->cuerpos()->attach($cuerpoIds);
        }

        return $contacto;
    }

    private function descripcionGestion(ProspeccionTipo $tipo, string $canal, ?string $motivo, ?int $intento): string
    {
        $detalle = $motivo
            ? ' (' . (ResultadoGestion::MOTIVOS_NO_INTERES[$motivo] ?? $motivo) . ')'
            : ($intento ? " (intento {$intento})" : '');

        return "Registró una gestión: {$tipo->nombre}{$detalle} por {$canal}";
    }
}
