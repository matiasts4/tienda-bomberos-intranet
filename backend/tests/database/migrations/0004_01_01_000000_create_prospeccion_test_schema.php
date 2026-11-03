<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema de test para el módulo Prospección.
 * Depende del schema comercial (0001): users, clientes, contactos, estados,
 * oportunidades, oportunidad_gestiones.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Tipos de interacción ───────────────────────────────────────────
        Schema::create('prospeccion_tipos', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('nombre');
            $table->string('color')->nullable();
            $table->string('icono')->nullable();
            $table->integer('score')->default(0);
            $table->boolean('es_final')->default(false);
            $table->timestamps();
        });

        // Seed de tipos (igual que la migración real)
        \DB::table('prospeccion_tipos')->insert([
            ['slug' => 'no_contesta',  'nombre' => 'No contesta',   'color' => 'slate',   'icono' => 'phone-off',    'es_final' => false, 'score' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'buzon',        'nombre' => 'Buzón de voz',  'color' => 'amber',   'icono' => 'phone-missed', 'es_final' => false, 'score' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'llamar_luego', 'nombre' => 'Llamar luego',  'color' => 'blue',    'icono' => 'clock',        'es_final' => false, 'score' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'no_califica',  'nombre' => 'No califica',   'color' => 'red',     'icono' => 'x-circle',     'es_final' => true,  'score' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'calificado',   'nombre' => '¡Calificado!',  'color' => 'emerald', 'icono' => 'star',         'es_final' => true,  'score' => 10, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // ── Interacciones ─────────────────────────────────────────────────
        Schema::create('prospeccion_contactos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contacto_id')->constrained('contactos')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('tipo_id')->nullable()->constrained('prospeccion_tipos')->nullOnDelete();
            $table->string('canal')->nullable();
            $table->text('notas')->nullable();
            $table->date('proxima_llamada')->nullable();
            $table->timestamps();
        });

        // ── Eventos (para interaccion con proxima_llamada) ─────────────────
        Schema::create('eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ejecutivo_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('oportunidad_id')->nullable()->constrained('oportunidades')->nullOnDelete();
            $table->string('titulo');
            $table->text('descripcion')->nullable();
            $table->dateTime('inicio');
            $table->dateTime('fin')->nullable();
            $table->string('tipo')->default('reunion');
            $table->string('area')->nullable();
            $table->string('nivel')->default('usuario');
            $table->string('categoria')->nullable();
            $table->string('color')->nullable();
            $table->boolean('alerta_enviada')->default(false);
            $table->boolean('is_all_day')->default(false);
            $table->text('rrule')->nullable();
            $table->nullableMorphs('eventable');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos');
        Schema::dropIfExists('prospeccion_contactos');
        Schema::dropIfExists('prospeccion_tipos');
    }
};
