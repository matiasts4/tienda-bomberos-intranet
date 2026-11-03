<?php

namespace App\Support;

use App\Models\Estado;
use Illuminate\Validation\Rule;

/**
 * Etapa "No Vigentes" del kanban de cotizaciones: reúne las que salieron del
 * flujo. El estado dice dónde está la tarjeta; el motivo, por qué salió.
 */
class NoVigente
{
    public const ESTADO = 'No Vigente';

    /**
     * Estados de salida anteriores a esta etapa. Sus cotizaciones no se migran:
     * el kanban las muestra en No Vigentes con su motivo de entonces.
     */
    public const ESTADOS_ANTERIORES = ['Rechazado', 'Rechazada', 'Anulado'];

    /** Claves estables para reportar, con su etiqueta. */
    public const MOTIVOS = [
        'desistida' => 'Desistida',
        'anulada' => 'Anulada',
        'finalizada' => 'Finalizada',
        'finalizacion_adm' => 'Finalización Adm.',
        'otro' => 'Otro',
    ];

    /** Motivos que solo puede elegir (y revertir) un superadmin. */
    public const SOLO_SUPERADMIN = ['finalizacion_adm'];

    /** "Finalizada" es una venta que terminó bien: solo se elige desde Ventas. */
    public const SOLO_DESDE_VENTA = ['finalizada'];

    public const MAX_OTRO = 120;
    public const MAX_OBSERVACION = 2000;

    public static function estado(): ?Estado
    {
        return Estado::where('scope', 'cotizacion')->where('nombre', self::ESTADO)->first();
    }

    /**
     * Reglas del cuerpo que acompaña el cambio a "No Vigente":
     * motivo_no_vigente, motivo_no_vigente_otro y observacion_no_vigente.
     */
    public static function reglas(): array
    {
        return [
            'motivo_no_vigente' => ['required', Rule::in(array_keys(self::MOTIVOS))],
            // Con otro motivo se ignora: el controlador no la guarda.
            'motivo_no_vigente_otro' => ['nullable', 'required_if:motivo_no_vigente,otro', 'string', 'max:' . self::MAX_OTRO],
            'observacion_no_vigente' => ['required', 'string', 'max:' . self::MAX_OBSERVACION],
        ];
    }

    /** Texto para el historial: "Otro (Cambio de proveedor): observación". */
    public static function glosa(string $motivo, ?string $otro, ?string $observacion): string
    {
        $etiqueta = self::MOTIVOS[$motivo] ?? $motivo;
        if ($motivo === 'otro' && filled($otro)) {
            $etiqueta .= ' (' . trim($otro) . ')';
        }

        return filled($observacion) ? $etiqueta . ': ' . trim($observacion) : $etiqueta;
    }
}
