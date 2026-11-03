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
        if (\Illuminate\Support\Facades\DB::getDriverName() !== 'sqlite') {
            Schema::table('marketplaces', function (Blueprint $table) {
                $table->text('quote_email')->nullable()->change();
                $table->text('internal_email')->nullable()->change();
                $table->text('dealer_email')->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marketplaces', function (Blueprint $table) {
            $table->string('quote_email', 255)->nullable()->change();
            $table->string('internal_email', 255)->nullable()->change();
            $table->string('dealer_email', 255)->nullable()->change();
        });
    }
};
