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
        Schema::table('item_personalizacion_tallas', function (Blueprint $table) {
            $table->string('genero')->nullable()->after('grupo_id');
            $table->string('color')->nullable()->after('genero');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('item_personalizacion_tallas', function (Blueprint $table) {
            $table->dropColumn(['genero', 'color']);
        });
    }
};
