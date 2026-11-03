<?php

namespace App\Support;

/**
 * Catálogos de los resultados de una llamada de prospección. Son claves
 * estables para reportar ("¿por qué nos dicen que no?") y etiquetas legibles
 * para la bitácora y el resumen de la oportunidad.
 */
class ResultadoGestion
{
    /** Tipos que exigen fecha: un seguimiento acordado siempre tiene día. */
    public const TIPOS_CON_FECHA = ['agendar_seguimiento'];

    /** Por qué un contacto dijo que no. */
    public const MOTIVOS_NO_INTERES = [
        'ya_tiene_proveedor' => 'Ya tiene proveedor',
        'compro_recientemente' => 'Compró hace poco',
        'sin_presupuesto' => 'Sin presupuesto',
        'no_es_el_momento' => 'No es el momento',
        'no_le_interesa' => 'No le interesa',
        'otro' => 'Otro',
    ];

    /** Para cuándo necesita la compra un contacto calificado. */
    public const PLAZOS = [
        'este_mes' => 'Este mes',
        '1_3_meses' => 'En 1 a 3 meses',
        '3_6_meses' => 'En 3 a 6 meses',
        'mas_adelante' => 'Más adelante',
    ];

    /** Estado del presupuesto de la compra. */
    public const PRESUPUESTOS = [
        'aprobado' => 'Aprobado',
        'en_tramite' => 'En trámite',
        'sin_presupuesto' => 'Aún sin presupuesto',
        'no_sabe' => 'No sabe',
    ];

    /**
     * Texto de una calificación para la oportunidad y la bitácora: lo que el
     * ejecutivo que cotiza necesita leer de un vistazo.
     *
     * @param array{necesidad?: ?string, cantidad?: ?int, plazo?: ?string, presupuesto?: ?string, decisor?: ?string, notas?: ?string} $datos
     */
    public static function resumenCalificacion(array $datos): string
    {
        $lineas = array_filter([
            !empty($datos['necesidad']) ? 'Necesita: ' . $datos['necesidad'] : null,
            !empty($datos['cantidad']) ? 'Cantidad aprox.: ' . $datos['cantidad'] : null,
            !empty($datos['plazo']) ? 'Plazo: ' . (self::PLAZOS[$datos['plazo']] ?? $datos['plazo']) : null,
            !empty($datos['presupuesto']) ? 'Presupuesto: ' . (self::PRESUPUESTOS[$datos['presupuesto']] ?? $datos['presupuesto']) : null,
            !empty($datos['decisor']) ? 'Decide: ' . $datos['decisor'] : null,
        ]);
        $notas = trim((string) ($datos['notas'] ?? ''));

        // Solo notas (clientes anteriores al formulario estructurado): van tal cual.
        if (empty($lineas)) {
            return $notas;
        }

        if ($notas !== '') {
            $lineas[] = 'Notas: ' . $notas;
        }

        return implode("\n", $lineas);
    }
}
