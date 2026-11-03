<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Etapa "No Vigente": reúne en el kanban las cotizaciones que salieron del flujo.
 *
 *  - Estado nuevo `No Vigente` (scope cotizacion).
 *  - Por qué salió: `motivo_no_vigente` (clave de App\Support\NoVigente::MOTIVOS),
 *    `motivo_no_vigente_otro` (descripción breve si el motivo es "otro"),
 *    `observacion_no_vigente`, `fecha_no_vigente` y `no_vigente_por_id`.
 *  - `historial_etapas.observaciones`: el motivo queda en el historial aunque
 *    la cotización vuelva al flujo.
 *
 * No cambia las cotizaciones Rechazadas o Anuladas existentes: el frontend las
 * muestra en la misma etapa con su motivo anterior.
 */
return new class extends Migration
{
    public function up(): void
    {
        $existe = DB::table('estados')
            ->where('scope', 'cotizacion')
            ->where('nombre', 'No Vigente')
            ->exists();

        if (!$existe) {
            DB::table('estados')->insert([
                'nombre' => 'No Vigente',
                'scope' => 'cotizacion',
                'color' => '#475569',
                'orden' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (!Schema::hasColumn('cotizaciones', 'motivo_no_vigente')) {
            Schema::table('cotizaciones', function (Blueprint $table) {
                $table->string('motivo_no_vigente', 30)->nullable()->after('motivo_rechazo');
                $table->string('motivo_no_vigente_otro', 120)->nullable()->after('motivo_no_vigente');
                $table->text('observacion_no_vigente')->nullable()->after('motivo_no_vigente_otro');
                $table->dateTime('fecha_no_vigente')->nullable()->after('observacion_no_vigente');
                $table->foreignId('no_vigente_por_id')->nullable()->after('fecha_no_vigente')
                    ->constrained('users')->nullOnDelete();
            });
        }

        if (!Schema::hasColumn('historial_etapas', 'observaciones')) {
            Schema::table('historial_etapas', function (Blueprint $table) {
                $table->text('observaciones')->nullable()->after('estado_nuevo_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('cotizaciones', 'motivo_no_vigente')) {
            Schema::table('cotizaciones', function (Blueprint $table) {
                $table->dropConstrainedForeignId('no_vigente_por_id');
                $table->dropColumn(['motivo_no_vigente', 'motivo_no_vigente_otro', 'observacion_no_vigente', 'fecha_no_vigente']);
            });
        }

        // Se deja historial_etapas.observaciones: puede tener texto que no conviene perder.
        $estadoId = DB::table('estados')->where('scope', 'cotizacion')->where('nombre', 'No Vigente')->value('id');
        if ($estadoId && !DB::table('cotizaciones')->where('estado_id', $estadoId)->exists()
            && !DB::table('historial_etapas')->where('estado_nuevo_id', $estadoId)->orWhere('estado_anterior_id', $estadoId)->exists()) {
            DB::table('estados')->where('id', $estadoId)->delete();
        }
    }
};
