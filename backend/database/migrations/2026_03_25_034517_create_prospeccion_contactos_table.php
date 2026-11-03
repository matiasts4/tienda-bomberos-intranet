<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('prospeccion_contactos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contacto_id')->constrained('contactos')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('tipo', [
                'no_contesta',
                'buzon',
                'llamar_luego',
                'no_califica',
                'calificado',
            ]);
            $table->text('notas')->nullable();
            $table->date('proxima_llamada')->nullable(); // Next follow-up date
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prospeccion_contactos');
    }
};
