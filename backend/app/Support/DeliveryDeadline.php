<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Plazo de entrega de una venta: fecha_venta + plazo_produccion.
 *
 * El plazo se guarda como texto ("20 dias", "15 dias habiles", "Stock Disponible").
 */
class DeliveryDeadline
{
    public const PLAZO_DEFAULT = '20 dias';

    /** Estados desde los que corre el plazo de entrega. */
    public const ESTADOS_VENTA = ['Ventas', 'Aprobada por Cliente'];

    /** Fecha límite de entrega (Y-m-d), o null si no hay fecha de venta o el plazo no se entiende. */
    public static function fechaEntrega($fechaVenta, ?string $plazo): ?string
    {
        if (!$fechaVenta) {
            return null;
        }

        $inicio = Carbon::parse($fechaVenta)->startOfDay();
        $plazo = mb_strtolower(trim($plazo ?: self::PLAZO_DEFAULT));

        if (str_contains($plazo, 'stock')) {
            return $inicio->toDateString();
        }

        if (!preg_match('/(\d+)/', $plazo, $m)) {
            return null;
        }

        $dias = (int) $m[1];
        $habiles = str_contains($plazo, 'habil') || str_contains($plazo, 'hábil');

        return ($habiles ? $inicio->addWeekdays($dias) : $inicio->addDays($dias))->toDateString();
    }
}
