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
        Schema::create('pte_stocks', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 50)->unique(); 
            
            $table->unsignedInteger('cantidad')->default(0); 
            
            // --- STOCK CRITICO PARA REPORTESS
            $table->integer('stock_critico')->default(5); 
            // ----------------------------

            $table->foreign('sku')->references('sku')->on('pte_skus')->cascadeOnDelete();
            $table->timestamps();
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pte_stocks');
    }
};