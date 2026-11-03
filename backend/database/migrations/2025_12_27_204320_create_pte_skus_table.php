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
        Schema::create('pte_skus', function (Blueprint $table) {
            $table->string('sku', 50)->primary();
            
            
            $table->string('nombre', 150);              
            $table->text('descripcion')->nullable();   
            $table->integer('precio_venta')->nullable(); 
            $table->integer('stock_critico')->default(5); 
          
            // fk
            $table->foreignId('unidad_negocio_id')->constrained('pte_unidades_negocio');
            $table->foreignId('origen_id')->constrained('pte_origenes');
            $table->foreignId('grp_familia_id')->constrained('pte_grp_familias');
            $table->foreignId('familia_id')->constrained('pte_familias');
            $table->foreignId('subfamilia_id')->constrained('pte_subfamilias');
            $table->foreignId('familia_tipo_id')->constrained('pte_familia_tipos');
            $table->foreignId('familia_formato_id')->constrained('pte_familia_formatos');
            $table->foreignId('genero_id')->constrained('pte_generos');
            $table->foreignId('color_id')->constrained('pte_colores');
            $table->foreignId('talla_id')->constrained('pte_tallas');

            $table->timestamps();
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pte_skus');
    }
};
