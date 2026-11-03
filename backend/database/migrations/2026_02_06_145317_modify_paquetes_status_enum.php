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
        // Change column type to string to support more statuses flexibly as it behaves like an Order now
        Schema::table('paquetes', function (Blueprint $table) {
            $table->string('status')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('paquetes', function (Blueprint $table) {
            // Revert is risky if data exists, but for dev env we'll try to revert to enum
            $table->enum('status', ['preparing', 'transit', 'delivered', 'returned'])->change();
        });
    }
};
