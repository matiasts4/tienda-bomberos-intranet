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
        Schema::table('personalizaciones', function (Blueprint $table) {
            $table->string('proceso_previo')->nullable()->after('siguiente_proceso');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('personalizaciones', function (Blueprint $table) {
            $table->dropColumn('proceso_previo');
        });
    }
};
