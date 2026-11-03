<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración SOLO para tests del módulo Comercial.
 *
 * Crea el esquema "final" (post-alters) de las tablas que el flujo de
 * cotizaciones necesita. Evita el ruido del historial de migraciones reales
 * (renombres ejecutivo_id → user_id, FKs a pte_skus, etc.) que rompen SQLite
 * en memoria.
 *
 * Cargada por TestCase::defineDatabaseMigrations().
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('last_name')->nullable();
            $table->string('email')->unique();
            $table->string('telefono')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('role')->default('ejecutivo');
            $table->json('modulos')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('estados', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 80);
            $table->string('color', 30)->nullable();
            $table->string('scope', 50)->nullable();
            $table->unsignedInteger('orden')->nullable();
            $table->unsignedBigInteger('next_estado_id')->nullable();
            $table->timestamps();
        });

        Schema::create('clientes', function (Blueprint $table) {
            $table->string('id', 15)->primary();
            $table->string('nombre_empresa', 150);
            $table->string('rut_empresa', 30)->nullable();
            $table->string('logo_url')->nullable();
            $table->date('fecha_fundacion')->nullable();
            $table->string('direccion')->nullable();
            $table->string('giro')->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('correo', 150)->nullable();
            $table->unsignedInteger('numero_companias')->nullable();
            $table->date('fecha_ingreso')->nullable();
            $table->unsignedBigInteger('region_id')->nullable();
            $table->unsignedBigInteger('provincia_id')->nullable();
            $table->string('parent_id', 15)->nullable();
            $table->unsignedBigInteger('cuerpo_id')->nullable();
            $table->unsignedBigInteger('compania_id')->nullable();
            $table->timestamps();
        });

        Schema::create('regiones', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->unsignedBigInteger('zona_id')->nullable();
            $table->timestamps();
        });

        Schema::create('cuerpos', function (Blueprint $table) {
            $table->string('id', 15)->primary();
            $table->unsignedBigInteger('region_id')->nullable();
            $table->string('nombre');
            $table->unsignedInteger('numero_socio')->nullable();
            $table->string('rut', 20)->nullable();
            $table->date('fecha_fundacion')->nullable();
            $table->string('direccion')->nullable();
            $table->string('giro')->nullable();
            $table->string('telefono')->nullable();
            $table->unsignedInteger('numero_companias')->nullable();
            $table->string('logo_url')->nullable();
            $table->timestamps();
        });

        Schema::create('companias', function (Blueprint $table) {
            $table->string('id', 15)->primary();
            $table->string('cuerpo_id', 15);
            $table->string('nombre');
            $table->unsignedInteger('numero');
            $table->timestamps();
        });

        Schema::create('contactos', function (Blueprint $table) {
            $table->id();
            $table->string('cliente_id', 15)->nullable();
            $table->unsignedBigInteger('cargo_id')->nullable();
            $table->string('cargo')->nullable();
            $table->string('rut', 30)->nullable();
            $table->string('nombre', 150);
            $table->string('correo', 150)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->timestamps();
        });

        Schema::create('contacto_actividades', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contacto_id')->nullable();
            $table->string('contacto_nombre', 150)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_nombre', 150)->nullable();
            $table->string('accion', 40);
            $table->string('descripcion', 500)->nullable();
            $table->json('cambios')->nullable();
            $table->string('origen', 40)->nullable();
            $table->timestamps();
        });

        Schema::create('cuerpo_contacto', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contacto_id');
            $table->string('cuerpo_id', 15);
            $table->timestamps();
            $table->unique(['contacto_id', 'cuerpo_id']);
        });

        Schema::create('compania_contacto', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contacto_id');
            $table->string('compania_id', 15);
            $table->timestamps();
            $table->unique(['contacto_id', 'compania_id']);
        });

        Schema::create('oportunidades', function (Blueprint $table) {
            $table->id();
            $table->date('fecha_ingreso')->nullable();
            $table->string('referido_por')->nullable();
            $table->string('canal')->nullable();
            $table->text('mensaje_prospeccion')->nullable();
            $table->string('cliente_id', 15)->nullable();
            $table->unsignedBigInteger('contacto_id')->nullable();
            $table->string('cuerpo_id', 15)->nullable();
            $table->string('compania_id', 15)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('estado_id')->nullable();
            $table->timestamps();
        });

        Schema::create('cotizaciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('oportunidad_id')->nullable();
            $table->string('cliente_id', 15)->nullable();
            $table->unsignedBigInteger('contacto_id')->nullable();
            $table->string('cuerpo_id', 15)->nullable();
            $table->string('compania_id', 15)->nullable();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('estado_id');
            $table->string('numero', 32)->nullable();
            $table->string('nombre')->nullable();
            $table->date('fecha_creacion')->nullable();
            $table->date('fecha_vencimiento')->nullable();
            $table->text('observaciones')->nullable();
            $table->unsignedBigInteger('total_neto')->default(0);
            $table->unsignedBigInteger('iva')->default(0);
            $table->unsignedBigInteger('total')->default(0);
            $table->date('fecha_venta')->nullable();
            $table->string('plazo_produccion', 120)->nullable();
            $table->string('condiciones_pago', 120)->nullable();
            $table->string('despacho', 120)->nullable();
            $table->boolean('personalizacion_completada')->default(false);
            $table->string('origen', 50)->nullable();
            $table->timestamp('fecha_rechazo')->nullable();
            $table->text('motivo_rechazo')->nullable();
            $table->string('estado_personalizacion', 50)->nullable();
            $table->timestamps();
        });

        Schema::create('detalle_cotizaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cotizacion_id')->constrained('cotizaciones')->cascadeOnDelete();
            $table->string('sku', 50)->nullable();
            $table->unsignedInteger('n_item')->nullable();
            $table->unsignedInteger('cantidad')->default(0);
            $table->unsignedBigInteger('subtotal')->nullable();
            $table->boolean('is_personalizable')->default(false);
            $table->string('producto', 120)->nullable();
            $table->string('talla', 30)->nullable();
            $table->string('color', 60)->nullable();
            $table->string('genero', 30)->nullable();
            $table->string('tipo_personalizacion', 60)->nullable();
            $table->unsignedBigInteger('precio_unitario')->nullable();
            $table->unsignedBigInteger('total_neto')->nullable();
            $table->json('configuracion')->nullable();
            $table->timestamps();
        });

        Schema::create('cotizacion_archivos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cotizacion_id')->constrained('cotizaciones')->cascadeOnDelete();
            $table->string('tipo', 50);
            $table->string('nombre_archivo');
            $table->string('url');
            $table->timestamps();
        });

        Schema::create('cotizacion_gestiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cotizacion_id')->constrained('cotizaciones')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->string('glosa')->nullable();
            $table->dateTime('fecha_gestion')->nullable();
            $table->date('fecha_vencimiento_nueva')->nullable();
            $table->timestamps();
        });

        Schema::create('oportunidad_gestiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('oportunidad_id')->constrained('oportunidades')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->string('tipo_contacto', 50)->nullable();
            $table->text('glosa')->nullable();
            $table->dateTime('fecha_gestion')->nullable();
            $table->date('fecha_vencimiento_nueva')->nullable();
            $table->timestamps();
        });

        // -- Personalización ---------------------------------------------
        Schema::create('item_personalizacion_grupos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cotizacion_item_id')->constrained('detalle_cotizaciones')->cascadeOnDelete();
            $table->string('nombre_grupo')->nullable();
            $table->string('archivo_adjunto')->nullable();
            $table->timestamps();
        });

        Schema::create('item_personalizacion_tallas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grupo_id')->constrained('item_personalizacion_grupos')->cascadeOnDelete();
            $table->string('genero')->nullable();
            $table->string('color')->nullable();
            $table->string('talla');
            $table->integer('cantidad')->nullable();
            $table->timestamps();
        });

        Schema::create('item_personalizacion_matrices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grupo_id')->constrained('item_personalizacion_grupos')->cascadeOnDelete();
            $table->string('posicion')->nullable();
            $table->string('tecnica')->nullable();
            $table->string('tipo_contenido')->default('logo');
            $table->text('valor_fijo')->nullable();
            $table->string('color')->nullable();
            $table->timestamps();
        });

        Schema::create('item_personalizacion_variables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grupo_id')->constrained('item_personalizacion_grupos')->cascadeOnDelete();
            $table->string('talla');
            $table->text('valor_variable')->nullable();
            $table->timestamps();
        });

        Schema::create('personalizaciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('movimiento_inventario_id')->nullable();
            $table->foreignId('usuario_creador_id')->constrained('users');
            $table->unsignedBigInteger('cotizacion_id')->nullable();
            $table->unsignedInteger('numero_item')->nullable();
            $table->string('sku')->nullable();
            $table->string('nombre_producto');
            $table->integer('cantidad')->default(1);
            $table->string('estado', 50)->default('pendiente_diseno');
            $table->string('tipo', 50)->default('bordado');
            $table->string('siguiente_proceso', 50)->nullable();
            $table->string('proceso_previo', 50)->nullable();
            $table->json('configuracion')->nullable();
            $table->text('notas')->nullable();
            $table->string('prioridad', 20)->default('media');
            $table->dateTime('fecha_inicio_proceso')->nullable();
            $table->dateTime('fecha_inicio_qc')->nullable();
            $table->unsignedBigInteger('personalizacion_padre_id')->nullable();
            $table->boolean('bloqueado_qc')->default(false);
            $table->integer('ciclos_qc')->default(0);
            $table->unsignedBigInteger('operario_asignado_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('personalizacion_variantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personalizacion_id')->constrained('personalizaciones')->onDelete('cascade');
            $table->string('talla', 50)->nullable();
            $table->string('color', 100)->nullable();
            $table->string('genero', 50)->default('Unisex');
            $table->integer('cantidad')->default(0);
            $table->timestamps();
        });

        Schema::create('personalizacion_archivos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personalizacion_id')->constrained('personalizaciones')->onDelete('cascade');
            $table->string('tipo_archivo', 50);
            $table->text('url_archivo');
            $table->boolean('confirmado_fisico')->default(false);
            $table->timestamps();
        });

        // Catálogos PTE mínimos (para resolveFinalSku en sendToPersonalizacion)
        Schema::create('pte_generos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->boolean('is_activo')->default(true);
            $table->timestamps();
        });
        Schema::create('pte_colores', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->boolean('is_activo')->default(true);
            $table->timestamps();
        });
        Schema::create('pte_tallas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->unsignedInteger('orden')->nullable();
            $table->boolean('is_activo')->default(true);
            $table->timestamps();
        });

        Schema::create('comercial_skus_genericos', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 15)->unique();
            $table->string('nombre');
            $table->string('imagen_url')->nullable();
            $table->timestamps();
        });

        Schema::create('historial_etapas', function (Blueprint $table) {
            $table->id();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('estado_anterior_id')->nullable();
            $table->unsignedBigInteger('estado_nuevo_id')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->index(['model_type', 'model_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacto_actividades');
        Schema::dropIfExists('historial_etapas');
        Schema::dropIfExists('comercial_skus_genericos');
        Schema::dropIfExists('pte_tallas');
        Schema::dropIfExists('pte_colores');
        Schema::dropIfExists('pte_generos');
        Schema::dropIfExists('personalizaciones');
        Schema::dropIfExists('item_personalizacion_variables');
        Schema::dropIfExists('item_personalizacion_matrices');
        Schema::dropIfExists('item_personalizacion_tallas');
        Schema::dropIfExists('item_personalizacion_grupos');
        Schema::dropIfExists('oportunidad_gestiones');
        Schema::dropIfExists('cotizacion_gestiones');
        Schema::dropIfExists('cotizacion_archivos');
        Schema::dropIfExists('detalle_cotizaciones');
        Schema::dropIfExists('cotizaciones');
        Schema::dropIfExists('oportunidades');
        Schema::dropIfExists('compania_contacto');
        Schema::dropIfExists('cuerpo_contacto');
        Schema::dropIfExists('contactos');
        Schema::dropIfExists('companias');
        Schema::dropIfExists('cuerpos');
        Schema::dropIfExists('regiones');
        Schema::dropIfExists('clientes');
        Schema::dropIfExists('estados');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('users');
    }
};
