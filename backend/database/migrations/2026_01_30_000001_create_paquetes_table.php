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
        Schema::create('paquetes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // P-001, P-002, etc.
            $table->string('destination'); // Destino del paquete
            $table->string('transport_type'); // Tipo de transporte
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
            $table->enum('status', [
                'preparing', // Preparando
                'transit', // En tránsito
                'delivered', // Entregado
                'returned' // Devuelto
            ])->default('preparing');
            $table->text('notes')->nullable();
            $table->integer('estimated_delivery_days')->default(3);
            $table->date('shipped_date')->nullable(); // Fecha de envío
            $table->date('delivered_date')->nullable(); // Fecha de entrega
            $table->string('tracking_number')->nullable(); // Número de seguimiento
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paquetes');
    }
};
