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
    Schema::create('pte_movimientos', function (Blueprint $table) {
        $table->id();
        $table->dateTime('fecha_hora')->nullable();


        // Aca falto poner qué producto se movió (quizas no lo puse en el word nose :p)
        $table->string('sku', 50);
        $table->foreign('sku')->references('sku')->on('pte_skus');
        //fin del cambio
        
        $table->string('tipo_movimiento', 50);

        $table->unsignedBigInteger('usuario_id')->nullable(); // si después tienes users, lo vuelves FK
        $table->foreignId('cotizacion_id')->nullable()->constrained('cotizaciones');

        $table->unsignedInteger('cantidad');
        $table->unsignedInteger('saldo_anterior')->nullable();
        $table->unsignedInteger('saldo_nuevo')->nullable();

        $table->timestamps();
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pte_movimientos');
    }
};
