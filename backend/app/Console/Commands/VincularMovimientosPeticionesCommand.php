<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Completa pte_movimientos.peticion_id en el historial anterior al vínculo formal.
 * Solo llena peticion_id vacíos con coincidencias únicas; nunca toca stock,
 * cantidades, usuarios ni fechas. Sin --apply solo muestra lo que haría.
 */
class VincularMovimientosPeticionesCommand extends Command
{
    protected $signature = 'pte:vincular-movimientos-peticiones
        {--apply : Guarda los vínculos (sin esta opción solo se informa)}
        {--detailed : Muestra cada movimiento ambiguo o sin coincidencia}';

    protected $description = 'Vincula movimientos históricos de reserva, despacho y rechazo con su solicitud (peticion)';

    private const TOLERANCIA_SEGUNDOS = 120;

    /** Tipo de movimiento → [estado de la petición que lo produce, columna de fecha de la petición]. */
    private const REGLAS = [
        'RESERVA_PRODUCTO' => [null, 'created_at'],
        'SALIDA_VENTA' => [['Despachado', 'Aceptado'], 'updated_at'],
        'ANULACION_RESERVA' => [['Rechazado'], 'updated_at'],
    ];

    public function handle(): int
    {
        $peticiones = DB::table('peticiones as p')
            ->leftJoin('estados as e', 'e.id', '=', 'p.estado_id')
            ->get(['p.id', 'p.sku', 'p.cantidad', 'p.cotizacion_id', 'p.referencia_externa', 'p.user_id', 'p.created_at', 'p.updated_at', 'e.nombre as estado']);

        $yaVinculadas = DB::table('pte_movimientos')->whereNotNull('peticion_id')
            ->get(['peticion_id', 'tipo_movimiento'])
            ->map(fn ($m) => $m->tipo_movimiento.'|'.$m->peticion_id)->flip();

        $movimientos = DB::table('pte_movimientos')->whereNull('peticion_id')
            ->whereIn('tipo_movimiento', array_keys(self::REGLAS))
            ->orderBy('id')
            ->get(['id', 'sku', 'cantidad', 'cotizacion_id', 'referencia_externa', 'usuario_id', 'tipo_movimiento', 'fecha_hora']);

        $candidatos = [];
        foreach ($movimientos as $mov) {
            [$estados, $columnaFecha] = self::REGLAS[$mov->tipo_movimiento];
            $fecha = CarbonImmutable::parse($mov->fecha_hora);

            // Antes del 23-09-2026 pte_movimientos no guardaba referencia_externa: en ese
            // historial, un movimiento sin cotización ni referencia no puede compararse por ella.
            $sinReferenciaHistorica = ! $mov->cotizacion_id && ! $mov->referencia_externa;

            $candidatos[$mov->id] = $peticiones->filter(function ($p) use ($mov, $estados, $columnaFecha, $fecha, $yaVinculadas, $sinReferenciaHistorica) {
                return $p->sku === $mov->sku
                    && (int) $p->cantidad === (int) $mov->cantidad
                    && $p->cotizacion_id == $mov->cotizacion_id
                    && ($sinReferenciaHistorica || ($p->referencia_externa ?: null) === ($mov->referencia_externa ?: null))
                    && ($estados === null ? (int) $p->user_id === (int) $mov->usuario_id : in_array($p->estado, $estados, true))
                    && ! $yaVinculadas->has($mov->tipo_movimiento.'|'.$p->id)
                    && $p->{$columnaFecha} !== null
                    && abs($fecha->diffInSeconds(CarbonImmutable::parse($p->{$columnaFecha}), false)) <= self::TOLERANCIA_SEGUNDOS;
            })->pluck('id')->all();
        }

        // Único en ambos sentidos: un movimiento con una sola petición, y esa petición
        // reclamada por un solo movimiento del mismo tipo.
        $reclamos = [];
        foreach ($candidatos as $movId => $ids) {
            if (count($ids) === 1) {
                $tipo = $movimientos->firstWhere('id', $movId)->tipo_movimiento;
                $reclamos[$tipo.'|'.$ids[0]][] = $movId;
            }
        }

        $vinculos = [];
        $ambiguos = [];
        $sinCoincidencia = [];
        foreach ($candidatos as $movId => $ids) {
            $tipo = $movimientos->firstWhere('id', $movId)->tipo_movimiento;
            if ($ids === []) {
                $sinCoincidencia[] = $movId;
            } elseif (count($ids) === 1 && count($reclamos[$tipo.'|'.$ids[0]]) === 1) {
                $vinculos[$movId] = $ids[0];
            } else {
                $ambiguos[] = $movId;
            }
        }

        $porTipo = collect($vinculos)->keys()->groupBy(fn ($id) => $movimientos->firstWhere('id', $id)->tipo_movimiento)->map->count();
        $this->table(['Resultado', 'Movimientos'], [
            ['Sin peticion_id (revisados)', $movimientos->count()],
            ['Vinculables (coincidencia única)', count($vinculos)],
            ...collect(array_keys(self::REGLAS))->map(fn ($tipo) => ["  · {$tipo}", $porTipo[$tipo] ?? 0])->all(),
            ['Ambiguos (se dejan sin vincular)', count($ambiguos)],
            ['Sin coincidencia (se dejan sin vincular)', count($sinCoincidencia)],
        ]);

        if ($this->option('detailed')) {
            foreach (['Ambiguos' => $ambiguos, 'Sin coincidencia' => $sinCoincidencia] as $titulo => $ids) {
                if ($ids !== []) {
                    $this->line("{$titulo}: ".implode(', ', $ids));
                }
            }
        }

        if (! $this->option('apply')) {
            $this->warn('Simulación: no se guardó nada. Ejecuta con --apply para guardar los vínculos.');

            return self::SUCCESS;
        }

        $guardados = DB::transaction(function () use ($vinculos) {
            $total = 0;
            foreach ($vinculos as $movId => $peticionId) {
                $total += DB::table('pte_movimientos')->where('id', $movId)->whereNull('peticion_id')
                    ->update(['peticion_id' => $peticionId]);
            }

            return $total;
        });

        $this->info("Vínculos guardados: {$guardados}.");

        return self::SUCCESS;
    }
}
