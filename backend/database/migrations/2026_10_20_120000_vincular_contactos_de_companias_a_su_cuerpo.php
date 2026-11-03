<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Quien es de una compañía es también de su cuerpo. Hasta ahora algunos flujos
 * (el "Nuevo contacto" de prospección, el contacto de un lead) vinculaban solo la
 * compañía, y esos contactos no aparecen en el árbol de prospección, que agrupa
 * por cuerpo_contacto. Desde esta versión los controladores agregan el cuerpo
 * (Compania::cuerpoIdsDe); esta migración completa los vínculos ya existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        $ahora = now();

        // Un solo INSERT ... SELECT: si se recorriera por páginas, cada página
        // insertada cambiaría el resultado del NOT EXISTS y el OFFSET de las
        // siguientes se saltaría filas.
        $faltantes = DB::table('compania_contacto as coc')
            ->join('companias as co', 'co.id', '=', 'coc.compania_id')
            ->whereNotNull('co.cuerpo_id')
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')
                  ->from('cuerpo_contacto as cc')
                  ->whereColumn('cc.contacto_id', 'coc.contacto_id')
                  ->whereColumn('cc.cuerpo_id', 'co.cuerpo_id');
            })
            ->selectRaw('coc.contacto_id, co.cuerpo_id, ? as created_at, ? as updated_at', [$ahora, $ahora])
            ->distinct();

        DB::table('cuerpo_contacto')->insertUsing(
            ['contacto_id', 'cuerpo_id', 'created_at', 'updated_at'],
            $faltantes
        );
    }

    public function down(): void
    {
        // Sin vuelta atrás: no se distingue un vínculo agregado aquí de uno que
        // ya existía, y quitarlo volvería a esconder contactos del árbol.
    }
};
