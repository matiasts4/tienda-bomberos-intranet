<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // En producción la columna se agregó a mano antes de esta migración.
        if (Schema::hasColumn('cuerpos', 'giro')) {
            return;
        }

        Schema::table('cuerpos', function (Blueprint $table) {
            $table->string('giro')->nullable()->after('direccion');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('cuerpos', 'giro')) {
            return;
        }

        Schema::table('cuerpos', function (Blueprint $table) {
            $table->dropColumn('giro');
        });
    }
};
