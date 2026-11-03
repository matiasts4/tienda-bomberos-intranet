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
        Schema::table('marketplaces', function (Blueprint $table) {
            if (!Schema::hasColumn('marketplaces', 'hero_image_url')) {
                $table->string('hero_image_url', 500)->nullable()->after('logo_url');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marketplaces', function (Blueprint $table) {
            if (Schema::hasColumn('marketplaces', 'hero_image_url')) {
                $table->dropColumn('hero_image_url');
            }
        });
    }
};
