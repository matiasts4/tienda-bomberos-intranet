<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extiende el esquema de pruebas SQLite con columnas de órdenes de corte,
 * trazabilidad de etapas, bitácora de fábrica y paquetes de taller.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordenes_corte', function (Blueprint $table) {
            if (! Schema::hasColumn('ordenes_corte', 'cotizacion_id')) {
                $table->unsignedBigInteger('cotizacion_id')->nullable();
                $table->index('cotizacion_id');
            }
            if (! Schema::hasColumn('ordenes_corte', 'cuerpo_id')) {
                $table->string('cuerpo_id', 15)->nullable();
                $table->index('cuerpo_id');
            }
            if (! Schema::hasColumn('ordenes_corte', 'compania_id')) {
                $table->string('compania_id', 15)->nullable();
                $table->index('compania_id');
            }
            if (! Schema::hasColumn('ordenes_corte', 'responsable_id')) {
                $table->unsignedBigInteger('responsable_id')->nullable();
                $table->index('responsable_id');
            }
            if (! Schema::hasColumn('ordenes_corte', 'fecha_compromiso')) {
                $table->date('fecha_compromiso')->nullable();
            }
            if (! Schema::hasColumn('ordenes_corte', 'total_unidades')) {
                $table->integer('total_unidades')->default(0);
            }
            if (! Schema::hasColumn('ordenes_corte', 'unidades_en_taller')) {
                $table->integer('unidades_en_taller')->default(0);
            }
            if (! Schema::hasColumn('ordenes_corte', 'unidades_en_reparacion')) {
                $table->integer('unidades_en_reparacion')->default(0);
            }
            if (! Schema::hasColumn('ordenes_corte', 'unidades_listas')) {
                $table->integer('unidades_listas')->default(0);
            }
        });

        Schema::table('items_ordenes_corte', function (Blueprint $table) {
            if (! Schema::hasColumn('items_ordenes_corte', 'detalle_cotizacion_id')) {
                $table->unsignedBigInteger('detalle_cotizacion_id')->nullable();
                $table->index('detalle_cotizacion_id');
            }
            if (! Schema::hasColumn('items_ordenes_corte', 'sku')) {
                $table->string('sku', 64)->nullable();
            }
            if (! Schema::hasColumn('items_ordenes_corte', 'personalizacion')) {
                $table->json('personalizacion')->nullable();
            }
        });

        Schema::table('historial_etapas', function (Blueprint $table) {
            if (! Schema::hasColumn('historial_etapas', 'etapa_anterior')) {
                $table->string('etapa_anterior', 50)->nullable();
            }
            if (! Schema::hasColumn('historial_etapas', 'etapa_nueva')) {
                $table->string('etapa_nueva', 50)->nullable();
            }
        });

        if (! Schema::hasTable('logs_fabrica')) {
            Schema::create('logs_fabrica', function (Blueprint $table) {
                $table->id();
                $table->string('action');
                $table->text('description');
                $table->json('details')->nullable();
                $table->string('entity_type')->nullable();
                $table->unsignedBigInteger('entity_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamps();
                $table->index(['entity_type', 'entity_id']);
            });
        }

        if (! Schema::hasTable('paquetes')) {
            Schema::create('paquetes', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('destination');
                $table->string('transport_type');
                $table->string('priority')->default('medium');
                $table->string('status')->default('preparing');
                $table->string('workshop')->nullable();
                $table->text('workshop_notes')->nullable();
                $table->text('notes')->nullable();
                $table->integer('estimated_delivery_days')->default(3);
                $table->date('shipped_date')->nullable();
                $table->date('delivered_date')->nullable();
                $table->string('tracking_number')->nullable();
                $table->integer('total_unidades')->default(0);
                $table->integer('unidades_en_taller')->default(0);
                $table->integer('unidades_en_reparacion')->default(0);
                $table->integer('unidades_listas')->default(0);
                $table->unsignedBigInteger('parent_package_id')->nullable();
                $table->boolean('is_repair_package')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('items_paquetes')) {
            Schema::create('items_paquetes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('package_id')->constrained('paquetes')->onDelete('cascade');
                $table->foreignId('cutting_order_item_id')->constrained('items_ordenes_corte')->onDelete('cascade');
                $table->integer('quantity');
                $table->timestamps();
                $table->unique(['package_id', 'cutting_order_item_id']);
            });
        }

        if (! Schema::hasTable('paquetes_ordenes_corte')) {
            Schema::create('paquetes_ordenes_corte', function (Blueprint $table) {
                $table->id();
                $table->foreignId('package_id')->constrained('paquetes')->onDelete('cascade');
                $table->foreignId('cutting_order_id')->constrained('ordenes_corte')->onDelete('cascade');
                $table->timestamps();
                $table->unique(['package_id', 'cutting_order_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('paquetes_ordenes_corte');
        Schema::dropIfExists('items_paquetes');
        Schema::dropIfExists('paquetes');
        Schema::dropIfExists('logs_fabrica');
    }
};
