<?php

namespace App\Support;

/**
 * Regiones de Chile con su numeración oficial (la misma que usa
 * bomberos_mapped.json para armar los ID de Cuerpos y Compañías).
 */
final class RegionesChile
{
    /** Nombre oficial por número de región. */
    public const NOMBRES = [
        1 => 'Región de Tarapacá',
        2 => 'Región de Antofagasta',
        3 => 'Región de Atacama',
        4 => 'Región de Coquimbo',
        5 => 'Región de Valparaíso',
        6 => 'Región del Libertador Bernardo O\'higgins',
        7 => 'Región del Maule',
        8 => 'Región del Bío Bío',
        9 => 'Región de la Araucanía',
        10 => 'Región de los Lagos',
        11 => 'Región de Aysén del General Carlos Ibañez del Campo',
        12 => 'Región de Magallanes y la Antártica Chilena',
        13 => 'Región Metropolitana',
        14 => 'Región de los Ríos',
        15 => 'Región de Arica',
        16 => 'Región de Ñuble',
    ];

    /** Macrozonas geográficas: id => [nombre, código]. */
    public const ZONAS = [
        1 => ['Norte', 'N'],
        2 => ['Centro', 'C'],
        3 => ['Sur', 'S'],
        4 => ['Austral', 'A'],
    ];

    /** Número de región => id de su macrozona. */
    public const ZONA_DE_REGION = [
        15 => 1, 1 => 1, 2 => 1, 3 => 1, 4 => 1,
        5 => 2, 13 => 2, 6 => 2, 7 => 2,
        16 => 3, 8 => 3, 9 => 3, 14 => 3, 10 => 3,
        11 => 4, 12 => 4,
    ];
}
