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
        Schema::create('pte_familia_formatos', function (Blueprint $table) {
            // 1. CAMBIO: Usamos unsignedBigInteger y primary() para que acepte tus IDs manuales (21, 22, 101...)
            // sin intentar autoincrementar desde el 1.
            $table->unsignedBigInteger('id')->primary();

            // 2. CAMBIO: Agregamos la columna que faltaba
            // Usamos unsignedBigInteger para que coincida con el ID de la tabla familias
            $table->unsignedBigInteger('familia_id'); 
            
            // Opcional: Si estás seguro que la tabla 'pte_familias' se crea ANTES que esta, 
            // puedes descomentar la siguiente línea para activar la llave foránea:
            // $table->foreign('familia_id')->references('id')->on('pte_familias');

            $table->string('nombre', 80);
            $table->boolean('is_activo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pte_familia_formatos');
    }
};