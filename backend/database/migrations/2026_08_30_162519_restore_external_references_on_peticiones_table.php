<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('peticiones', 'referencia_externa')) {
            Schema::table('peticiones', function (Blueprint $table) {
                $table->string('referencia_externa', 100)->nullable()->index();
            });
        }
        if (!Schema::hasColumn('peticiones', 'cliente_externo')) {
            Schema::table('peticiones', function (Blueprint $table) {
                $table->string('cliente_externo', 255)->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restauración compatible con instalaciones que ya tenían estos campos.
        // No eliminar referencias históricas al revertir este parche.
    }
};
