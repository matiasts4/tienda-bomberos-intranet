<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            // Default 'bomberos' para que los espejos creados por CuerpoController/CompaniaController
            // queden bien clasificados sin tocar esos controladores.
            $table->string('tipo', 20)->default('bomberos')->index()->after('nombre_empresa');
        });

        // Backfill: los registros existentes sin vínculo a la estructura de bomberos
        // son clientes directos (empresas / particulares).
        DB::table('clientes')
            ->whereNull('cuerpo_id')
            ->whereNull('compania_id')
            ->whereNull('parent_id')
            ->update(['tipo' => 'empresa']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropIndex(['tipo']);
            $table->dropColumn('tipo');
        });
    }
};
