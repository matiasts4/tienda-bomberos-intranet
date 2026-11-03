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
        Schema::table('despachos', function (Blueprint $table) {
            if (!Schema::hasColumn('despachos', 'agencia')) {
                $table->string('agencia', 100)->nullable()->after('ciudad');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('despachos', function (Blueprint $table) {
            if (Schema::hasColumn('despachos', 'agencia')) {
                $table->dropColumn('agencia');
            }
        });
    }
};
