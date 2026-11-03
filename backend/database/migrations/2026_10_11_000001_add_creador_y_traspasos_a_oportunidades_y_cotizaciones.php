<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Creador y responsable de las tarjetas comerciales (oportunidades y cotizaciones).
 *
 *  - `creado_por_id`: quien creó la tarjeta; no cambia nunca.
 *  - `user_id` (ya existente): ejecutivo responsable o gestionador; arranca igual
 *    al creador y se puede traspasar a otro ejecutivo.
 *  - `traspasos_responsable`: historial de cada cambio de responsable.
 *
 * Backfill: para lo histórico no se sabe quién la creó, así que se toma el
 * responsable actual como creador.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['oportunidades', 'cotizaciones'] as $tabla) {
            if (!Schema::hasColumn($tabla, 'creado_por_id')) {
                Schema::table($tabla, function (Blueprint $table) {
                    $table->foreignId('creado_por_id')->nullable()->after('user_id')
                        ->constrained('users')->nullOnDelete();
                });
            }

            DB::table($tabla)->whereNull('creado_por_id')->update(['creado_por_id' => DB::raw('user_id')]);
        }

        if (!Schema::hasTable('traspasos_responsable')) {
            Schema::create('traspasos_responsable', function (Blueprint $table) {
                $table->id();
                $table->string('model_type');
                $table->unsignedBigInteger('model_id');
                $table->foreignId('de_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('a_user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('por_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('motivo', 255)->nullable();
                $table->timestamps();

                $table->index(['model_type', 'model_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('traspasos_responsable');

        foreach (['oportunidades', 'cotizaciones'] as $tabla) {
            if (Schema::hasColumn($tabla, 'creado_por_id')) {
                Schema::table($tabla, function (Blueprint $table) {
                    $table->dropConstrainedForeignId('creado_por_id');
                });
            }
        }
    }
};
