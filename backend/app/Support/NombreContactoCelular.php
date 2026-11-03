<?php

namespace App\Support;

use App\Models\Contacto;
use Illuminate\Support\Str;

/**
 * Nombre con que los ejecutivos guardan un contacto en su celular
 * ("NomContacCelu"):
 *
 *   CB <Cuerpo> - <N° Compañía, 0 si es del Cuerpo> - <Contacto> - <Cargo abreviado> - <Código vendedor>
 *   CB La Ligua - 5 - Toto Pavo - TEN1 - V2
 *
 * Se calcula con los datos del sistema (no se escribe a mano) para que siempre
 * coincida con los nombres de Cuerpos y Compañías y con la cartera del Cuerpo.
 * Lo que falte se marca con "?" para que se vea que hay que completarlo.
 */
class NombreContactoCelular
{
    public const FALTA = '?';

    /** Relaciones que hay que cargar antes de llamar a para(). */
    public const RELACIONES = ['cargo_obj', 'cuerpos.ejecutivo', 'companias.cuerpo.ejecutivo'];

    /** Null si el contacto no es de la Red de Bomberos (empresas, sin asignar). */
    public static function para(Contacto $contacto): ?string
    {
        $compania = $contacto->companias->first();
        $cuerpo = $compania?->cuerpo ?? $contacto->cuerpos->first();
        if (!$cuerpo) {
            return null;
        }

        return implode(' - ', [
            'CB ' . self::nombreCuerpo($cuerpo->nombre),
            (string) ($compania?->numero ?? 0),
            self::limpiar($contacto->nombre) ?: self::FALTA,
            self::abreviarCargo($contacto->cargo_obj?->nombre ?? $contacto->cargo) ?? self::FALTA,
            $cuerpo->ejecutivo?->codigo_vendedor ?: self::FALTA,
        ]);
    }

    /** "Cuerpo de Bomberos de La Ligua" → "La Ligua". */
    public static function nombreCuerpo(string $nombre): string
    {
        $nombre = preg_replace('/^\s*(cuerpo\s+de\s+bomberos|c\.?\s*b\.?)\s+(de\s+)?/iu', '', $nombre);

        return self::limpiar($nombre);
    }

    /**
     * Abreviatura del cargo: DIR, CAP, TEN1, CMDT2, SUP, VSUP, TES, SEC...
     * Los ordinales ("1ro", "2°", "Segundo") se pegan al final.
     */
    public static function abreviarCargo(?string $cargo): ?string
    {
        $texto = Str::of((string) $cargo)->ascii()->lower()->squish()->value();
        if ($texto === '') {
            return null;
        }

        $base = match (true) {
            str_contains($texto, 'vice superintend') => 'VSUP',
            str_contains($texto, 'superintend') => 'SUP',
            str_contains($texto, 'comandant') => 'CMDT',
            str_contains($texto, 'director') => 'DIR',
            str_contains($texto, 'capitan') => 'CAP',
            str_contains($texto, 'teniente') => 'TEN',
            str_contains($texto, 'pro tesorer') => 'PTES',
            str_contains($texto, 'tesorer') => 'TES',
            str_contains($texto, 'pro secretari') => 'PSEC',
            str_contains($texto, 'secretari') => 'SEC',
            str_contains($texto, 'ayudante') => 'AYU',
            str_contains($texto, 'inspector') => 'INSP',
            str_contains($texto, 'intendente') => 'INT',
            str_contains($texto, 'consejero') => 'CONS',
            str_contains($texto, 'maquinista') => 'MAQ',
            str_contains($texto, 'cuartelero') => 'CUAR',
            str_contains($texto, 'bomber') => 'BOM',
            default => Str::upper(Str::substr(preg_replace('/[^a-z]/', '', $texto), 0, 4)),
        };

        $ordinal = null;
        if (preg_match('/\b([1-4])\s*(ro|do|er|to|°|º)?\b/u', $texto, $m)) {
            $ordinal = $m[1];
        } elseif (preg_match('/\b(primer|segund|tercer|cuart)/', $texto, $m)) {
            $ordinal = ['primer' => '1', 'segund' => '2', 'tercer' => '3', 'cuart' => '4'][$m[1]];
        }

        // "Cuarto Comandante" no debe quedar como CUAR: el ordinal es parte del cargo.
        return $base . ($ordinal && in_array($base, ['CMDT', 'DIR', 'CAP', 'TEN'], true) ? $ordinal : '');
    }

    /** Sin guiones ni espacios dobles: el " - " es el separador. */
    private static function limpiar(?string $texto): string
    {
        return Str::of((string) $texto)->replace(['-', '_'], ' ')->squish()->value();
    }
}
