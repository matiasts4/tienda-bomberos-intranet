<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('oportunidades', function (Blueprint $table) {
            // OJO: primero dropea la FK actual (la que apunta a users)
            $table->dropForeign(['ejecutivo_id']);

            // Si la columna no es unsignedBigInteger, conviene asegurar tipo
            // (descomenta si lo necesitas)
            // $table->unsignedBigInteger('ejecutivo_id')->change();

            // Ahora crea la FK correcta hacia ejecutivos
            $table->foreign('ejecutivo_id')
                  ->references('id')
                  ->on('ejecutivos')
                  ->onDelete('set null'); // o ->restrict() si quieres obligar consistencia
        });
    }

    public function down(): void
    {
        Schema::table('oportunidades', function (Blueprint $table) {
            $table->dropForeign(['ejecutivo_id']);

            // vuelve a users si quisieras revertir (opcional)
            $table->foreign('ejecutivo_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
        });
    }
};
