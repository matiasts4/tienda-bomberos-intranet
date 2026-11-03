<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Espejo de 2026_10_21_100000_enriquecer_gestiones_de_prospeccion para SQLite:
// columnas nuevas de la gestión, pausas por cuenta y los tipos que faltaban.
return new class extends Migration {
    public function up(): void
    {
        Schema::table('prospeccion_contactos', function (Blueprint $table) {
            $table->time('hora')->nullable();
            $table->string('objetivo', 120)->nullable();
            $table->string('motivo', 40)->nullable();
            $table->unsignedSmallInteger('intento')->nullable();
            $table->unsignedBigInteger('derivado_a_id')->nullable();
            $table->json('datos')->nullable();
        });

        Schema::create('prospeccion_pausas', function (Blueprint $table) {
            $table->id();
            $table->string('compania_id', 15)->nullable();
            $table->string('cuerpo_id', 15)->nullable();
            $table->date('hasta');
            $table->string('motivo', 40)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('gestion_id')->nullable();
            $table->timestamp('levantada_at')->nullable();
            $table->timestamps();
        });

        $tipos = [
            ['slug' => 'buzon', 'nombre' => 'Dejé mensaje', 'color' => 'amber', 'icono' => 'phone-missed', 'es_final' => false, 'score' => 0],
            ['slug' => 'llamar_luego', 'nombre' => 'Reagendado sin contacto', 'color' => 'blue', 'icono' => 'clock', 'es_final' => false, 'score' => 5],
            ['slug' => 'agendar_seguimiento', 'nombre' => 'Seguimiento acordado', 'color' => 'orange', 'icono' => 'calendar', 'es_final' => false, 'score' => 20],
            ['slug' => 'derivado', 'nombre' => 'Derivado a otro contacto', 'color' => 'violet', 'icono' => 'forward', 'es_final' => true, 'score' => 10],
            ['slug' => 'numero_equivocado', 'nombre' => 'Número equivocado', 'color' => 'zinc', 'icono' => 'phone-off', 'es_final' => true, 'score' => 0],
            ['slug' => 'no_califica', 'nombre' => 'No interesado', 'color' => 'red', 'icono' => 'x-circle', 'es_final' => true, 'score' => -1],
        ];
        foreach ($tipos as $tipo) {
            DB::table('prospeccion_tipos')->updateOrInsert(['slug' => $tipo['slug']], $tipo + ['created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('prospeccion_pausas');
        Schema::table('prospeccion_contactos', fn (Blueprint $table) => $table->dropColumn(['hora', 'objetivo', 'motivo', 'intento', 'derivado_a_id', 'datos']));
    }
};
