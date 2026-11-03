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
        Schema::create('item_personalizacion_tallas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grupo_id')->constrained('item_personalizacion_grupos')->onDelete('cascade');
            $table->string('talla');
            $table->integer('cantidad');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_personalizacion_tallas');
    }
};
