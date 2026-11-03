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
        Schema::table('pte_sku_genericos', function (Blueprint $table) {
            $table->string('imagen_back_url')->nullable()->after('imagen_url');
            $table->string('imagen_side_url')->nullable()->after('imagen_back_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pte_sku_genericos', function (Blueprint $table) {
            $table->dropColumn(['imagen_back_url', 'imagen_side_url']);
        });
    }
};
