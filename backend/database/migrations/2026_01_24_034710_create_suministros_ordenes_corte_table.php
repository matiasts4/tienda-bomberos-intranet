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
        Schema::create('suministros_ordenes_corte', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cutting_order_id')->constrained('ordenes_corte')->onDelete('cascade');
            $table->string('name'); // Nombre del insumo
            $table->string('type'); // Tipo de insumo (tela, botón, zipper, etc.)
            $table->decimal('quantity', 10, 2); // Cantidad necesaria
            $table->string('unit'); // Unidad de medida (metros, piezas, etc.)
            $table->text('notes')->nullable(); // Descripción o notas del insumo
            $table->timestamps();
            
            // Índices para búsquedas eficientes
            $table->index(['cutting_order_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suministros_ordenes_corte');
    }
};
