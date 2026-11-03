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
        Schema::table('mkt_products', function (Blueprint $table) {
            $table->decimal('price', 12, 2)->nullable()->default(0)->after('variants_json');
        });

        Schema::table('mkt_quote_items', function (Blueprint $table) {
            $table->decimal('unit_price', 12, 2)->nullable()->default(0)->after('product_sku');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mkt_quote_items', function (Blueprint $table) {
            $table->dropColumn('unit_price');
        });

        Schema::table('mkt_products', function (Blueprint $table) {
            $table->dropColumn('price');
        });
    }
};
