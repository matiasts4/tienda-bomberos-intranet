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
        Schema::create('companias', function (Blueprint $table) {
            $table->string('id', 15)->primary();
            $table->string('cuerpo_id', 15);
            $table->foreign('cuerpo_id')->references('id')->on('cuerpos')->cascadeOnDelete();
            $table->string('nombre');
            $table->integer('numero'); // e.g. 1, 2, ... 99

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companias');
    }
};
