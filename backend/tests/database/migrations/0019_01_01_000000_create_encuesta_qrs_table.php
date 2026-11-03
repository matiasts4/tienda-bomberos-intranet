<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        (require database_path('migrations/2026_10_19_121000_create_encuesta_qrs_table.php'))->up();
    }
};
