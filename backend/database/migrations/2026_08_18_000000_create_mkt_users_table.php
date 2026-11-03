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
        Schema::create('mkt_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('marketplace_id');
            $table->string('name');
            $table->string('rut');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('company_unit')->nullable();
            $table->string('cargo')->nullable();
            $table->string('password');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->foreign('marketplace_id')
                  ->references('id')
                  ->on('marketplaces')
                  ->onDelete('cascade');

            $table->unique(['marketplace_id', 'rut']);
            $table->index(['marketplace_id', 'active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mkt_users');
    }
};
