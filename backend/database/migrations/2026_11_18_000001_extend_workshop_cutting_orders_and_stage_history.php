<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Extiende las tablas de producción de taller para vincular órdenes de corte
     * con cotizaciones aprobadas, compañías/cuerpos institucionales, responsable
     * operativo y trazabilidad detallada de etapas.
     */
    public function up(): void
    {
        Schema::table('ordenes_corte', function (Blueprint $table) {
            if (! Schema::hasColumn('ordenes_corte', 'cotizacion_id')) {
                $table->unsignedBigInteger('cotizacion_id')->nullable()->after('code');
                $table->index('cotizacion_id', 'ordenes_corte_cotizacion_id_idx');
                $table->foreign('cotizacion_id', 'ordenes_corte_cotizacion_id_fk')
                    ->references('id')
                    ->on('cotizaciones')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('ordenes_corte', 'cuerpo_id')) {
                $table->string('cuerpo_id', 15)->nullable()->after('cotizacion_id');
                $table->index('cuerpo_id', 'ordenes_corte_cuerpo_id_idx');
                $table->foreign('cuerpo_id', 'ordenes_corte_cuerpo_id_fk')
                    ->references('id')
                    ->on('cuerpos')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('ordenes_corte', 'compania_id')) {
                $table->string('compania_id', 15)->nullable()->after('cuerpo_id');
                $table->index('compania_id', 'ordenes_corte_compania_id_idx');
                $table->foreign('compania_id', 'ordenes_corte_compania_id_fk')
                    ->references('id')
                    ->on('companias')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('ordenes_corte', 'responsable_id')) {
                $table->unsignedBigInteger('responsable_id')->nullable()->after('compania_id');
                $table->index('responsable_id', 'ordenes_corte_responsable_id_idx');
                $table->foreign('responsable_id', 'ordenes_corte_responsable_id_fk')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('ordenes_corte', 'fecha_compromiso')) {
                $table->date('fecha_compromiso')->nullable()->after('estimated_days');
            }
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement(
                "ALTER TABLE ordenes_corte MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'order-received'"
            );
        }

        Schema::table('items_ordenes_corte', function (Blueprint $table) {
            if (! Schema::hasColumn('items_ordenes_corte', 'detalle_cotizacion_id')) {
                $table->unsignedBigInteger('detalle_cotizacion_id')->nullable()->after('cutting_order_id');
                $table->index('detalle_cotizacion_id', 'items_ordenes_corte_detalle_cot_id_idx');
                $table->foreign('detalle_cotizacion_id', 'items_ordenes_corte_detalle_cot_id_fk')
                    ->references('id')
                    ->on('detalle_cotizaciones')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('items_ordenes_corte', 'sku')) {
                $table->string('sku', 64)->nullable()->after('detalle_cotizacion_id');
            }

            if (! Schema::hasColumn('items_ordenes_corte', 'personalizacion')) {
                $table->json('personalizacion')->nullable()->after('fabric_type');
            }
        });

        Schema::table('historial_etapas', function (Blueprint $table) {
            if (! Schema::hasColumn('historial_etapas', 'etapa_anterior')) {
                $table->string('etapa_anterior', 50)->nullable()->after('estado_nuevo_id');
            }

            if (! Schema::hasColumn('historial_etapas', 'etapa_nueva')) {
                $table->string('etapa_nueva', 50)->nullable()->after('etapa_anterior');
            }

            if (! Schema::hasColumn('historial_etapas', 'observaciones')) {
                $table->text('observaciones')->nullable()->after('etapa_nueva');
            }
        });
    }

    /**
     * Revierte las columnas añadidas para trazabilidad de taller.
     */
    public function down(): void
    {
        Schema::table('historial_etapas', function (Blueprint $table) {
            $drop = array_values(array_filter(
                ['etapa_anterior', 'etapa_nueva'],
                fn (string $column) => Schema::hasColumn('historial_etapas', $column)
            ));

            if (! empty($drop)) {
                $table->dropColumn($drop);
            }
        });

        Schema::table('items_ordenes_corte', function (Blueprint $table) {
            if (Schema::hasColumn('items_ordenes_corte', 'detalle_cotizacion_id')) {
                $table->dropForeign('items_ordenes_corte_detalle_cot_id_fk');
                $table->dropIndex('items_ordenes_corte_detalle_cot_id_idx');
            }

            $drop = array_values(array_filter(
                ['detalle_cotizacion_id', 'sku', 'personalizacion'],
                fn (string $column) => Schema::hasColumn('items_ordenes_corte', $column)
            ));

            if (! empty($drop)) {
                $table->dropColumn($drop);
            }
        });

        Schema::table('ordenes_corte', function (Blueprint $table) {
            if (Schema::hasColumn('ordenes_corte', 'cotizacion_id')) {
                $table->dropForeign('ordenes_corte_cotizacion_id_fk');
                $table->dropIndex('ordenes_corte_cotizacion_id_idx');
            }
            if (Schema::hasColumn('ordenes_corte', 'cuerpo_id')) {
                $table->dropForeign('ordenes_corte_cuerpo_id_fk');
                $table->dropIndex('ordenes_corte_cuerpo_id_idx');
            }
            if (Schema::hasColumn('ordenes_corte', 'compania_id')) {
                $table->dropForeign('ordenes_corte_compania_id_fk');
                $table->dropIndex('ordenes_corte_compania_id_idx');
            }
            if (Schema::hasColumn('ordenes_corte', 'responsable_id')) {
                $table->dropForeign('ordenes_corte_responsable_id_fk');
                $table->dropIndex('ordenes_corte_responsable_id_idx');
            }

            $drop = array_values(array_filter(
                ['cotizacion_id', 'cuerpo_id', 'compania_id', 'responsable_id', 'fecha_compromiso'],
                fn (string $column) => Schema::hasColumn('ordenes_corte', $column)
            ));

            if (! empty($drop)) {
                $table->dropColumn($drop);
            }
        });
    }
};
