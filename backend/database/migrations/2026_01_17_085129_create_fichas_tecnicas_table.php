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
    Schema::create('fichas_tecnicas', function (Blueprint $table) {
        $table->id();
        $table->string('sku', 50);
        $table->foreignId('material_id')->constrained('mp_materiales');
        $table->decimal('cantidad_requerida', 12, 3);

        $table->foreign('sku')->references('sku')->on('pte_skus');
        $table->timestamps();
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fichas_tecnicas');
    }
};
