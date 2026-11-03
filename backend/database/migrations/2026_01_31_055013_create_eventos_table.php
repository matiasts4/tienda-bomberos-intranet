<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up()
{
    Schema::create('eventos', function (Blueprint $table) {
        $table->id();
        
        // Relaciones
        $table->foreignId('ejecutivo_id')->constrained('users'); // O 'ejecutivos' según tu tabla de usuarios
        $table->foreignId('oportunidad_id')->nullable()->constrained('oportunidades')->nullOnDelete();
        
        // Datos del evento
        $table->string('titulo');
        $table->text('descripcion')->nullable();
        
        // Tiempos
        $table->dateTime('inicio');
        $table->dateTime('fin')->nullable();
        
        // Lógica de negocio (Semáforo)
        $table->string('tipo')->default('reunion'); // reunion, vencimiento, seguimiento
        $table->boolean('alerta_enviada')->default(false);
        
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('eventos');
    }
};
