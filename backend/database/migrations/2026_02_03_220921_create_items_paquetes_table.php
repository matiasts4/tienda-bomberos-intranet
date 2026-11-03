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
        Schema::create('items_paquetes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained('paquetes')->onDelete('cascade');
            $table->foreignId('cutting_order_item_id')->constrained('items_ordenes_corte')->onDelete('cascade');
            $table->integer('quantity'); // Cantidad de items incluidos en este paquete
            $table->timestamps();
            
            // Un item de una orden de corte puede estar dividido en varios paquetes, 
            // pero NO en el mismo paquete dos veces.
            $table->unique(['package_id', 'cutting_order_item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items_paquetes');
    }
};
