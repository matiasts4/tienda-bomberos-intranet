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
        if (Schema::hasTable('prospeccion_tipos')) {
            Schema::table('prospeccion_tipos', function (Blueprint $table) {
                if (Schema::hasColumn('prospeccion_tipos', 'icon') && !Schema::hasColumn('prospeccion_tipos', 'icono')) {
                    $table->renameColumn('icon', 'icono');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('prospeccion_tipos')) {
            Schema::table('prospeccion_tipos', function (Blueprint $table) {
                if (Schema::hasColumn('prospeccion_tipos', 'icono') && !Schema::hasColumn('prospeccion_tipos', 'icon')) {
                    $table->renameColumn('icono', 'icon');
                }
            });
        }
    }
};
