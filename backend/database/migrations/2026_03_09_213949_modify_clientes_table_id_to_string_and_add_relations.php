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
        // 1. Drop existing foreign keys across tables (Idempotent)
        $drops = [
            'clientes' => 'clientes_parent_id_foreign',
            'contactos' => 'contactos_cliente_id_foreign',
            'cotizacion_imports' => 'cotizacion_imports_cliente_id_foreign',
            'cotizaciones' => 'cotizaciones_cliente_id_foreign',
            'despachos' => 'despachos_cliente_id_foreign',
            'oportunidades' => 'oportunidades_cliente_id_foreign',
        ];

        foreach ($drops as $table => $fk) {
            try {
                DB::statement("ALTER TABLE {$table} DROP FOREIGN KEY {$fk}");
            } catch (\Exception $e) {
                // Key might already be dropped if migration failed midway previously
            }
        }

        // 2. Modify columns to string(15)
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('id', 15)->change();
            if (Schema::hasColumn('clientes', 'parent_id')) {
                $table->string('parent_id', 15)->nullable()->change();
            }
            // Add new relations
            if (!Schema::hasColumn('clientes', 'cuerpo_id')) {
                $table->string('cuerpo_id', 15)->nullable();
                $table->foreign('cuerpo_id')->references('id')->on('cuerpos')->nullOnDelete();
            }
            if (!Schema::hasColumn('clientes', 'compania_id')) {
                $table->string('compania_id', 15)->nullable();
                $table->foreign('compania_id')->references('id')->on('companias')->nullOnDelete();
            }
        });

        Schema::table('contactos', function (Blueprint $table) {
            $table->string('cliente_id', 15)->change(); });
        Schema::table('cotizacion_imports', function (Blueprint $table) {
            $table->string('cliente_id', 15)->nullable()->change(); });
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->string('cliente_id', 15)->change(); });
        Schema::table('despachos', function (Blueprint $table) {
            $table->string('cliente_id', 15)->change(); });
        Schema::table('oportunidades', function (Blueprint $table) {
            $table->string('cliente_id', 15)->nullable()->change(); });

        // 3. Re-add foreign keys
        // We catch exception in case they already exist
        $adds = [
            "ALTER TABLE clientes ADD CONSTRAINT clientes_parent_id_foreign FOREIGN KEY (parent_id) REFERENCES clientes(id) ON DELETE SET NULL",
            "ALTER TABLE contactos ADD CONSTRAINT contactos_cliente_id_foreign FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE",
            "ALTER TABLE cotizacion_imports ADD CONSTRAINT cotizacion_imports_cliente_id_foreign FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE",
            "ALTER TABLE cotizaciones ADD CONSTRAINT cotizaciones_cliente_id_foreign FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE",
            "ALTER TABLE despachos ADD CONSTRAINT despachos_cliente_id_foreign FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE",
            "ALTER TABLE oportunidades ADD CONSTRAINT oportunidades_cliente_id_foreign FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL",
        ];

        foreach ($adds as $sql) {
            try {
                DB::statement($sql);
            } catch (\Exception $e) {
                // Ignore if it already exists
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // For brevity and safety, reversing this involves doing the exact opposite.
        // But reverting a String PK back to BigInteger AI is often impossible if actual 7-digit strings were inserted.
    }
};
