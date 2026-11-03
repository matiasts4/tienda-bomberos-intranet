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
    Schema::create('cotizaciones', function (Blueprint $table) {
        $table->id();
        $table->foreignId('oportunidad_id')->constrained('oportunidades');
        $table->foreignId('cliente_id')->constrained('clientes');
        $table->foreignId('ejecutivo_id')->constrained('ejecutivos');

        $table->date('fecha_creacion')->nullable();
        $table->date('fecha_vencimiento')->nullable();
        $table->text('observaciones')->nullable();

        $table->foreignId('estado_id')->constrained('estados');
        $table->timestamps();
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cotizaciones');
    }
};
