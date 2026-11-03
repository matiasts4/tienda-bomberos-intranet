<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Regla única de vencimiento de cotizaciones (agenda, alertas y creación).
 */
class QuoteExpiry
{
    /** Estados en los que una cotización sigue viva y su vencimiento importa. */
    public const ESTADOS_ABIERTOS = ['Cotización Enviada', 'Ventas'];

    /** Días hábiles de vigencia desde la creación. */
    public const DIAS_HABILES = 15;

    public static function abiertas(Builder $query): Builder
    {
        return $query->whereHas('estado', fn (Builder $q) => $q->whereIn('nombre', self::ESTADOS_ABIERTOS));
    }

    public static function defaultFor($fechaCreacion): string
    {
        return Carbon::parse($fechaCreacion)->addWeekdays(self::DIAS_HABILES)->toDateString();
    }

    /** "vence hoy", "vence mañana", "vence en N días", "venció ayer", "venció hace N días". */
    public static function describir($fechaVencimiento, $hoy = null): string
    {
        $dias = self::diasParaVencer($fechaVencimiento, $hoy);

        return match (true) {
            $dias === 0 => 'vence hoy',
            $dias === 1 => 'vence mañana',
            $dias > 1 => "vence en {$dias} días",
            $dias === -1 => 'venció ayer',
            default => 'venció hace ' . abs($dias) . ' días',
        };
    }

    public static function diasParaVencer($fechaVencimiento, $hoy = null): int
    {
        $hoy = Carbon::parse($hoy ?? Carbon::today())->startOfDay();

        return (int) $hoy->diffInDays(Carbon::parse($fechaVencimiento)->startOfDay(), false);
    }
}
