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
        Schema::table('oportunidad_gestiones', function (Blueprint $table) {
            $table->string('tipo_contacto')->after('user_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('oportunidad_gestiones', function (Blueprint $table) {
            $table->dropColumn('tipo_contacto');
        });
    }
};
