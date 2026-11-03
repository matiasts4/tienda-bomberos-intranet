<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Esquema de tablas para tests del módulo Inventario PTE.
 * pte_generos, pte_colores y pte_tallas ya están en el schema comercial (0001).
 */
return new class extends Migration
{
    public function up(): void
    {
        // --- Catálogos PTE -----------------------------------------------
        Schema::create('pte_unidades_negocio', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->timestamps();
        });

        Schema::create('pte_origenes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->timestamps();
        });

        Schema::create('pte_grp_familias', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->timestamps();
        });

        Schema::create('pte_familias', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->timestamps();
        });

        Schema::create('pte_subfamilias', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->unsignedBigInteger('familia_id')->nullable();
            $table->timestamps();
        });

        Schema::create('pte_familia_tipos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->timestamps();
        });

        Schema::create('pte_familia_formatos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->timestamps();
        });

        // --- Productos / SKUs ---------------------------------------------
        Schema::create('pte_sku_genericos', function (Blueprint $table) {
            $table->id();
            $table->string('sku_base', 15)->unique();
            $table->string('nombre', 150);
            $table->string('imagen_url')->nullable();
            $table->integer('precio_venta')->nullable();
            $table->unsignedBigInteger('unidad_negocio_id')->nullable();
            $table->unsignedBigInteger('origen_id')->nullable();
            $table->unsignedBigInteger('grp_familia_id')->nullable();
            $table->unsignedBigInteger('familia_id')->nullable();
            $table->unsignedBigInteger('subfamilia_id')->nullable();
            $table->unsignedBigInteger('familia_tipo_id')->nullable();
            $table->unsignedBigInteger('familia_formato_id')->nullable();
            $table->unsignedBigInteger('genero_id')->default(0);
            $table->boolean('activo')->default(true);
            $table->boolean('permite_personalizar')->default(false);
            $table->json('images_json')->nullable();
            $table->string('imagen_extra_1')->nullable();
            $table->string('imagen_extra_2')->nullable();
            $table->string('imagen_extra_3')->nullable();
            $table->string('imagen_extra_4')->nullable();
            $table->string('imagen_extra_5')->nullable();
            $table->timestamps();
        });

        Schema::create('pte_skus', function (Blueprint $table) {
            $table->string('sku', 50)->primary();
            $table->unsignedBigInteger('sku_generico_id')->nullable();
            $table->boolean('activo')->default(true);
            $table->string('nombre', 150);
            $table->text('descripcion')->nullable();
            $table->integer('precio_venta')->nullable();
            $table->integer('stock_critico')->default(5);

            $table->foreign('sku_generico_id')->references('id')->on('pte_sku_genericos');

            $table->foreignId('color_id')->constrained('pte_colores');
            $table->foreignId('talla_id')->constrained('pte_tallas');

            $table->timestamps();
        });

        // --- Stock --------------------------------------------------------
        Schema::create('pte_stocks', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 50)->unique();
            $table->unsignedInteger('cantidad')->default(0);
            $table->unsignedInteger('reserved_stock')->default(0);
            $table->integer('stock_critico')->default(5);
            $table->foreign('sku')->references('sku')->on('pte_skus')->cascadeOnDelete();
            $table->timestamps();
        });

        // --- Movimientos --------------------------------------------------
        Schema::create('pte_movimientos', function (Blueprint $table) {
            $table->id();
            $table->dateTime('fecha_hora')->nullable();
            $table->string('sku', 50);
            $table->foreign('sku')->references('sku')->on('pte_skus');
            $table->string('tipo_movimiento', 50);
            $table->string('cliente_nombre', 255)->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->unsignedBigInteger('cotizacion_id')->nullable();
            $table->string('referencia_externa', 100)->nullable();
            $table->unsignedInteger('cantidad');
            $table->unsignedInteger('saldo_anterior')->nullable();
            $table->unsignedInteger('saldo_nuevo')->nullable();
            $table->string('id_lote')->nullable();
            $table->text('observacion')->nullable();
            $table->timestamps();
        });

        // --- Cargos (usado por CatalogoController) ------------------------
        Schema::create('cargos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->timestamps();
        });

        // --- Peticiones (usado por SkuController::destroy) ---------------
        Schema::create('peticiones', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 50)->nullable();
            $table->unsignedBigInteger('estado_id')->nullable();
            $table->timestamps();
        });

        // --- Otros catálogos ya en schema comercial (no repetir) ----------
        // pte_generos, pte_colores, pte_tallas → definidos en 0001_...
    }

    public function down(): void
    {
        Schema::dropIfExists('pte_movimientos');
        Schema::dropIfExists('pte_stocks');
        Schema::dropIfExists('pte_skus');
        Schema::dropIfExists('pte_familia_formatos');
        Schema::dropIfExists('pte_familia_tipos');
        Schema::dropIfExists('pte_subfamilias');
        Schema::dropIfExists('pte_familias');
        Schema::dropIfExists('pte_grp_familias');
        Schema::dropIfExists('pte_origenes');
        Schema::dropIfExists('pte_unidades_negocio');
    }
};
