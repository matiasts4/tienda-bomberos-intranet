<?php

namespace App\Support;

use App\Models\Contacto;
use App\Models\ProspeccionTipo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cola de prospección: qué contactos están por trabajar y en qué balde cae cada
 * uno. La comparten el listado paginado, los conteos del selector y el árbol
 * Región → Cuerpo → Compañía, para que los tres cuenten exactamente lo mismo.
 *
 * Los baldes de trabajo son relativos a HOY (no al mes elegido en pantalla, que
 * solo mide gestiones): un ejecutivo parte por los seguimientos vencidos, luego
 * los de hoy, los reintentos y recién después los contactos nuevos.
 *
 * Un contacto está "en cola" si (ver condicionEnCola):
 *   - nunca se gestionó, o su última gestión no lo cerró;
 *   - no es inalcanzable (MAX_INTENTOS sin respuesta seguidos), salvo que
 *     después se hayan corregido sus datos;
 *   - un "No interesado" vuelve cuando llega su fecha de recontacto, y un
 *     "Número equivocado" cuando se edita el contacto;
 *   - su cuenta (compañía, o el cuerpo si no tiene compañía) no está pausada
 *     ni tiene un lead abierto: ahí ya hay alguien negociando.
 *
 * La red tiene decenas de miles de contactos, así que contar o agrupar se
 * resuelve siempre en SQL: nunca se hidratan modelos para eso.
 */
class ColaProspeccion
{
    /** Gestiones sin respuesta: cuentan como intento de la cadencia. */
    public const TIPOS_SIN_RESPUESTA = ['no_contesta', 'buzon', 'whatsapp'];

    /** Gestiones en que sí hubo conversación (base de la tasa de contacto). */
    public const TIPOS_EFECTIVOS = ['agendar_seguimiento', 'calificado', 'no_califica', 'derivado'];

    /** Gestiones que sacan al contacto de la cola. */
    public const TIPOS_RESUELTOS = ['no_califica', 'calificado', 'derivado', 'numero_equivocado'];

    /**
     * Filtros de historial: muestran contactos aunque ya no estén en la cola
     * (revisar descartes, derivaciones, datos malos; lo gestionado en el mes).
     */
    public const FILTROS_HISTORIAL = ['llamadas_mes', 'calificado', 'no_califica', 'derivado', 'numero_equivocado', 'inalcanzables'];

    /** Intentos sin respuesta seguidos tras los que el contacto sale de la cola. */
    public const MAX_INTENTOS = 5;

    /** Días de espera tras un intento sin respuesta antes de reintentar. */
    public const DIAS_REINTENTO = 3;

    /** Seguimientos agendados que cuentan como "próximos". */
    public const DIAS_PROXIMOS = 7;

    /**
     * Estados de oportunidad que cierran un lead ("Cerrada ganada", "Cerrada
     * perdida"); cualquier otro estado es un lead abierto.
     */
    public const PREFIJO_ESTADO_CERRADO = 'Cerrad';

    /**
     * Cargos que deciden o pagan una compra (prefijos de cargos.nombre): la
     * directiva de la compañía y la oficialidad del cuerpo. Los bomberos rasos
     * son el 74 % de la red, pero no compran equipos ni uniformes.
     */
    public const CARGOS_DECISORES = ['SUPERINTENDENTE', 'VICE SUPERINTENDENTE', 'COMANDANTE', 'CAPIT', 'DIRECTOR', 'TESORER', 'SECRETARI'];

    private function __construct(
        private readonly Request $request,
        public readonly string $mesInicio,
        public readonly string $mesFin,
        public readonly string $limiteReintento,
        public readonly string $hoy,
        public readonly string $manana,
        public readonly string $finProximos,
    ) {
    }

