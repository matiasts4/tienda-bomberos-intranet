<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema de test para los módulos:
 *  - Bodega MP (mp_*): materiales, lotes, movimientos, proveedores, catálogos
 *  - Fábrica Corte: ordenes_corte, items_ordenes_corte, suministros_ordenes_corte
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Catálogos MP ───────────────────────────────────────────────────
        Schema::create('mp_tipos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->timestamps();
        });

        Schema::create('mp_unidades', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('abreviacion', 10);
            $table->timestamps();
        });

        Schema::create('mp_anchos', function (Blueprint $table) {
            $table->id();
            $table->string('medida');
            $table->timestamps();
        });

        Schema::create('mp_colores', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('codigo_hex', 7)->nullable();
            $table->timestamps();
        });

        // ── Proveedores ────────────────────────────────────────────────────
        Schema::create('mp_proveedores', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_fantasia');
            $table->string('razon_social')->nullable();
            $table->string('rut_empresa', 20)->nullable();
            $table->string('contacto_nombre')->nullable();
            $table->string('telefono')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        // ── Materiales ─────────────────────────────────────────────────────
        Schema::create('mp_materiales', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_base');
            $table->string('codigo_interno')->unique()->nullable();
            $table->foreignId('tipo_id')->constrained('mp_tipos');
            $table->foreignId('unidad_id')->constrained('mp_unidades');
            $table->foreignId('ancho_id')->nullable()->constrained('mp_anchos');
            $table->foreignId('color_id')->nullable()->constrained('mp_colores');
            $table->integer('stock_minimo')->default(10);
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // ── Pivot proveedor-material ────────────────────────────────────────
        Schema::create('mp_proveedor_productos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proveedor_id')->constrained('mp_proveedores')->onDelete('cascade');
            $table->foreignId('material_id')->constrained('mp_materiales')->onDelete('cascade');
            $table->string('sku_proveedor')->nullable();
            $table->decimal('precio_referencia', 10, 2)->nullable()->default(0);
            $table->string('moneda', 3)->default('CLP');
            $table->timestamps();
        });

        // ── Lotes ──────────────────────────────────────────────────────────
        Schema::create('mp_lotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained('mp_materiales');
            $table->foreignId('proveedor_id')->nullable()->constrained('mp_proveedores');
            $table->string('codigo_barra_unico')->unique();
            $table->string('codigo_lote_proveedor')->nullable();
            $table->string('factura_referencia')->nullable();
            $table->date('fecha_ingreso');
            $table->date('fecha_vencimiento')->nullable();
            $table->decimal('cantidad_inicial', 10, 2);
            $table->decimal('cantidad_actual', 10, 2);
            $table->decimal('cantidad_reservada', 10, 2)->default(0);
            $table->string('ubicacion')->nullable();
            $table->string('estado')->default('DISPONIBLE'); // SQLite no tiene ENUM
            $table->timestamps();
        });

        // ── Movimientos MP ─────────────────────────────────────────────────
        Schema::create('mp_movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lote_id')->constrained('mp_lotes');
            $table->foreignId('proveedor_id')->nullable()->constrained('mp_proveedores');
            $table->string('tipo_movimiento');
            $table->decimal('cantidad', 10, 2);
            $table->unsignedBigInteger('usuario_id')->nullable(); // nullable en test (controller hardcodea 1)
            $table->string('documento_respaldo')->nullable();
            $table->text('observacion')->nullable();
            $table->timestamps();
        });

        // ── Órdenes de Corte ───────────────────────────────────────────────
        Schema::create('ordenes_corte', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('client')->default('Orden de Corte General');
            $table->string('selected_product');
            $table->string('status')->default('order-received');
            $table->text('notes')->nullable();
            $table->integer('estimated_days')->default(7);
            $table->decimal('progress', 5, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('items_ordenes_corte', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cutting_order_id')->constrained('ordenes_corte')->onDelete('cascade');
            $table->string('product_type');
            $table->string('size');
            $table->string('color');
            $table->integer('quantity');
            $table->string('fabric_type');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('suministros_ordenes_corte', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cutting_order_id')->constrained('ordenes_corte')->onDelete('cascade');
            $table->string('name');
            $table->string('type');
            $table->decimal('quantity', 10, 2);
            $table->string('unit');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suministros_ordenes_corte');
        Schema::dropIfExists('items_ordenes_corte');
        Schema::dropIfExists('ordenes_corte');
        Schema::dropIfExists('mp_movimientos');
        Schema::dropIfExists('mp_lotes');
        Schema::dropIfExists('mp_proveedor_productos');
        Schema::dropIfExists('mp_materiales');
        Schema::dropIfExists('mp_proveedores');
        Schema::dropIfExists('mp_colores');
        Schema::dropIfExists('mp_anchos');
        Schema::dropIfExists('mp_unidades');
        Schema::dropIfExists('mp_tipos');
    }
};
