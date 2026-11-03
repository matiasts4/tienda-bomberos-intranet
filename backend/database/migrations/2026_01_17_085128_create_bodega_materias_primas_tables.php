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
        // --- ZONA DE LIMPIEZA (Borrar rastro de la fábrica antigua) ---
        Schema::disableForeignKeyConstraints();
        
        // Listado de tablas antiguas que mencionaste o que vi en tus controladores viejos
        Schema::dropIfExists('mp_movimientos'); 
        Schema::dropIfExists('mp_stocks'); 
        Schema::dropIfExists('mp_especificaciones'); // Esta era la intermedia vieja
        Schema::dropIfExists('mp_proveedor_productos');
        Schema::dropIfExists('mp_lotes'); // Por si acaso
        Schema::dropIfExists('mp_materias_primas'); 
        Schema::dropIfExists('mp_proveedores');
        
        // Tablas de catálogos viejos si existieran
        Schema::dropIfExists('mp_colores');
        Schema::dropIfExists('mp_anchos');
        Schema::dropIfExists('mp_unidades');
        Schema::dropIfExists('mp_tipos');

        Schema::enableForeignKeyConstraints();
        // -------------------------------------------------------------


        // 1. CATÁLOGOS SIMPLES
        Schema::create('mp_tipos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre'); // Tela, Insumo, Hilo, Etiqueta
            $table->timestamps();
        });

        Schema::create('mp_unidades', function (Blueprint $table) {
            $table->id();
            $table->string('nombre'); // Metros, Kilos, Unidades
            $table->string('abreviacion', 10); // Mts, Kg, Ud
            $table->timestamps();
        });

        Schema::create('mp_anchos', function (Blueprint $table) {
            $table->id();
            $table->string('medida'); // 1.50m, 1.80m, N/A
            $table->timestamps();
        });

        Schema::create('mp_colores', function (Blueprint $table) {
            $table->id();
            $table->string('nombre'); // Azul Marino, Crudo, Negro
            $table->string('codigo_hex', 7)->nullable(); 
            $table->timestamps();
        });

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

        // 2. EL PRODUCTO TEÓRICO (La Ficha del Material)
        Schema::create('mp_materiales', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_base'); // "Techni-Shell"
            $table->string('codigo_interno')->unique()->nullable(); // SKU interno tuyo
            
            // Relaciones a catálogos
            $table->foreignId('tipo_id')->constrained('mp_tipos');
            $table->foreignId('unidad_id')->constrained('mp_unidades');
            $table->foreignId('ancho_id')->nullable()->constrained('mp_anchos');
            $table->foreignId('color_id')->nullable()->constrained('mp_colores');
            
            $table->integer('stock_minimo')->default(10);
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            
            $table->timestamps();
        });

        // 3. RELACIÓN MATERIAL - PROVEEDOR (Precios y SKUs externos)
        Schema::create('mp_proveedor_productos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proveedor_id')->constrained('mp_proveedores')->onDelete('cascade');
            $table->foreignId('material_id')->constrained('mp_materiales')->onDelete('cascade');
            
            $table->string('sku_proveedor')->nullable(); 
            $table->decimal('precio_referencia', 10, 2)->default(0);
            $table->string('moneda', 3)->default('CLP'); 
            
            $table->timestamps();
        });

        // 4. EL STOCK FÍSICO REAL (Tabla Unificada: 1 Fila = 1 Rollo/Caja)
        Schema::create('mp_lotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained('mp_materiales')->onDelete('restrict');

            // Identificación Única (Tu etiqueta interna)
            $table->string('codigo_barra_unico')->unique(); // R-1001

            // Trazabilidad de Origen 
            $table->string('codigo_lote_proveedor')->nullable(); // Batch/Tinte
            $table->string('factura_referencia')->nullable(); // Factura compra
            $table->date('fecha_ingreso');
            $table->date('fecha_vencimiento')->nullable();

            // Cantidades
            $table->decimal('cantidad_inicial', 10, 2); 
            $table->decimal('cantidad_actual', 10, 2);  
            $table->decimal('cantidad_reservada', 10, 2)->default(0); 

            // Logística
            $table->string('ubicacion')->nullable(); 
            $table->enum('estado', ['DISPONIBLE', 'RESERVADO', 'AGOTADO', 'CUARENTENA', 'BAJA'])->default('DISPONIBLE');
            
            $table->timestamps();
            
            $table->index('codigo_lote_proveedor');
            $table->index('estado');
        });

        // 5. HISTORIAL DE MOVIMIENTOS
        Schema::create('mp_movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lote_id')->constrained('mp_lotes');
            
            $table->string('tipo_movimiento'); // INGRESO, SALIDA_CORTE, ETC
            $table->decimal('cantidad', 10, 2); 
            
            $table->foreignId('usuario_id')->constrained('users');
            $table->string('documento_respaldo')->nullable(); 
            $table->text('observacion')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('mp_movimientos');
        Schema::dropIfExists('mp_lotes');
        Schema::dropIfExists('mp_proveedor_productos');
        Schema::dropIfExists('mp_materiales');
        Schema::dropIfExists('mp_proveedores');
        Schema::dropIfExists('mp_colores');
        Schema::dropIfExists('mp_anchos');
        Schema::dropIfExists('mp_unidades');
        Schema::dropIfExists('mp_tipos');
        Schema::enableForeignKeyConstraints();
    }
};