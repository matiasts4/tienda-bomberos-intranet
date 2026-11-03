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
    Schema::create('detalle_cotizaciones', function (Blueprint $table) {
        $table->id();
        $table->foreignId('cotizacion_id')->constrained('cotizaciones')->cascadeOnDelete();

        $table->string('sku', 50);
        $table->unsignedInteger('n_item')->nullable();
        $table->unsignedInteger('cantidad');
        $table->unsignedBigInteger('subtotal')->nullable();
        $table->boolean('is_personalizable')->default(false);

        $table->foreign('sku')->references('sku')->on('pte_skus');
        $table->timestamps();
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detalle_cotizaciones');
    }
};
