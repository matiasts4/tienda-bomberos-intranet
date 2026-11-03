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
        Schema::table('oportunidades', function (Blueprint $table) {
            $table->string('canal', 50)->nullable()->after('referido_por');
            $table->text('mensaje_prospeccion')->nullable()->after('canal');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('oportunidades', function (Blueprint $table) {
            $table->dropColumn(['canal', 'mensaje_prospeccion']);
        });
    }
};
