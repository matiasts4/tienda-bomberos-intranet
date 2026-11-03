<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        if (!Schema::hasTable('cotizaciones') || !Schema::hasColumn('cotizaciones', 'ejecutivo_id')) {
            Schema::enableForeignKeyConstraints();
            return;
        }

        // ✅ Drop FK real (sin asumir nombre)
        $fk = DB::selectOne("
            SELECT CONSTRAINT_NAME AS name
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'cotizaciones'
              AND COLUMN_NAME = 'ejecutivo_id'
              AND REFERENCED_TABLE_NAME IS NOT NULL
            LIMIT 1
        ");

        if ($fk && !empty($fk->name)) {
            DB::statement("ALTER TABLE `cotizaciones` DROP FOREIGN KEY `{$fk->name}`");
        }

        // ✅ Drop index si existe (a veces queda aparte)
        $idx = DB::selectOne("
            SELECT INDEX_NAME AS name
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'cotizaciones'
              AND COLUMN_NAME = 'ejecutivo_id'
            LIMIT 1
        ");

        if ($idx && !empty($idx->name) && $idx->name !== 'PRIMARY') {
            try {
                DB::statement("ALTER TABLE `cotizaciones` DROP INDEX `{$idx->name}`");
            } catch (\Throwable $e) {
                // no bloqueamos
            }
        }

        // Crear user_id si no existe
        Schema::table('cotizaciones', function (Blueprint $table) {
            if (!Schema::hasColumn('cotizaciones', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('cliente_id');
            }
        });

        // Copiar valores
        DB::statement("UPDATE cotizaciones SET user_id = ejecutivo_id WHERE user_id IS NULL");

        // Dropear ejecutivo_id
        Schema::table('cotizaciones', function (Blueprint $table) {
            if (Schema::hasColumn('cotizaciones', 'ejecutivo_id')) {
                $table->dropColumn('ejecutivo_id');
            }
        });

        // FK user_id -> users
        if (Schema::hasTable('users')) {
            Schema::table('cotizaciones', function (Blueprint $table) {
                try {
                    $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
                } catch (\Throwable $e) {
                    // por si ya existía
                }
            });
        }

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        // rollback no trivial
    }
};
