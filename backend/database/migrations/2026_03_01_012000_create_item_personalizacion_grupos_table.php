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
        Schema::create('item_personalizacion_grupos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cotizacion_item_id')->constrained('detalle_cotizaciones')->onDelete('cascade');
            $table->string('nombre_grupo')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_personalizacion_grupos');
    }
};
