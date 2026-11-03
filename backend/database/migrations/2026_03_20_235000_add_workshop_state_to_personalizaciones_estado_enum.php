<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add 'workshop-in-progress' to the estado ENUM
        DB::statement("ALTER TABLE personalizaciones MODIFY COLUMN estado ENUM('pending-definition', 'pending-design', 'designing', 'in-correction', 'approval', 'quality-control', 'completed', 'workshop-in-progress') DEFAULT 'pending-design'");
    }

    public function down(): void
    {
        // Migrate any workshop-in-progress back to completed before removing
        DB::table('personalizaciones')->where('estado', 'workshop-in-progress')->update(['estado' => 'completed']);
        DB::statement("ALTER TABLE personalizaciones MODIFY COLUMN estado ENUM('pending-definition', 'pending-design', 'designing', 'in-correction', 'approval', 'quality-control', 'completed') DEFAULT 'pending-design'");
    }
};
