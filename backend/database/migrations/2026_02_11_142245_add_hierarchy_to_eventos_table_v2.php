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
        Schema::table('eventos', function (Blueprint $table) {
            $table->string('nivel')->default('usuario'); // usuario, area, corporativo
            $table->string('area')->nullable(); // comercial, fabrica, bodega, etc.
            $table->string('categoria')->nullable(); // Para ingresos manuales según categorías definidas
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->dropColumn(['nivel', 'area', 'categoria']);
        });
    }
};