    public static function desde(Request $request): self
    {
        $mes = (string) $request->query('mes', '');
        $inicio = preg_match('/^\d{4}-\d{2}$/', $mes)
            ? Carbon::createFromFormat('!Y-m', $mes)
            : Carbon::today()->startOfMonth();

        $hoy = Carbon::today();

        return new self(
            $request,
            $inicio->toDateString(),
            $inicio->copy()->endOfMonth()->toDateString(),
            $hoy->copy()->subDays(self::DIAS_REINTENTO)->toDateString(),
            $hoy->toDateString(),
            $hoy->copy()->addDay()->toDateString(),
            $hoy->copy()->addDays(self::DIAS_PROXIMOS)->toDateString(),
        );
    }

    /**
     * Contactos según el balde (`filtro`) y los filtros de búsqueda u
     * organización de la request. Es lo que lista index() y lo que cuenta el árbol.
     */
    public function contactos(): Builder
    {
        $filtro = $this->filtro();
        $query = $this->base();

        if ($filtro === 'bloqueados') {
            // Seguirían en la cola si su cuenta no estuviera pausada o con lead.
            [$sql, $bindings] = $this->condicionEnCola(conBloqueo: false);
            $query->whereRaw($sql, $bindings)->whereNotNull('bq.contacto_id');
        } elseif (!in_array($filtro, self::FILTROS_HISTORIAL, true)) {
            [$sql, $bindings] = $this->condicionEnCola();
            $query->whereRaw($sql, $bindings);
        }

        $this->aplicarBalde($query, $filtro);

        return $query;
    }

    /**
     * Columna `prioridad` del listado, en el orden en que se trabaja la cola:
     *   1 vencido · 2 para hoy · 3 reintentar · 4 nunca contactado ·
     *   5 agendado en los próximos días · 99 en espera.
     * Cada condición es la misma de su balde en aplicarBalde() y conteos().
     *
     * @return array{0: string, 1: array<int, string>}
     */
    public function prioridadSql(): array
    {
        [$reintentar, $bindingsReintentar] = $this->condicionReintentar();

        return [
            "CASE
                WHEN DATE(pc_latest.proxima_llamada) < ? THEN 1
                WHEN DATE(pc_latest.proxima_llamada) = ? THEN 2
                WHEN {$reintentar} THEN 3
                WHEN li.contacto_id IS NULL THEN 4
                WHEN DATE(pc_latest.proxima_llamada) BETWEEN ? AND ? THEN 5
                ELSE 99
            END AS prioridad",
            [$this->hoy, $this->hoy, ...$bindingsReintentar, $this->manana, $this->finProximos],
        ];
    }

