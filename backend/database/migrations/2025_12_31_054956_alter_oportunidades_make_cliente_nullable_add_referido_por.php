<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oportunidades', function (Blueprint $table) {

            $table->string('referido_por', 150)->nullable()->after('empresa');

            $table->dropForeign(['cliente_id']);

            $table->unsignedBigInteger('cliente_id')->nullable()->change();

            $table->foreign('cliente_id')->references('id')->on('clientes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('oportunidades', function (Blueprint $table) {

            $table->dropForeign(['cliente_id']);

            $table->unsignedBigInteger('cliente_id')->nullable(false)->change();

            $table->foreign('cliente_id')->references('id')->on('clientes');

            // Eliminar referido_por
            $table->dropColumn('referido_por');
        });
    }
};
