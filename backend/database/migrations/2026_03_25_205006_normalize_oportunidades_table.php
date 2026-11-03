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
        // Data migration: try to ensure all records have a contacto_id
        // (Most should have it already from previous migrations, but safe check)
        
        Schema::table('oportunidades', function (Blueprint $table) {
            $table->dropColumn(['nombre_contacto', 'numero_contacto', 'empresa']);
        });
    }

    public function down(): void
    {
        Schema::table('oportunidades', function (Blueprint $table) {
            $table->string('nombre_contacto', 150)->nullable();
            $table->string('numero_contacto', 30)->nullable();
            $table->string('empresa', 150)->nullable();
        });
    }
};
