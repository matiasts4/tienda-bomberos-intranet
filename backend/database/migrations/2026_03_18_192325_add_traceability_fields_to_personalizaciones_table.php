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
        Schema::table('personalizaciones', function (Blueprint $table) {
            $table->integer('n_item')->nullable()->after('cotizacion_id');
            $table->string('siguiente_proceso')->nullable()->after('tipo');
        });

        // Extendemos el Enum de tipo para incluir la versión mixta
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE personalizaciones MODIFY COLUMN tipo ENUM('embroidery', 'print', 'patch', 'other', 'mixed') DEFAULT 'embroidery'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('personalizaciones', function (Blueprint $table) {
            $table->dropColumn(['n_item', 'siguiente_proceso']);
        });

        \Illuminate\Support\Facades\DB::statement("ALTER TABLE personalizaciones MODIFY COLUMN tipo ENUM('embroidery', 'print', 'patch', 'other') DEFAULT 'embroidery'");
    }
};
