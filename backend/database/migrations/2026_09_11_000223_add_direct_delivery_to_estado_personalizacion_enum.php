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
        \Illuminate\Support\Facades\DB::statement("
            ALTER TABLE `cotizaciones` 
            MODIFY COLUMN `estado_personalizacion` 
            ENUM('pending_configuration', 'configured', 'sent_to_personalization', 'direct_delivery') 
            NULL DEFAULT NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \Illuminate\Support\Facades\DB::statement("
            ALTER TABLE `cotizaciones` 
            MODIFY COLUMN `estado_personalizacion` 
            ENUM('pending_configuration', 'configured', 'sent_to_personalization') 
            NULL DEFAULT NULL
        ");
    }
};
