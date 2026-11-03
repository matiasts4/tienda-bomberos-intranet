<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cartera de ejecutivos: cada Cuerpo queda a cargo de un ejecutivo (users), y
 * cada ejecutivo tiene un código corto (V1..V4) que va al final del nombre con
 * que los vendedores guardan a sus contactos en el celular.
 */
return new class extends Migration
{
    /** Código => patrón del nombre del ejecutivo (LIKE, sin tildes ni mayúsculas). */
    private const CODIGOS = [
        'V1' => '%rodrigo%fuente%alba%',
        'V2' => '%claudia%s_nchez%',
        'V3' => '%osvaldo%vargas%',
        'V4' => '%c_sar%v_squez%',
    ];

    public function up(): void
    {
        if (!Schema::hasColumn('users', 'codigo_vendedor')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('codigo_vendedor', 4)->nullable()->unique()->after('role');
            });
        }

        if (!Schema::hasColumn('cuerpos', 'ejecutivo_id')) {
            Schema::table('cuerpos', function (Blueprint $table) {
                $table->foreignId('ejecutivo_id')->nullable()->after('comuna_id')
                    ->constrained('users')->nullOnDelete();
            });
        }

        // Códigos iniciales: solo si el nombre calza con un único usuario.
        foreach (self::CODIGOS as $codigo => $patron) {
            if (DB::table('users')->where('codigo_vendedor', $codigo)->exists()) {
                continue;
            }
            $ids = DB::table('users')->whereRaw('LOWER(name) LIKE ?', [$patron])->pluck('id');
            if ($ids->count() === 1) {
                DB::table('users')->where('id', $ids->first())->update(['codigo_vendedor' => $codigo]);
            }
        }

        // Cartera inicial: el ejecutivo con más cotizaciones en ese Cuerpo.
        $conteos = DB::table('cotizaciones')
            ->join('users', 'users.id', '=', 'cotizaciones.user_id')
            ->whereNotNull('cotizaciones.cuerpo_id')
            ->groupBy('cotizaciones.cuerpo_id', 'cotizaciones.user_id')
            ->selectRaw('cotizaciones.cuerpo_id, cotizaciones.user_id, COUNT(*) as total')
            ->orderByDesc('total')
            ->get();

        $asignados = [];
        foreach ($conteos as $fila) {
            if (isset($asignados[$fila->cuerpo_id])) {
                continue;
            }
            $asignados[$fila->cuerpo_id] = true;
            DB::table('cuerpos')->where('id', $fila->cuerpo_id)->whereNull('ejecutivo_id')
                ->update(['ejecutivo_id' => $fila->user_id]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('cuerpos', 'ejecutivo_id')) {
            Schema::table('cuerpos', function (Blueprint $table) {
                $table->dropConstrainedForeignId('ejecutivo_id');
            });
        }
        if (Schema::hasColumn('users', 'codigo_vendedor')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique(['codigo_vendedor']);
                $table->dropColumn('codigo_vendedor');
            });
        }
    }
};
