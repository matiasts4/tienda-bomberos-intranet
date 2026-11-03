<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personalizaciones', function (Blueprint $table) {
            // Contador de veces que el ítem ha vuelto a "En Proceso" por rechazo de QC
            $table->unsignedTinyInteger('qc_cycles')->default(0)->after('qc_blocked');
            // Nombre caché del operario asignado (para mostrar en tarjeta sin hacer JOIN)
            $table->string('assigned_user_name')->nullable()->after('assigned_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('personalizaciones', function (Blueprint $table) {
            $table->dropColumn(['qc_cycles', 'assigned_user_name']);
        });
    }
};
