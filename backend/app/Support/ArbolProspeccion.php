<?php

namespace App\Support;

use App\Models\Cuerpo;
use App\Models\ProspeccionPausa;
use App\Models\Region;
use Illuminate\Support\Facades\DB;

/**
 * Agrupa la cola de prospección en el árbol Región → Cuerpo → Compañía.
 *
 * Solo entrega conteos: los contactos de cada hoja se piden aparte, paginados,
 * al listado de la cola. Así abrir el árbol cuesta lo mismo con 6 mil que con
 * 60 mil contactos.
 *
 * Cada cuerpo y compañía trae además su contexto de cuenta, sin importar los
 * filtros: cuántos leads abiertos tiene, si está pausada y cuándo se le gestionó
 * por última vez. Una compañía con lead o pausa no tiene contactos en la cola
 * (ColaProspeccion la bloquea), así que solo se ve en el balde "bloqueados";
 * su cuerpo sí muestra el lead.
 *
 * La pertenencia a un cuerpo sale de cuerpo_contacto. Un contacto de una
 * compañía también está en el cuerpo de esa compañía (ver
 * Compania::cuerpoIdsDe), así que nadie queda fuera del árbol por estar
 * vinculado solo a su compañía.
 */
class ArbolProspeccion
{
    public function __construct(private readonly ColaProspeccion $cola)
    {
    }

