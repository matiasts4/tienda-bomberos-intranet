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
        Schema::create('personalizaciones', function (Blueprint $table) {
            $table->id();

            // Relación opcional con movimiento de inventario (origen)
            // Usamos unsignedBigInteger porque pte_movimientos.id es bigInt
            $table->unsignedBigInteger('pte_movimiento_id')->nullable();

            // Relación con usuario creador
            $table->foreignId('user_id')->constrained('users');

            // Datos del producto
            $table->string('sku');
            $table->string('producto_nombre');
            $table->integer('cantidad')->default(1);

            // Estado y Tipo
            $table->enum('estado', [
                'pending-art',          // Pendiente de Arte
                'art-sent',             // Arte Enviado (esperando aprobación)
                'art-approved',         // Arte Aprobado
                'in-personalization',   // En Personalización (Taller)
                'personalization-finished' // Terminado
            ])->default('pending-art');

            $table->enum('tipo', ['embroidery', 'print', 'patch', 'other'])->default('embroidery');

            // Configuración visual (JSON con posiciones, imagenes, textos)
            $table->json('configuracion')->nullable();

            // Detalles extra
            $table->text('notas')->nullable();
            $table->enum('prioridad', ['low', 'medium', 'high'])->default('medium');

            $table->timestamps();
            $table->softDeletes();

            // Indices
            $table->index('estado');
            $table->index('sku');

            // Foreign key manual si es necesario, pero pte_movimientos podría no usar id estandar
            // Dejamos pte_movimiento_id como referencia simple por ahora para evitar errores de integridad si pte cambia
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personalizaciones');
    }
};