    /**
     * Cuántos contactos hay en cada balde del selector, en una sola consulta
     * agregada. Los baldes de trabajo exigen la misma condición "en cola" del
     * listado; los de historial cuentan por el tipo de la última gestión.
     *
     * @return array<string, int>
     */
    public function conteos(): array
    {
        $tipos = ProspeccionTipo::orderBy('id')->pluck('slug');

        $query = $this->base()->leftJoinSub($this->gestionadosEnElMes(), 'gm', 'gm.contacto_id', '=', 'contactos.id');

        [$enCola, $bEnCola] = $this->condicionEnCola();
        [$sinBloqueo, $bSinBloqueo] = $this->condicionEnCola(conBloqueo: false);
        [$reintentar, $bReintentar] = $this->condicionReintentar();
        [$inalcanzable, $bInalcanzable] = $this->condicionInalcanzable();
        $sinRespuesta = "pt.slug IN ('" . implode("', '", self::TIPOS_SIN_RESPUESTA) . "')";

        // [alias, condición SQL, bindings]
        $baldes = [
            ['total_all', $enCola, $bEnCola],
            // DATE(): el cast `date` de Eloquent guarda hora en SQLite, y comparar el
            // texto '2026-10-21 00:00:00' con '2026-10-21' nunca da igual.
            ['vencidos', "{$enCola} AND DATE(pc_latest.proxima_llamada) < ?", [...$bEnCola, $this->hoy]],
            ['hoy', "{$enCola} AND DATE(pc_latest.proxima_llamada) = ?", [...$bEnCola, $this->hoy]],
            ['proximos', "{$enCola} AND DATE(pc_latest.proxima_llamada) BETWEEN ? AND ?", [...$bEnCola, $this->manana, $this->finProximos]],
            ['reintentar', "{$enCola} AND {$reintentar}", [...$bEnCola, ...$bReintentar]],
            ['nuevos', "{$enCola} AND li.contacto_id IS NULL", $bEnCola],
            ['inalcanzables', $inalcanzable, $bInalcanzable],
            ['bloqueados', "{$sinBloqueo} AND bq.contacto_id IS NOT NULL", $bSinBloqueo],
            ['llamadas_mes', 'gm.contacto_id IS NOT NULL', []],
            // Baldes anteriores (p1 = agendados del mes, p2 = sin gestión, p3 = sin
            // respuesta) y 'agendado': siguen para quien los consulte.
            ['p1', "{$enCola} AND DATE(pc_latest.proxima_llamada) BETWEEN ? AND ?", [...$bEnCola, $this->mesInicio, $this->mesFin]],
            ['p2', "{$enCola} AND li.contacto_id IS NULL", $bEnCola],
            // proxima_llamada suele ser NULL: NOT (NULL BETWEEN ...) es NULL, no TRUE,
            // así que el IS NULL explícito es obligatorio (igual que en aplicarBalde).
            ['p3', "{$enCola} AND {$sinRespuesta} AND DATE(pc_latest.created_at) <= ?"
                . ' AND (pc_latest.proxima_llamada IS NULL OR DATE(pc_latest.proxima_llamada) NOT BETWEEN ? AND ?)',
                [...$bEnCola, $this->limiteReintento, $this->mesInicio, $this->mesFin]],
            ['agendado', "{$enCola} AND pc_latest.proxima_llamada IS NOT NULL", $bEnCola],
        ];

        $columnas = [];
        $bindings = [];
        foreach ($baldes as [$alias, $condicion, $b]) {
            $columnas[] = "SUM(CASE WHEN {$condicion} THEN 1 ELSE 0 END) as {$alias}";
            array_push($bindings, ...$b);
        }
        foreach ($tipos as $i => $slug) {
            $columnas[] = "SUM(CASE WHEN pt.slug = ? THEN 1 ELSE 0 END) as tipo_{$i}";
            $bindings[] = $slug;
        }

        $fila = (array) $query->toBase()->selectRaw(implode(', ', $columnas), $bindings)->first();

        $conteos = ['all' => (int) ($fila['total_all'] ?? 0)];
        foreach (array_slice(array_column($baldes, 0), 1) as $alias) {
            $conteos[$alias] = (int) ($fila[$alias] ?? 0);
        }
        foreach ($tipos as $i => $slug) {
            $conteos[$slug] = (int) ($fila["tipo_{$i}"] ?? 0);
        }

        return $conteos;
    }

    /**
     * El contacto está en la cola (ver doc de la clase). Con `conBloqueo: false`
     * se ignora si su cuenta está pausada o con lead (balde "bloqueados").
     *
     * @return array{0: string, 1: array<int, mixed>}
     */
    private function condicionEnCola(bool $conBloqueo = true): array
    {
        [$inalcanzable, $bInalcanzable] = $this->condicionInalcanzable();
        $resueltos = "'" . implode("', '", self::TIPOS_RESUELTOS) . "'";

        $sql = "(
            pt.slug IS NULL
            OR (pt.slug NOT IN ({$resueltos}) AND NOT {$inalcanzable})
            OR (pt.slug = 'no_califica' AND DATE(pc_latest.proxima_llamada) <= ?)
            OR (pt.slug = 'numero_equivocado' AND contactos.updated_at > pc_latest.created_at)
        )";

        if ($conBloqueo) {
            $sql = "({$sql} AND bq.contacto_id IS NULL)";
        }

