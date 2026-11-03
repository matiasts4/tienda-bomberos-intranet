<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('encuesta_satisfaccions', function (Blueprint $table) {
            $table->string('origen', 20)->nullable();
            $table->string('referencia_clave', 100)->nullable()->unique('encuestas_referencia_clave_unique');
            $table->foreignId('peticion_id')->nullable()->constrained('peticiones')->nullOnDelete();
            $table->foreignId('contacto_id')->nullable()->constrained('contactos')->nullOnDelete();
            $table->string('empresa_cliente', 255)->nullable();
            $table->string('vendedor_nombre', 255)->nullable();
            $table->string('nombre_cliente_manual', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Evita un rollback destructivo de vínculos y respuestas históricas.
        throw new RuntimeException('Esta migración aditiva requiere una reversión revisada que preserve las encuestas.');
    }
};
