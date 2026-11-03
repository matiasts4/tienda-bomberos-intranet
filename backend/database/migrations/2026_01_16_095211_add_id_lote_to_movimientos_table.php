<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('pte_movimientos', function (Blueprint $table) {
            // Agregamos la columna id_lote, permitiendo nulos (para registros viejos)
            $table->string('id_lote')->nullable()->after('saldo_nuevo'); 
            
            // Creamos un índice para que buscar por lote sea rápido
            $table->index('id_lote');
        });
    }

    public function down()
    {
        Schema::table('pte_movimientos', function (Blueprint $table) {
            $table->dropColumn('id_lote');
        });
    }
};