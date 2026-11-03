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
        Schema::table('oportunidades', function (Blueprint $table) {
            $table->foreignId('contacto_id')->nullable()->constrained('contactos')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('oportunidades', function (Blueprint $table) {
            $table->dropForeign(['contacto_id']);
            $table->dropColumn('contacto_id');
        });
    }
};
