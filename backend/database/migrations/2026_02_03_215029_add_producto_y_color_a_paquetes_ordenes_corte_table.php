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
        Schema::table('paquetes_ordenes_corte', function (Blueprint $table) {
            $table->string('product')->nullable()->after('cutting_order_id');
            $table->string('color')->nullable()->after('product');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('paquetes_ordenes_corte', function (Blueprint $table) {
            $table->dropColumn(['product', 'color']);
        });
    }
};
