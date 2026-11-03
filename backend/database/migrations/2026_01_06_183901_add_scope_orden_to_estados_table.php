<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('estados', function (Blueprint $table) {
            $table->string('scope')->default('oportunidad')->index();
            $table->unsignedInteger('orden')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('estados', function (Blueprint $table) {
            $table->dropColumn(['scope', 'orden']);
        });
    }
};
