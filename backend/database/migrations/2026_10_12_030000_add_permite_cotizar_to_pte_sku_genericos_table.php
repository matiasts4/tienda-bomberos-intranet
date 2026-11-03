<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // DEFAULT true deja cotizables todos los productos existentes, sin borrar datos.
        Schema::table('pte_sku_genericos', function (Blueprint $table) {
            $table->boolean('permite_cotizar')->default(true)->after('permite_personalizar');
        });
    }

    public function down(): void
    {
        // La autorización del usuario prohíbe eliminar datos/estructura existente.
        throw new RuntimeException('Esta migración conserva permite_cotizar. Revertir el código no debe eliminar la columna ni sus datos.');
    }
};
