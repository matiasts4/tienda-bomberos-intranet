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
        Schema::create('oportunidad_gestiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('oportunidad_id')->constrained('oportunidades')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users'); // Usuario que realiza la gestión
            $table->string('glosa')->nullable(); // Motivo o detalle de la gestión
            $table->dateTime('fecha_gestion');
            // Not adding fecha_vencimiento_nueva since its more on quote side?
            // "Oportunidad Kanban" can have "next action" date, let's keep it to be consistent with Cotizaciones
            $table->date('fecha_vencimiento_nueva')->nullable(); // Nueva fecha pactada
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('oportunidad_gestiones');
    }
};
