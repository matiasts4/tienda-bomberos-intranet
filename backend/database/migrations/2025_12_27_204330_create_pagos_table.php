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
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cotizacion_id')->constrained('cotizaciones')->cascadeOnDelete();
    
            $table->string('factura', 100)->nullable();
            $table->string('comprobante_pago', 100)->nullable();
            $table->string('orden_compra', 100)->nullable();
            $table->unsignedBigInteger('monto')->nullable();
            $table->date('fecha_pago')->nullable();
            $table->string('metodo', 50)->nullable();
            $table->string('estado_pago', 50)->nullable();
    
            $table->timestamps();
        });
    }
    

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};
