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
    Schema::create('controles_calidad', function (Blueprint $table) {
        $table->id();
        $table->foreignId('orden_produccion_id')->constrained('ordenes_produccion')->cascadeOnDelete();
        $table->date('fecha_inspeccion')->nullable();
        $table->unsignedInteger('cantidad_aprobada')->default(0);
        $table->unsignedInteger('cantidad_rechazada')->default(0);
        $table->unsignedBigInteger('inspector_id')->nullable();
        $table->text('observaciones')->nullable();
        $table->timestamps();
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('controles_calidad');
    }
};
