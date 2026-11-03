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
            $table->string('cliente_id', 15)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Never invent legacy clients or delete dispatched orders during rollback.
        if (\Illuminate\Support\Facades\DB::table('despachos')->whereNull('cliente_id')->exists()) {
            throw new \RuntimeException('Hay despachos sin cliente antiguo. No se puede restaurar NOT NULL sin conciliar esos pedidos.');
        }
        Schema::table('despachos', function (Blueprint $table) {
            $table->string('cliente_id', 15)->nullable(false)->change();
        });
    }
};
