<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 0) Dropear FK si existe (con nombre real)
        $fk = DB::selectOne("
            SELECT CONSTRAINT_NAME AS name
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'oportunidades'
              AND COLUMN_NAME = 'ejecutivo_id'
              AND REFERENCED_TABLE_NAME IS NOT NULL
            LIMIT 1
        ");

        if ($fk?->name) {
            DB::statement("ALTER TABLE oportunidades DROP FOREIGN KEY `{$fk->name}`");
        }

        // 1) Hacer nullable la columna
        Schema::table('oportunidades', function (Blueprint $table) {
            $table->unsignedBigInteger('ejecutivo_id')->nullable()->change();
        });

        // 2) Limpiar huérfanos antes de crear FK
        DB::statement("
            UPDATE oportunidades o
            LEFT JOIN users u ON u.id = o.ejecutivo_id
            SET o.ejecutivo_id = NULL
            WHERE o.ejecutivo_id IS NOT NULL
              AND u.id IS NULL
        ");

        // 3) Crear FK con nullOnDelete
        Schema::table('oportunidades', function (Blueprint $table) {
            $table->foreign('ejecutivo_id')
                ->references('id')->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        // drop FK si existe
        $fk = DB::selectOne("
            SELECT CONSTRAINT_NAME AS name
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'oportunidades'
              AND COLUMN_NAME = 'ejecutivo_id'
              AND REFERENCED_TABLE_NAME IS NOT NULL
            LIMIT 1
        ");

        if ($fk?->name) {
            DB::statement("ALTER TABLE oportunidades DROP FOREIGN KEY `{$fk->name}`");
        }

        Schema::table('oportunidades', function (Blueprint $table) {
            $table->unsignedBigInteger('ejecutivo_id')->nullable(false)->change();
        });

        Schema::table('oportunidades', function (Blueprint $table) {
            $table->foreign('ejecutivo_id')->references('id')->on('users');
        });
    }
};
