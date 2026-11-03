<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bitácora de gestión de contactos: deja registro de qué ejecutivo hizo qué
 * sobre cada contacto (creación, edición, eliminación, vínculos y gestiones
 * comerciales).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacto_actividades', function (Blueprint $table) {
            $table->id();
            // Sin FK a contactos: la bitácora debe sobrevivir a la eliminación
            // del contacto (por eso también se guarda el nombre como snapshot).
            $table->unsignedBigInteger('contacto_id')->nullable();
            $table->string('contacto_nombre', 150)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_nombre', 150)->nullable();
            $table->string('accion', 40);
            $table->string('descripcion', 500)->nullable();
            $table->json('cambios')->nullable();
            $table->string('origen', 40)->nullable();
            $table->timestamps();

            $table->index(['contacto_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacto_actividades');
    }
};
