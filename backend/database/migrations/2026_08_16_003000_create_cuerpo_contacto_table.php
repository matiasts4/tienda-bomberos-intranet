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
        Schema::create('cuerpo_contacto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contacto_id')->constrained('contactos')->cascadeOnDelete();
            $table->string('cuerpo_id', 15);
            $table->foreign('cuerpo_id')->references('id')->on('cuerpos')->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['contacto_id', 'cuerpo_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cuerpo_contacto');
    }
};
