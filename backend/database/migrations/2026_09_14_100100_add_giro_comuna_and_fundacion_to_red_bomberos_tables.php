<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('cuerpos', function (Blueprint $table) {
            if (!Schema::hasColumn('cuerpos', 'giro')) {
                $table->string('giro', 255)->nullable()->after('direccion');
            }
            if (!Schema::hasColumn('cuerpos', 'comuna_id')) {
                $table->foreignId('comuna_id')->nullable()->after('region_id')
                    ->constrained('comunas')->nullOnDelete();
            }
        });

        Schema::table('clientes', function (Blueprint $table) {
            if (!Schema::hasColumn('clientes', 'comuna_id')) {
                $table->foreignId('comuna_id')->nullable()->after('provincia_id')
                    ->constrained('comunas')->nullOnDelete();
            }
        });

        Schema::table('companias', function (Blueprint $table) {
            // El formulario de "Nueva Compañía" siempre mostró Fecha Fundación,
            // pero la columna no existía y el dato se perdía en silencio.
            // Es opcional: crear una compañía sin fecha debe seguir funcionando.
            if (!Schema::hasColumn('companias', 'fecha_fundacion')) {
                $table->date('fecha_fundacion')->nullable()->after('numero');
            }
        });

        // Por si alguna instalación quedó con la columna en NOT NULL.
        $this->aflojarFechaFundacionCuerpos();
    }

    private function aflojarFechaFundacionCuerpos(): void
    {
        if (!Schema::hasColumn('cuerpos', 'fecha_fundacion')) {
            return;
        }

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        try {
            DB::statement('ALTER TABLE `cuerpos` MODIFY `fecha_fundacion` DATE NULL');
        } catch (\Throwable $e) {
            // No es crítico: si el motor no lo permite, seguimos.
        }
    }

    public function down(): void
    {
        Schema::table('cuerpos', function (Blueprint $table) {
            if (Schema::hasColumn('cuerpos', 'comuna_id')) {
                $table->dropConstrainedForeignId('comuna_id');
            }
            if (Schema::hasColumn('cuerpos', 'giro')) {
                $table->dropColumn('giro');
            }
        });

        Schema::table('clientes', function (Blueprint $table) {
            if (Schema::hasColumn('clientes', 'comuna_id')) {
                $table->dropConstrainedForeignId('comuna_id');
            }
        });

        Schema::table('companias', function (Blueprint $table) {
            if (Schema::hasColumn('companias', 'fecha_fundacion')) {
                $table->dropColumn('fecha_fundacion');
            }
        });
    }
};