        return [$sql, [...$bInalcanzable, $this->hoy]];
    }

    /**
     * "Reintentar": la última gestión fue sin respuesta hace DIAS_REINTENTO días
     * o más y no quedó un seguimiento agendado (ese cae en vencidos/hoy/próximos).
     * Un número corregido después de "número equivocado" también se reintenta.
     *
     * @return array{0: string, 1: array<int, string>}
     */
    private function condicionReintentar(): array
    {
        return [
            "((pt.slug IN ('" . implode("', '", self::TIPOS_SIN_RESPUESTA) . "')"
                . ' AND DATE(pc_latest.created_at) <= ? AND pc_latest.proxima_llamada IS NULL)'
                . " OR pt.slug = 'numero_equivocado')",
            [$this->limiteReintento],
        ];
    }

    /**
     * Inalcanzable: MAX_INTENTOS intentos seguidos sin respuesta y nadie corrigió
     * sus datos después del último (editar el contacto lo devuelve a la cola).
     *
     * @return array{0: string, 1: array<int, int>}
     */
    private function condicionInalcanzable(): array
    {
        return [
            "(pt.slug IN ('" . implode("', '", self::TIPOS_SIN_RESPUESTA) . "')"
                . ' AND COALESCE(pc_latest.intento, 1) >= ?'
                . ' AND contactos.updated_at <= pc_latest.created_at)',
            [self::MAX_INTENTOS],
        ];
    }

    /**
     * Contactos con su última gestión unida (`pc_latest`, con su tipo en `pt`),
     * la marca de cuenta bloqueada (`bq`) y los filtros de búsqueda y organización.
     */
    private function base(): Builder
    {
        // La última gestión es la de mayor id: los ids crecen con cada inserción,
        // así que equivale a la más reciente, MAX(id) por contacto sale del índice
        // de contacto_id (en InnoDB todo índice secundario ya incluye la PK) y, a
        // diferencia de MAX(created_at), dos gestiones en el mismo segundo no
        // duplican al contacto en la lista.
        $ultima = DB::table('prospeccion_contactos')
            ->select('contacto_id', DB::raw('MAX(id) as ultima_id'))
            ->groupBy('contacto_id');

        $query = Contacto::query()
            ->leftJoinSub($ultima, 'li', 'li.contacto_id', '=', 'contactos.id')
            ->leftJoin('prospeccion_contactos as pc_latest', 'pc_latest.id', '=', 'li.ultima_id')
            ->leftJoin('prospeccion_tipos as pt', 'pt.id', '=', 'pc_latest.tipo_id')
            // Se une una sola vez: las condiciones solo preguntan si bq es NULL.
            ->leftJoinSub($this->contactosBloqueados(), 'bq', 'bq.contacto_id', '=', 'contactos.id');

        $this->aplicarBusqueda($query);
        $this->aplicarOrganizacion($query);
        $this->aplicarContactabilidad($query);

        return $query;
    }

    /**
     * Contactos cuya cuenta no se debe llamar ahora: tiene un lead abierto (ya hay
     * alguien negociando) o una pausa vigente. La cuenta es la compañía; para un
     * contacto sin compañía en ese cuerpo, es el cuerpo. UNION ya deja un id por
     * contacto, así que el LEFT JOIN no duplica filas.
     */
    private function contactosBloqueados(): QueryBuilder
    {
        $cerrado = self::PREFIJO_ESTADO_CERRADO . '%';

        // Un contacto "directo" del cuerpo no está en ninguna de sus compañías.
        $directoDelCuerpo = function ($q) {
            $q->selectRaw('1')
              ->from('compania_contacto as x')
              ->join('companias as xc', 'xc.id', '=', 'x.compania_id')
              ->whereColumn('x.contacto_id', 'cc.contacto_id')
              ->whereColumn('xc.cuerpo_id', 'cc.cuerpo_id');
        };

        $leadCompania = DB::table('compania_contacto as coc')
            ->join('oportunidades as o', 'o.compania_id', '=', 'coc.compania_id')
            ->join('estados as e', 'e.id', '=', 'o.estado_id')
            ->where('e.nombre', 'not like', $cerrado)
            ->select('coc.contacto_id');

        $leadCuerpo = DB::table('cuerpo_contacto as cc')
            ->join('oportunidades as o', function ($join) {
                $join->on('o.cuerpo_id', '=', 'cc.cuerpo_id')->whereNull('o.compania_id');
            })
            ->join('estados as e', 'e.id', '=', 'o.estado_id')
            ->where('e.nombre', 'not like', $cerrado)
            ->whereNotExists($directoDelCuerpo)
            ->select('cc.contacto_id');

        $pausaCompania = DB::table('compania_contacto as coc')
            ->join('prospeccion_pausas as pp', 'pp.compania_id', '=', 'coc.compania_id')
            ->whereNull('pp.levantada_at')
            ->whereDate('pp.hasta', '>=', $this->hoy)
            ->select('coc.contacto_id');

        $pausaCuerpo = DB::table('cuerpo_contacto as cc')
            ->join('prospeccion_pausas as pp', function ($join) {
                $join->on('pp.cuerpo_id', '=', 'cc.cuerpo_id')->whereNull('pp.compania_id');
            })
            ->whereNull('pp.levantada_at')
            ->whereDate('pp.hasta', '>=', $this->hoy)
            ->whereNotExists($directoDelCuerpo)
            ->select('cc.contacto_id');

        return $leadCompania->union($leadCuerpo)->union($pausaCompania)->union($pausaCuerpo);
    }

    private function aplicarBusqueda(Builder $query): void
    {
        if (!$this->request->filled('q')) {
            return;
        }

        $term = '%' . $this->request->query('q') . '%';
        $query->where(function ($q) use ($term) {
            $q->where('contactos.nombre', 'like', $term)
              ->orWhere('contactos.cargo', 'like', $term)
              ->orWhere('contactos.telefono', 'like', $term)
              ->orWhereHas('cuerpos', fn ($cq) => $cq->where('nombre', 'like', $term))
              ->orWhereHas('companias', fn ($cq) => $cq->where('nombre', 'like', $term));
        });
    }

    /**
     * `con_telefono`: solo a quien se puede llamar (el 45 % de la red no tiene
     * teléfono). `decisores`: solo cargos que deciden la compra.
     */
    private function aplicarContactabilidad(Builder $query): void
    {
        if ($this->request->boolean('con_telefono')) {
            $query->whereNotNull('contactos.telefono')->where('contactos.telefono', '<>', '');
        }

        if ($this->request->boolean('decisores')) {
            $query->whereIn('contactos.cargo_id', function ($q) {
                $q->select('id')->from('cargos')->where(function ($w) {
                    foreach (self::CARGOS_DECISORES as $prefijo) {
                        $w->orWhere('nombre', 'like', $prefijo . '%');
                    }
                });
            });
        }
    }

    /**
     * Filtros de la jerarquía. `sin_compania` acota un cuerpo a los contactos que
     * no están en ninguna de sus compañías; `sin_cuerpo` trae los que no cuelgan
     * de ningún cuerpo (el nodo "Sin cuerpo" del árbol).
     */
    private function aplicarOrganizacion(Builder $query): void
    {
        $r = $this->request;

        if ($r->filled('cliente_id')) {
            $query->where('contactos.cliente_id', $r->query('cliente_id'));
        }

        if ($r->filled('cuerpo_id')) {
            $cuerpoId = (string) $r->query('cuerpo_id');
            $query->whereHas('cuerpos', fn ($q) => $q->where('cuerpos.id', $cuerpoId));

            if ($r->boolean('sin_compania')) {
                $query->whereNotExists(function ($q) use ($cuerpoId) {
                    $q->selectRaw('1')
                      ->from('compania_contacto as coc')
                      ->join('companias as co', 'co.id', '=', 'coc.compania_id')
                      ->whereColumn('coc.contacto_id', 'contactos.id')
                      ->where('co.cuerpo_id', $cuerpoId);
                });
            }
        }

        if ($r->filled('compania_id')) {
            $companiaId = (string) $r->query('compania_id');
            $query->whereHas('companias', fn ($q) => $q->where('companias.id', $companiaId));
        }

        if ($r->boolean('sin_cuerpo')) {
            $query->whereDoesntHave('cuerpos');
        }
    }

    /**
     * Restringe a un balde del selector. Los de prioridad usan las mismas
     * condiciones del CASE de prioridadSql() como WHERE, no un HAVING sobre el
     * alias: SQLite rechaza HAVING sin GROUP BY, y paginate() reenvuelve la
     * consulta para su count().
     */
    private function aplicarBalde(Builder $query, ?string $filtro): void
    {
        switch ($filtro) {
            case null:
            case '':
            case 'all':
            case 'bloqueados':
                return;

            case 'vencidos':
                $query->whereDate('pc_latest.proxima_llamada', '<', $this->hoy);
                return;

            case 'hoy':
                $query->whereDate('pc_latest.proxima_llamada', $this->hoy);
                return;

            case 'proximos':
                $query->whereRaw('DATE(pc_latest.proxima_llamada) BETWEEN ? AND ?', [$this->manana, $this->finProximos]);
                return;

            case 'reintentar':
                [$reintentar, $bindings] = $this->condicionReintentar();
                $query->whereRaw($reintentar, $bindings);
                return;

            case 'nuevos':
                $query->whereNull('li.contacto_id');
                return;

            case 'inalcanzables':
                [$inalcanzable, $bindings] = $this->condicionInalcanzable();
                $query->whereRaw($inalcanzable, $bindings);
                return;

            case 'llamadas_mes':
                $query->whereIn('contactos.id', $this->gestionadosEnElMes());
                return;

            // Baldes anteriores: siguen respondiendo (la pestaña Agendamientos usa 'agendado').
            case '1':
                $query->whereRaw('DATE(pc_latest.proxima_llamada) BETWEEN ? AND ?', [$this->mesInicio, $this->mesFin]);
                return;

            case '2':
                $query->whereNull('li.contacto_id');
                return;

            case '3':
                $query->whereIn('pt.slug', self::TIPOS_SIN_RESPUESTA)
                      ->whereDate('pc_latest.created_at', '<=', $this->limiteReintento)
                      ->where(function ($q) {
                          $q->whereNull('pc_latest.proxima_llamada')
                            ->orWhereRaw('DATE(pc_latest.proxima_llamada) NOT BETWEEN ? AND ?', [$this->mesInicio, $this->mesFin]);
                      });
                return;

            case 'agendado':
                $query->whereNotNull('pc_latest.proxima_llamada');
                return;

            default:
                // Cualquier otro valor es un prospeccion_tipos.slug.
                $query->where('pt.slug', $filtro);
        }
    }

    /**
     * IDs de contactos con al menos una gestión en el mes. Si el usuario no es
     * superadmin solo cuentan sus propias gestiones, igual que en metricas(),
     * para que el filtro calce con la tarjeta "Llamadas del Mes".
     */
    private function gestionadosEnElMes(): QueryBuilder
    {
        $query = DB::table('prospeccion_contactos')
            ->select('contacto_id')
            ->distinct()
            ->whereBetween('created_at', ["{$this->mesInicio} 00:00:00", "{$this->mesFin} 23:59:59"]);

        $user = $this->request->user();
        if ($user && $user->role !== 'superadmin') {
            $query->where('user_id', $user->id);
        }

        return $query;
    }

    private function filtro(): ?string
    {
        $filtro = $this->request->query('filtro');

        return is_string($filtro) ? $filtro : null;
    }
}
