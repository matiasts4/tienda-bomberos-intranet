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
            if (!Schema::hasColumn('despachos', 'voucher_url')) {
                $table->string('voucher_url')->nullable()->after('agencia');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('despachos', function (Blueprint $table) {
            if (Schema::hasColumn('despachos', 'voucher_url')) {
                $table->dropColumn('voucher_url');
            }
        });
    }
};
