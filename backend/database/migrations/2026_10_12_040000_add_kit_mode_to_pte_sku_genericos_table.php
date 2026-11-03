<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pte_sku_genericos', function (Blueprint $table) {
            $table->boolean('modo_kit')->default(false);
            $table->json('componentes_kit')->nullable();
        });
    }
    public function down(): void
    {
        throw new RuntimeException('Se conserva la composición de kits y sus datos. No se elimina ninguna columna.');
    }
};
