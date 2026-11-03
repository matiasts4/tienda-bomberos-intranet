<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pte_sku_genericos', function (Blueprint $table) {
            $table->id();
            $table->string('sku_base', 15)->unique();      // SKU genérico (10 dígitos base + género + 0000)
            $table->string('nombre', 150);
            $table->string('imagen_url')->nullable();
            $table->integer('precio_venta')->nullable();

            // FK a catálogos PTE
            $table->unsignedBigInteger('familia_id')->nullable();
            $table->unsignedBigInteger('subfamilia_id')->nullable();
            $table->unsignedBigInteger('familia_tipo_id')->nullable();
            $table->unsignedBigInteger('familia_formato_id')->nullable();
            $table->unsignedBigInteger('genero_id')->default(0);

            $table->boolean('activo')->default(true);
            $table->timestamps();

            // FK constraints
            $table->foreign('familia_id')->references('id')->on('pte_familias');
            $table->foreign('subfamilia_id')->references('id')->on('pte_subfamilias');
            $table->foreign('familia_tipo_id')->references('id')->on('pte_familia_tipos');
            $table->foreign('genero_id')->references('id')->on('pte_generos');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pte_sku_genericos');
    }
};
