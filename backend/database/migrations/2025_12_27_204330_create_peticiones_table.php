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
    Schema::create('peticiones', function (Blueprint $table) {
        $table->id();
        $table->foreignId('detalle_cotizacion_id')->constrained('detalle_cotizaciones')->cascadeOnDelete();
        $table->foreignId('estado_id')->constrained('estados');

        $table->date('fecha_creacion')->nullable();
        $table->date('fecha_vencimiento')->nullable();
        $table->text('observacion')->nullable();

        $table->timestamps();
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('peticiones');
    }
};
