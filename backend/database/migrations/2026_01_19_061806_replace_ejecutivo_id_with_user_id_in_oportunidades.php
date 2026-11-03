<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Importante: si tienes FKs con nombres distintos, esto evita el bloqueo
        Schema::disableForeignKeyConstraints();

        // 1) OPORTUNIDADES: quitar FK a ejecutivos y reemplazar por users
        if (Schema::hasTable('oportunidades')) {

            // Drop FK si existe (aunque el nombre del constraint sea raro)
            Schema::table('oportunidades', function (Blueprint $table) {
                try { $table->dropForeign(['ejecutivo_id']); } catch (\Throwable $e) {}
            });

            // Crear columna nueva para user
            Schema::table('oportunidades', function (Blueprint $table) {
                if (!Schema::hasColumn('oportunidades', 'ejecutivo_user_id')) {
                    $table->unsignedBigInteger('ejecutivo_user_id')->nullable()->after('cliente_id');
                }
            });

            // Copiar valores antiguos (ojo: solo sirve si ids coinciden; si no, quedará como "dato sucio")
            // Si NO quieres copiar, comenta esta línea.
            DB::statement('UPDATE oportunidades SET ejecutivo_user_id = ejecutivo_id');

            // Eliminar columna vieja
            Schema::table('oportunidades', function (Blueprint $table) {
                if (Schema::hasColumn('oportunidades', 'ejecutivo_id')) {
                    $table->dropColumn('ejecutivo_id');
                }
            });

            // Crear ejecutivo_id nuevo apuntando a users
            Schema::table('oportunidades', function (Blueprint $table) {
                if (!Schema::hasColumn('oportunidades', 'ejecutivo_id')) {
                    $table->unsignedBigInteger('ejecutivo_id')->nullable()->after('cliente_id');
                }
            });

            DB::statement('UPDATE oportunidades SET ejecutivo_id = ejecutivo_user_id');

            Schema::table('oportunidades', function (Blueprint $table) {
                $table->foreign('ejecutivo_id')->references('id')->on('users')->nullOnDelete();
            });

            // Borrar temporal
            Schema::table('oportunidades', function (Blueprint $table) {
                if (Schema::hasColumn('oportunidades', 'ejecutivo_user_id')) {
                    $table->dropColumn('ejecutivo_user_id');
                }
            });
        }

        // 2) COTIZACIONES: si también tiene ejecutivo_id -> cambiar a users
        if (Schema::hasTable('cotizaciones') && Schema::hasColumn('cotizaciones', 'ejecutivo_id')) {

            Schema::table('cotizaciones', function (Blueprint $table) {
                try { $table->dropForeign(['ejecutivo_id']); } catch (\Throwable $e) {}
            });

            // crear temp
            Schema::table('cotizaciones', function (Blueprint $table) {
                if (!Schema::hasColumn('cotizaciones', 'ejecutivo_user_id')) {
                    $table->unsignedBigInteger('ejecutivo_user_id')->nullable()->after('cliente_id');
                }
            });

            // copiar (misma nota de arriba)
            DB::statement('UPDATE cotizaciones SET ejecutivo_user_id = ejecutivo_id');

            // eliminar viejo
            Schema::table('cotizaciones', function (Blueprint $table) {
                if (Schema::hasColumn('cotizaciones', 'ejecutivo_id')) {
                    $table->dropColumn('ejecutivo_id');
                }
            });

            // recrear a users
            Schema::table('cotizaciones', function (Blueprint $table) {
                if (!Schema::hasColumn('cotizaciones', 'ejecutivo_id')) {
                    $table->unsignedBigInteger('ejecutivo_id')->nullable()->after('cliente_id');
                }
            });

            DB::statement('UPDATE cotizaciones SET ejecutivo_id = ejecutivo_user_id');

            Schema::table('cotizaciones', function (Blueprint $table) {
                $table->foreign('ejecutivo_id')->references('id')->on('users')->nullOnDelete();
            });

            // borrar temporal
            Schema::table('cotizaciones', function (Blueprint $table) {
                if (Schema::hasColumn('cotizaciones', 'ejecutivo_user_id')) {
                    $table->dropColumn('ejecutivo_user_id');
                }
            });
        }

        // 3) Ahora sí, dropear tabla ejecutivos
        Schema::dropIfExists('ejecutivos');

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        // No implemento rollback completo porque el mapeo user<->ejecutivo no es recuperable sin regla clara.
        // Si necesitas rollback real, lo armamos con una tabla puente.
    }
};
