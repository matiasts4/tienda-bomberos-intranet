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
        Schema::create('ordenes_corte', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // Código secuencial (1000, 1001, etc.)
            $table->string('client')->default('Orden de Corte General'); // Nombre del cliente
            $table->string('selected_product'); // Producto principal de la orden
            $table->enum('status', [
                'order-received',
                'in-cutting', 
                'in-assembly',
                'in-quality-control',
                'completed',
                'delivered'
            ])->default('order-received');
            $table->text('notes')->nullable();
            $table->integer('estimated_days')->default(7);
            $table->decimal('progress', 5, 2)->default(0); // Progreso en porcentaje
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ordenes_corte');
    }
};
