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
        Schema::table('encuesta_satisfaccions', function (Blueprint $table) {
            $table->string('vendedor_manual', 100)->nullable()->after('vendedor_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('encuesta_satisfaccions', function (Blueprint $table) {
            $table->dropColumn('vendedor_manual');
        });
    }
};