    /**
     * Regiones con sus cuerpos y cuántos contactos de la cola tiene cada uno.
     * Solo aparecen nodos con al menos un contacto.
     *
     * @return array{regiones: array<int, array>, sin_cuerpo: int}
     */
    public function regiones(): array
    {
        // Una fila por par (contacto, cuerpo): el par es único en el pivote, así
        // que COUNT(*) por cuerpo ya cuenta contactos distintos. El LEFT JOIN deja
        // a los contactos sin cuerpo en el grupo cuerpo_id = NULL.
        $porCuerpo = $this->cola->contactos()
            ->leftJoin('cuerpo_contacto as cc', 'cc.contacto_id', '=', 'contactos.id')
            ->toBase()
            ->select('cc.cuerpo_id', DB::raw('COUNT(*) as total'))
            ->groupBy('cc.cuerpo_id')
            ->get();

        // Por región hace falta DISTINCT: hay contactos en dos cuerpos de la misma
        // región y sumar los cuerpos los contaría dos veces.
        $porRegion = $this->cola->contactos()
            ->join('cuerpo_contacto as cc', 'cc.contacto_id', '=', 'contactos.id')
            ->join('cuerpos as cu', 'cu.id', '=', 'cc.cuerpo_id')
            ->toBase()
            ->select('cu.region_id', DB::raw('COUNT(DISTINCT contactos.id) as total'))
            ->groupBy('cu.region_id')
            ->pluck('total', 'region_id');

        $sinCuerpo = 0;
        $totalesCuerpo = [];
        foreach ($porCuerpo as $fila) {
            if ($fila->cuerpo_id === null) {
                $sinCuerpo = (int) $fila->total;
            } else {
                $totalesCuerpo[(string) $fila->cuerpo_id] = (int) $fila->total;
            }
        }

        // Los IDs de cuerpo son strings ("0500100"); PHP convierte a int las claves
        // numéricas sin cero inicial ("1300300"), así que se vuelven a string para
        // que el IN compare contra el VARCHAR y use el índice.
        $cuerpos = Cuerpo::query()
            ->with('comuna:id,nombre')
            ->whereIn('id', array_map('strval', array_keys($totalesCuerpo)))
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'region_id', 'comuna_id', 'numero_companias'])
            ->groupBy('region_id');

        $leads = $this->leadsAbiertosPorCuerpo();
        $pausas = $this->pausasVigentes()->whereNull('compania_id')->keyBy('cuerpo_id');
        $ultimas = DB::table('prospeccion_contactos as p')
            ->join('cuerpo_contacto as cc', 'cc.contacto_id', '=', 'p.contacto_id')
            ->groupBy('cc.cuerpo_id')
            ->selectRaw('cc.cuerpo_id, MAX(p.created_at) as ultima')
            ->pluck('ultima', 'cuerpo_id');

        $regiones = Region::query()
            ->whereIn('id', $porRegion->keys())
            ->orderBy('id')
            ->get(['id', 'nombre'])
            ->map(fn (Region $region) => [
                'id' => $region->id,
                'nombre' => $region->nombre,
                'total' => (int) $porRegion[$region->id],
                'cuerpos' => ($cuerpos[$region->id] ?? collect())
                    ->map(fn (Cuerpo $cuerpo) => [
                        'id' => $cuerpo->id,
                        'nombre' => $cuerpo->nombre,
                        'comuna' => $cuerpo->comuna?->nombre,
                        'numero_companias' => $cuerpo->numero_companias,
                        'total' => $totalesCuerpo[$cuerpo->id] ?? 0,
                        'leads_abiertos' => (int) ($leads[$cuerpo->id] ?? 0),
                        'ultima_gestion_at' => $ultimas[$cuerpo->id] ?? null,
                        'pausa' => $this->resumenPausa($pausas[$cuerpo->id] ?? null),
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();

        return ['regiones' => $regiones, 'sin_cuerpo' => $sinCuerpo];
    }

    /**
     * Compañías de un cuerpo con sus contactos en cola, más cuántos contactos
     * del cuerpo no están en ninguna de sus compañías (nodo "Sin compañía").
     *
     * @return array{cuerpo_id: string, total: int, sin_compania: int, companias: array<int, array>}
     */
    public function cuerpo(Cuerpo $cuerpo): array
    {
        $leads = DB::table('oportunidades as o')
            ->join('estados as e', 'e.id', '=', 'o.estado_id')
            ->join('companias as co', 'co.id', '=', 'o.compania_id')
            ->where('co.cuerpo_id', $cuerpo->id)
            ->where('e.nombre', 'not like', ColaProspeccion::PREFIJO_ESTADO_CERRADO . '%')
            ->groupBy('o.compania_id')
            ->selectRaw('o.compania_id, COUNT(*) as total')
            ->pluck('total', 'compania_id');
        $ultimas = DB::table('prospeccion_contactos as p')
            ->join('compania_contacto as coc', 'coc.contacto_id', '=', 'p.contacto_id')
            ->join('companias as co', 'co.id', '=', 'coc.compania_id')
            ->where('co.cuerpo_id', $cuerpo->id)
            ->groupBy('coc.compania_id')
            ->selectRaw('coc.compania_id, MAX(p.created_at) as ultima')
            ->pluck('ultima', 'compania_id');

        $pausas = $this->pausasVigentes()->whereNotNull('compania_id')->keyBy('compania_id');

        $companias = $this->cola->contactos()
            ->join('compania_contacto as coc', 'coc.contacto_id', '=', 'contactos.id')
            ->join('companias as co', 'co.id', '=', 'coc.compania_id')
            ->where('co.cuerpo_id', $cuerpo->id)
            ->toBase()
            ->select('co.id', 'co.nombre', 'co.numero', DB::raw('COUNT(*) as total'))
            ->groupBy('co.id', 'co.nombre', 'co.numero')
            ->orderBy('co.numero')
            ->orderBy('co.nombre')
            ->get()
            ->map(fn ($fila) => [
                'id' => (string) $fila->id,
                'nombre' => $fila->nombre,
                'numero' => $fila->numero === null ? null : (int) $fila->numero,
                'total' => (int) $fila->total,
                'leads_abiertos' => (int) ($leads[$fila->id] ?? 0),
                'ultima_gestion_at' => $ultimas[$fila->id] ?? null,
                'pausa' => $this->resumenPausa($pausas[$fila->id] ?? null),
            ])
            ->all();

        $resumen = $this->cola->contactos()
            ->join('cuerpo_contacto as cc', function ($join) use ($cuerpo) {
                $join->on('cc.contacto_id', '=', 'contactos.id')->where('cc.cuerpo_id', $cuerpo->id);
            })
            ->toBase()
            ->selectRaw(
                'COUNT(*) as total, SUM(CASE WHEN NOT EXISTS ('
                    . 'SELECT 1 FROM compania_contacto coc JOIN companias co ON co.id = coc.compania_id'
                    . ' WHERE coc.contacto_id = contactos.id AND co.cuerpo_id = ?'
                    . ') THEN 1 ELSE 0 END) as sin_compania',
                [$cuerpo->id]
            )
            ->first();

        return [
            'cuerpo_id' => $cuerpo->id,
            'total' => (int) ($resumen->total ?? 0),
            'sin_compania' => (int) ($resumen->sin_compania ?? 0),
            'companias' => $companias,
        ];
    }

    /**
     * Leads abiertos por cuerpo: los del cuerpo y los de sus compañías (un lead
     * de compañía puede venir sin cuerpo_id).
     *
     * @return \Illuminate\Support\Collection<string, int>
     */
    private function leadsAbiertosPorCuerpo()
    {
        return DB::table('oportunidades as o')
            ->join('estados as e', 'e.id', '=', 'o.estado_id')
            ->leftJoin('companias as co', 'co.id', '=', 'o.compania_id')
            ->where('e.nombre', 'not like', ColaProspeccion::PREFIJO_ESTADO_CERRADO . '%')
            ->whereRaw('COALESCE(o.cuerpo_id, co.cuerpo_id) IS NOT NULL')
            ->groupByRaw('COALESCE(o.cuerpo_id, co.cuerpo_id)')
            ->selectRaw('COALESCE(o.cuerpo_id, co.cuerpo_id) as cuerpo_id, COUNT(*) as total')
            ->pluck('total', 'cuerpo_id');
    }

    /** Pausas vigentes hoy, la de fecha más lejana primero (una cuenta puede tener varias). */
    private function pausasVigentes()
    {
        return ProspeccionPausa::query()
            ->activas()
            ->orderByDesc('hasta')
            ->get(['id', 'compania_id', 'cuerpo_id', 'hasta', 'motivo'])
            ->unique(fn (ProspeccionPausa $p) => $p->compania_id ?? 'cuerpo:' . $p->cuerpo_id)
            ->values();
    }

    /** @return array{id: int, hasta: string, motivo: ?string}|null */
    private function resumenPausa(?ProspeccionPausa $pausa): ?array
    {
        return $pausa ? ['id' => $pausa->id, 'hasta' => $pausa->hasta->toDateString(), 'motivo' => $pausa->motivo] : null;
    }
}
