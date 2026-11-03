<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        (require database_path('migrations/2026_10_19_120000_link_surveys_to_quote_sources.php'))->up();
    }
};
