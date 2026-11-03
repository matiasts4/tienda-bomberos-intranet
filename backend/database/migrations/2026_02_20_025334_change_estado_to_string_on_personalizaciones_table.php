<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('personalizaciones', function (Blueprint $table) {
            $table->string('estado', 50)->default('pending-definition')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('personalizaciones', function (Blueprint $table) {
            // Reversing might be tricky with enum if data is lost, we'll just leave string
            // $table->enum('estado', [...])->change();
        });
    }
};
