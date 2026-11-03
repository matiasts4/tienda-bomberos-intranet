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
        Schema::create('item_personalizacion_matrices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grupo_id')->constrained('item_personalizacion_grupos')->onDelete('cascade');
            $table->string('posicion'); // Ej: 'Delantero Izquierdo', 'Brazo Izquierdo'
            $table->string('tecnica')->nullable(); // Ej: 'Bordado', 'DTF'
            $table->string('tipo_contenido')->default('logo'); // 'logo', 'texto_fijo', 'texto_variable'
            $table->text('valor_fijo')->nullable(); // URL de logo o texto fijo
            $table->string('color')->nullable(); // Ej: 'Full Color', 'Blanco'
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_personalizacion_matrices');
    }
};
