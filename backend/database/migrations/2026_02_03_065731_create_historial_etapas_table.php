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
        Schema::create('historial_etapas', function (Blueprint $table) {
            $table->id();

            // Polimórfica: model_type + model_id (Oportunidad / Cotizacion)
            $table->morphs('model');

            $table->foreignId('user_id')->constrained('users');

            $table->unsignedBigInteger('estado_anterior_id')->nullable();
            $table->foreign('estado_anterior_id')->references('id')->on('estados');

            $table->unsignedBigInteger('estado_nuevo_id');
            $table->foreign('estado_nuevo_id')->references('id')->on('estados');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('historial_etapas');
    }
};
