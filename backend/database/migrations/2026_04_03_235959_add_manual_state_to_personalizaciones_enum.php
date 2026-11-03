<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE personalizaciones MODIFY COLUMN estado ENUM('pending-definition', 'pending-design', 'designing', 'in-correction', 'approval', 'quality-control', 'completed', 'workshop-in-progress', 'workshop-processing', 'workshop-done', 'manual') DEFAULT 'pending-design'");
    }

    public function down(): void
    {
        DB::table('personalizaciones')->where('estado', 'manual')->update(['estado' => 'pending-design']);
        DB::statement("ALTER TABLE personalizaciones MODIFY COLUMN estado ENUM('pending-definition', 'pending-design', 'designing', 'in-correction', 'approval', 'quality-control', 'completed', 'workshop-in-progress', 'workshop-processing', 'workshop-done') DEFAULT 'pending-design'");
    }
};
