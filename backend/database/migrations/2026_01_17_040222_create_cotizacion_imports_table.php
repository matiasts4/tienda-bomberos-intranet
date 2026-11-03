<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cotizacion_imports', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cliente_id')
                ->nullable()
                ->constrained('clientes')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreignId('oportunidad_id')
                ->nullable()
                ->constrained('oportunidades')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreignId('ejecutivo_id')
                ->nullable()
                ->constrained('ejecutivos')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->string('estado', 20)->default('pendiente'); // pendiente|procesando|ok|error
            $table->string('archivo_path', 255);
            $table->text('error')->nullable();

            $table->foreignId('cotizacion_id')
                ->nullable()
                ->constrained('cotizaciones')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotizacion_imports');
    }
};
