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
    Schema::create('detalle_personalizaciones', function (Blueprint $table) {
        $table->id();
        $table->foreignId('detalle_cotizacion_id')->constrained('detalle_cotizaciones')->cascadeOnDelete();

        $table->string('sku', 50);
        $table->string('tipo', 80)->nullable();
        $table->string('posicion', 80)->nullable();

        $table->foreign('sku')->references('sku')->on('pte_skus');
        $table->timestamps();
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detalle_personalizaciones');
    }
};
