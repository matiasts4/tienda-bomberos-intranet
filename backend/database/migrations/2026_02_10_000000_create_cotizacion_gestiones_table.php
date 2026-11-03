<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cotizacion_gestiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cotizacion_id')->constrained('cotizaciones')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users'); // Usuario que realiza la gestión
            $table->string('glosa')->nullable(); // Motivo o detalle de la gestión
            $table->dateTime('fecha_gestion');
            $table->date('fecha_vencimiento_nueva')->nullable(); // Nueva fecha pactada
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cotizacion_gestiones');
    }
};
