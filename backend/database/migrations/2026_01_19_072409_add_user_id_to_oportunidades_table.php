<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oportunidades', function (Blueprint $table) {
            // 1) agregamos user_id (nullable primero para no romper datos existentes)
            if (!Schema::hasColumn('oportunidades', 'user_id')) {
                $table->foreignId('user_id')
                    ->nullable()
                    ->after('cliente_id')
                    ->constrained('users');
            }
        });

        /**
         * 2) si tu tabla todavía tenía ejecutivo_id, puedes migrar datos:
         * - Si tu "ejecutivo_id" ya no existe, esto no hace nada.
         * - Si existe, dejamos user_id con algún valor para no romper "required" después.
         */
        if (Schema::hasColumn('oportunidades', 'ejecutivo_id')) {
            // intenta mapear ejecutivo_id -> user_id (si no tienes mapeo real, al menos setea 1)
            // OJO: esto es un "parche" si estabas usando ejecutivos antes.
            // Mejor: tú defines cómo mapear. Aquí lo dejamos en 1.
            DB::statement("UPDATE oportunidades SET user_id = COALESCE(user_id, 1) WHERE user_id IS NULL");
        } else {
            // si no existe ejecutivo_id, igual setea un default si hay registros
            DB::statement("UPDATE oportunidades SET user_id = COALESCE(user_id, 1) WHERE user_id IS NULL");
        }

        // 3) Ahora sí: hacemos user_id NOT NULL (solo si quieres obligarlo)
        Schema::table('oportunidades', function (Blueprint $table) {
            // si tu proyecto está en MySQL, modificar columna requiere doctrine/dbal
            // si NO quieres instalar dbal, deja user_id nullable y valida en app.
        });

        // 4) (Opcional) eliminar ejecutivo_id si existe
        // Hazlo solo cuando estés 100% seguro que no lo usas en ningún lado.
        if (Schema::hasColumn('oportunidades', 'ejecutivo_id')) {
            Schema::table('oportunidades', function (Blueprint $table) {
                $table->dropConstrainedForeignId('ejecutivo_id');
            });
        }
    }

    public function down(): void
    {
        // (Opcional) volver a ejecutivo_id NO lo recomiendo, pero dejamos rollback limpio
        if (Schema::hasColumn('oportunidades', 'user_id')) {
            Schema::table('oportunidades', function (Blueprint $table) {
                $table->dropConstrainedForeignId('user_id');
            });
        }
    }
};
