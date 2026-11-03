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
            $table->string('cuerpo_id', 15)->nullable()->after('contacto_id');
            $table->string('compania_id', 15)->nullable()->after('cuerpo_id');
        });

        Schema::table('oportunidades', function (Blueprint $table) {
            $table->foreign('cuerpo_id')->references('id')->on('cuerpos')->nullOnDelete();
            $table->foreign('compania_id')->references('id')->on('companias')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('oportunidades', function (Blueprint $table) {
            $table->dropForeign(['cuerpo_id']);
            $table->dropForeign(['compania_id']);
            $table->dropColumn(['cuerpo_id', 'compania_id']);
        });
    }
};
