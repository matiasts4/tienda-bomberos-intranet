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
        // contactos.cliente_id: hoy NOT NULL + cascadeOnDelete. Pasa a nullable + nullOnDelete
        // porque un Contacto ya no depende de un Cliente (se vincula a Cuerpo/Compañía vía pivotes).
        Schema::table('contactos', function (Blueprint $table) {
            $table->dropForeign(['cliente_id']);
        });
        Schema::table('contactos', function (Blueprint $table) {
            $table->string('cliente_id', 15)->nullable()->change();
        });
        Schema::table('contactos', function (Blueprint $table) {
            $table->foreign('cliente_id')->references('id')->on('clientes')->nullOnDelete();
        });

        // cotizaciones.cliente_id: hoy NOT NULL + cascadeOnDelete. Pasa a nullable + nullOnDelete
        // para que una Cotizacion pueda anclarse solo vía contacto_id/cuerpo_id/compania_id.
        // Esto también evita que borrar un cliente-espejo (más adelante) arrastre en cascada
        // cotizaciones reales.
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->dropForeign(['cliente_id']);
        });
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->string('cliente_id', 15)->nullable()->change();
        });
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->foreign('cliente_id')->references('id')->on('clientes')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op intencional: una vez que existan filas con cliente_id null (esperado tras
        // el backfill de FASE B), revertir a NOT NULL fallaría contra esos datos.
    }
};
