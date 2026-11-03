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
        Schema::create('encuesta_satisfaccions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cotizacion_id')->constrained('cotizaciones')->onDelete('cascade');
            $table->string('cliente_id', 15)->nullable();
            $table->foreign('cliente_id')->references('id')->on('clientes')->onDelete('set null');
            $table->tinyInteger('calidad_producto');
            $table->tinyInteger('calidad_personalizacion');
            $table->tinyInteger('tiempo_entrega');
            $table->tinyInteger('atencion_comercial');
            $table->text('comentarios')->nullable();
            
            // Garantizar 1 encuesta por cotización
            $table->unique('cotizacion_id');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('encuesta_satisfaccions');
    }
};
