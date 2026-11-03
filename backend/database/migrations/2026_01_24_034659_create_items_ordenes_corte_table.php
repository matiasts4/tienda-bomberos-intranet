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
        Schema::create('items_ordenes_corte', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cutting_order_id')->constrained('ordenes_corte')->onDelete('cascade');
            $table->string('product_type'); // Tipo de producto (debe coincidir con selected_product)
            $table->string('size'); // Talla (XS, S, M, L, etc.)
            $table->string('color'); // Color de la variante
            $table->integer('quantity'); // Cantidad para esta talla
            $table->string('fabric_type'); // Tipo de tela
            $table->text('notes')->nullable(); // Notas específicas del item
            $table->timestamps();
            
            // Índices para búsquedas eficientes
            $table->index(['cutting_order_id', 'product_type']);
            $table->index(['cutting_order_id', 'color']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items_ordenes_corte');
    }
};
