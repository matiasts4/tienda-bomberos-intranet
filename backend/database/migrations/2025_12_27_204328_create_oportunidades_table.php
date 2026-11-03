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
    Schema::create('oportunidades', function (Blueprint $table) {
        $table->id();
        $table->date('fecha_ingreso')->nullable();
        $table->string('nombre_contacto', 150)->nullable();
        $table->string('numero_contacto', 30)->nullable();
        $table->string('empresa', 150)->nullable();

        $table->foreignId('cliente_id')->constrained('clientes');
        $table->foreignId('ejecutivo_id')->constrained('ejecutivos');
        $table->foreignId('estado_id')->constrained('estados');

        $table->timestamps();
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('oportunidades');
    }
};
