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
        Schema::create('paquetes_ordenes_corte', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained('paquetes')->onDelete('cascade');
            $table->foreignId('cutting_order_id')->constrained('ordenes_corte')->onDelete('cascade');
            $table->timestamps();

            // Evitar duplicados
            $table->unique(['package_id', 'cutting_order_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paquetes_ordenes_corte');
    }
};
