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
        Schema::create('reparaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('control_calidad_id')->constrained('controles_calidad')->cascadeOnDelete();
            $table->foreignId('orden_produccion_id')->constrained('ordenes_produccion')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained('mp_materiales');
    
            $table->decimal('cantidad_perdida', 12, 3);
            $table->string('motivo_reparacion', 120)->nullable();
            $table->timestamps();
        });
    }
    

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reparaciones');
    }
};
