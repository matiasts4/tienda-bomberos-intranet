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
        DB::statement("ALTER TABLE ordenes_corte MODIFY COLUMN status ENUM('order-received', 'cutting', 'cutting-finished', 'transit', 'pending-warehouse', 'in-warehouse', 'sewing', 'quality-control', 'finished') NOT NULL DEFAULT 'order-received'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No revertimos para evitar perdida de datos si ya se usaron los nuevos estados
    }
};
