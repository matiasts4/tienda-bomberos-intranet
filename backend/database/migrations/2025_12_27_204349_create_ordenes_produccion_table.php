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
    Schema::create('ordenes_produccion', function (Blueprint $table) {
        $table->id();
        $table->foreignId('peticion_id')->constrained('peticiones')->cascadeOnDelete();
        $table->string('sku', 50);
        $table->unsignedInteger('cantidad_a_producir');
        $table->string('estado_produccion', 30)->nullable();

        $table->foreign('sku')->references('sku')->on('pte_skus');
        $table->timestamps();
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ordenes_produccion');
    }
};
