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
        Schema::dropIfExists('package_cutting_orders');
        Schema::dropIfExists('package_items');
        Schema::dropIfExists('packages');
        Schema::dropIfExists('cutting_order_items');
        Schema::dropIfExists('cutting_order_supplies');
        Schema::dropIfExists('cutting_orders');
        Schema::dropIfExists('factory_logs');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Tables dropped permanently. Recreation logic omitted to avoid restoring deprecated tables.
    }
};
