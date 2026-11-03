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
        Schema::table('reparaciones', function (Blueprint $table) {
            // Cambiar cantidad_perdida de decimal a entero sin signo
            $table->unsignedInteger('cantidad_perdida')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reparaciones', function (Blueprint $table) {
            // Revertir a decimal(12, 3) si es necesario hacer rollback
            $table->decimal('cantidad_perdida', 12, 3)->change();
        });
    }
};
