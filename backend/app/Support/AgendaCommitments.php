<?php

namespace App\Support;

use App\Models\Cotizacion;
use App\Models\OportunidadGestion;
use App\Models\ProspeccionContacto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/** Fuentes vigentes compartidas por calendario y recordatorios. */
class AgendaCommitments
{
    public static function cotizaciones(): Builder
    {
        return Cotizacion::query()->whereHas('estado', fn (Builder $q) => $q
            ->where('scope', 'cotizacion')->whereIn('nombre', ['Cotización Enviada', ...DeliveryDeadline::ESTADOS_VENTA]))
            ->whereDoesntHave('despachoModelo.estado', fn (Builder $q) => $q
                ->where('scope', 'despacho')->where('nombre', 'Entregado'));
    }

    public static function esVenta(Cotizacion $cotizacion): bool
    {
        return in_array($cotizacion->estado?->nombre, DeliveryDeadline::ESTADOS_VENTA, true);
    }

    public static function fecha(Cotizacion $cotizacion): ?string
    {
        return self::esVenta($cotizacion) ? $cotizacion->fecha_entrega : $cotizacion->fecha_vencimiento?->toDateString();
    }

    /** El plazo es texto libre: calcular con la misma regla del tablero, sin SQL alternativo. */
    public static function enRango(Builder $query, string $start, string $end, bool $includePending = false): Collection
    {
        $items = collect();
        $query->chunkById(200, function ($rows) use ($items, $start, $end, $includePending) {
            foreach ($rows as $cotizacion) {
                $fecha = self::fecha($cotizacion);
                if (($fecha && $fecha >= $start && $fecha < $end) || ($includePending && !$fecha && self::esVenta($cotizacion))) {
                    $items->push($cotizacion);
                }
            }
        });

        return $items;
    }

    public static function llamadas(): Builder
    {
        return ProspeccionContacto::query()->whereNotExists(function ($q) {
            $q->selectRaw('1')->from('prospeccion_contactos as posterior')
                ->whereColumn('posterior.contacto_id', 'prospeccion_contactos.contacto_id')
                ->where(function ($q) {
                    $q->whereColumn('posterior.created_at', '>', 'prospeccion_contactos.created_at')
                        ->orWhere(fn ($q) => $q->whereColumn('posterior.created_at', 'prospeccion_contactos.created_at')
                            ->whereColumn('posterior.id', '>', 'prospeccion_contactos.id'));
                });
        // Solo contactos que siguen en juego: un descarte, una calificación, una
        // derivación o un número equivocado no dejan una llamada pendiente.
        })->whereHas('tipo', fn ($q) => $q->whereNotIn('slug', ColaProspeccion::TIPOS_RESUELTOS));
    }

    public static function seguimientos(): Builder
    {
        return OportunidadGestion::query()->whereNotExists(function ($q) {
            $q->selectRaw('1')->from('oportunidad_gestiones as posterior')
                ->whereColumn('posterior.oportunidad_id', 'oportunidad_gestiones.oportunidad_id')
                ->where(function ($q) {
                    $q->whereColumn('posterior.fecha_gestion', '>', 'oportunidad_gestiones.fecha_gestion')
                        ->orWhere(fn ($q) => $q->whereColumn('posterior.fecha_gestion', 'oportunidad_gestiones.fecha_gestion')
                            ->whereColumn('posterior.id', '>', 'oportunidad_gestiones.id'));
                });
        })->whereHas('oportunidad', fn ($q) => $q->whereDoesntHave('cotizaciones')
            ->whereHas('estado', fn ($q) => $q->whereNotIn('nombre', [
                'Perdida', 'Cerrada ganada', 'Cerrada perdida', 'Rechazada', 'Rechazado', 'Anulado',
            ])));
    }
}
