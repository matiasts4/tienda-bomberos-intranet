<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Réplica reducida de 2026_09_14_100000 y 2026_09_14_100100 para el esquema de
// pruebas: catálogo de comunas y sus enlaces desde cuerpos/clientes, sin poblar
// las 346 comunas ni tocar FKs que sqlite no soporta al alterar tablas.
return new class extends Migration {
    public function up(): void
    {
        Schema::create('comunas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120);
            $table->unsignedBigInteger('region_id');
            $table->unsignedBigInteger('provincia_id')->nullable();
            $table->timestamps();

            $table->unique(['region_id', 'nombre']);
        });

        Schema::table('cuerpos', function (Blueprint $table) {
            // giro ya viene en el esquema base (0001) desde main.
            $table->unsignedBigInteger('comuna_id')->nullable();
        });

        Schema::table('clientes', function (Blueprint $table) {
            $table->unsignedBigInteger('comuna_id')->nullable();
        });

        Schema::table('companias', function (Blueprint $table) {
            $table->date('fecha_fundacion')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('companias', fn (Blueprint $table) => $table->dropColumn('fecha_fundacion'));
        Schema::table('clientes', fn (Blueprint $table) => $table->dropColumn('comuna_id'));
        Schema::table('cuerpos', fn (Blueprint $table) => $table->dropColumn('comuna_id'));
        Schema::dropIfExists('comunas');
    }
};
