<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('rut_empresa', 20)->nullable()->after('nombre_empresa');
            $table->date('fecha_fundacion')->nullable()->after('rut_empresa');
            $table->string('direccion')->nullable()->after('fecha_fundacion');
            $table->string('superintendente')->nullable()->after('cargo_contacto');
            $table->string('comandante')->nullable()->after('superintendente');
            $table->integer('numero_companias')->nullable()->after('comandante');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            //
        });
    }
};
