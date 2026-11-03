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
    Schema::create('despachos', function (Blueprint $table) {
        $table->id();
        $table->foreignId('cotizacion_id')->constrained('cotizaciones')->cascadeOnDelete();
        $table->foreignId('cliente_id')->constrained('clientes');

        $table->string('calle', 150)->nullable();
        $table->string('numero_calle', 20)->nullable();
        $table->string('comuna', 100)->nullable();
        $table->string('ciudad', 100)->nullable();
        $table->date('fecha_despacho')->nullable();
        $table->string('estado_despacho', 50)->nullable();
        $table->text('observacion')->nullable();

        $table->timestamps();
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('despachos');
    }
};
