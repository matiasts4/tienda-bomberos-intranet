<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerta_configs', function (Blueprint $table) {
            $table->id();
            $table->string('clave')->unique(); // Ej: 'horario', 'emails'
            $table->json('valor');             // Guardamos arrays o datos complejos
            $table->timestamps();
        });

        // Insertar configuración por defecto
        DB::table('alerta_configs')->insert([
            ['clave' => 'horario', 'valor' => json_encode('08:30')],
            ['clave' => 'dias', 'valor' => json_encode(['Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes'])],
            ['clave' => 'emails', 'valor' => json_encode(['bodega@tiendabomberos.cl'])],
            ['clave' => 'activar_prediccion', 'valor' => json_encode(true)],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('alerta_configs');
    }
};