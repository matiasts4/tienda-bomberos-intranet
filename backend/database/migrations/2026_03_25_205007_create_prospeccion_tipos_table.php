<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('prospeccion_tipos', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('nombre');
            $table->string('color')->nullable();
            $table->string('icono')->nullable();
            $table->integer('score')->default(0); // For future analytics
            $table->boolean('es_final')->default(false); // If it closes the interaction (e.g. calificado)
            $table->timestamps();
        });

        // Seed initial types (basic seed, more detail in ProspeccionTipoSeeder)
        DB::table('prospeccion_tipos')->insert([
            ['slug' => 'no_contesta', 'nombre' => 'No contesta', 'color' => 'slate', 'icono' => 'phone-off', 'es_final' => false, 'created_at' => now()],
            ['slug' => 'buzon', 'nombre' => 'Buzón de voz', 'color' => 'amber', 'icono' => 'phone-missed', 'es_final' => false, 'created_at' => now()],
            ['slug' => 'llamar_luego', 'nombre' => 'Llamar luego', 'color' => 'blue', 'icono' => 'clock', 'es_final' => false, 'created_at' => now()],
            ['slug' => 'no_califica', 'nombre' => 'No califica', 'color' => 'red', 'icono' => 'x-circle', 'es_final' => true, 'created_at' => now()],
            ['slug' => 'calificado', 'nombre' => '¡Calificado!', 'color' => 'emerald', 'icono' => 'star', 'es_final' => true, 'created_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('prospeccion_tipos');
    }
};
