<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Modificar el ENUM de status para incluir los estados del frontend y bodega
        DB::statement("ALTER TABLE ordenes_corte MODIFY COLUMN status ENUM('order-received', 'cutting', 'cutting-finished', 'transit', 'pending-warehouse', 'in-warehouse', 'sewing', 'quality-control', 'finished') NOT NULL DEFAULT 'order-received'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir a los estados originales
        DB::statement("ALTER TABLE ordenes_corte MODIFY COLUMN status ENUM('order-received', 'in-cutting', 'in-assembly', 'in-quality-control', 'completed', 'delivered') NOT NULL DEFAULT 'order-received'");
    }
};
