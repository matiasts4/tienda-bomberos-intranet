<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('personalizacion_archivos');
        Schema::dropIfExists('personalizacion_variantes');

        // 1. Crear tabla personalizacion_archivos
        Schema::create('personalizacion_archivos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('personalizacion_id');
            $table->string('tipo_archivo', 50); // diseno_dtf, diseno_estampado, matriz_bordado, mockup_visual
            $table->boolean('confirmado_fisico')->default(false);
            $table->string('url_archivo')->nullable();
            $table->timestamps();

            $table->foreign('personalizacion_id')->references('id')->on('personalizaciones')->onDelete('cascade');
        });

        // 2. Crear tabla personalizacion_variantes
        Schema::create('personalizacion_variantes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('personalizacion_id');
            $table->string('talla', 50)->nullable();
            $table->string('color', 50)->nullable();
            $table->string('genero', 50)->nullable();
            $table->integer('cantidad')->default(1);
            $table->timestamps();

            $table->foreign('personalizacion_id')->references('id')->on('personalizaciones')->onDelete('cascade');
        });

        // 3. Añadir nuevas columnas en español a personalizaciones
        try {
            Schema::table('personalizaciones', function (Blueprint $table) {
                $table->dropColumn([
                    'usuario_creador_id', 'movimiento_inventario_id', 'operario_asignado_id', 
                    'personalizacion_padre_id', 'nombre_producto', 'numero_item', 
                    'fecha_inicio_proceso', 'fecha_inicio_qc', 'bloqueado_qc', 'ciclos_qc'
                ]);
            });
        } catch (\Exception $e) {}

        Schema::table('personalizaciones', function (Blueprint $table) {
            $table->unsignedBigInteger('usuario_creador_id')->nullable();
            $table->unsignedBigInteger('movimiento_inventario_id')->nullable();
            $table->unsignedBigInteger('operario_asignado_id')->nullable();
            $table->unsignedBigInteger('personalizacion_padre_id')->nullable();
            
            $table->string('nombre_producto')->nullable();
            $table->integer('numero_item')->default(1);
            
            $table->dateTime('fecha_inicio_proceso')->nullable();
            $table->dateTime('fecha_inicio_qc')->nullable();
            
            $table->boolean('bloqueado_qc')->default(false);
            $table->integer('ciclos_qc')->default(0);
        });

        // 3.5 Convertir las columnas ENUM problemáticas a VARCHAR(50) antes del update
        DB::statement('ALTER TABLE personalizaciones MODIFY estado VARCHAR(50)');
        DB::statement('ALTER TABLE personalizaciones MODIFY tipo VARCHAR(50)');
        DB::statement('ALTER TABLE personalizaciones MODIFY siguiente_proceso VARCHAR(50)');
        DB::statement('ALTER TABLE personalizaciones MODIFY proceso_previo VARCHAR(50)');
        DB::statement('ALTER TABLE personalizaciones MODIFY prioridad VARCHAR(50)');

        // 4. Migrar Datos (Copiar valores, Traducir ENUMs, Extraer JSON)
        $items = DB::table('personalizaciones')->get();

        $estadoMap = [
            'pending-design' => 'pendiente_diseno',
            'designing' => 'disenando',
            'approval' => 'aprobacion',
            'in-correction' => 'en_correccion',
            'workshop-in-progress' => 'taller_por_hacer',
            'workshop-processing' => 'taller_en_proceso',
            'quality-control' => 'control_calidad',
            'workshop-done' => 'taller_completado',
            'completed' => 'completado',
            'manual' => 'manual',
        ];

        $tipoMap = [
            'embroidery' => 'bordado',
            'print' => 'estampado',
            'patch' => 'parche',
            'other' => 'otro',
            'mixed' => 'mixto',
            'mixed_pending' => 'mixto_pendiente',
        ];

        $prioridadMap = [
            'low' => 'baja',
            'medium' => 'media',
            'high' => 'alta',
        ];

        foreach ($items as $item) {
            // Traducir estados
            $newEstado = $estadoMap[$item->estado] ?? $item->estado;
            $newTipo = $tipoMap[$item->tipo] ?? $item->tipo;
            $newSigProc = $item->siguiente_proceso ? ($tipoMap[$item->siguiente_proceso] ?? $item->siguiente_proceso) : null;
            $newProcPrev = $item->proceso_previo ? ($tipoMap[$item->proceso_previo] ?? $item->proceso_previo) : null;
            $newPrioridad = $prioridadMap[$item->prioridad] ?? 'media';

            // Actualizar tabla principal
            DB::table('personalizaciones')->where('id', $item->id)->update([
                'usuario_creador_id' => $item->user_id,
                'movimiento_inventario_id' => $item->pte_movimiento_id ?? null,
                'operario_asignado_id' => $item->assigned_user_id ?? null,
                'personalizacion_padre_id' => $item->parent_split_id ?? null,
                'nombre_producto' => $item->producto_nombre,
                'numero_item' => $item->n_item ?? 1,
                'fecha_inicio_proceso' => $item->processing_started_at ?? null,
                'fecha_inicio_qc' => $item->qc_started_at ?? null,
                'bloqueado_qc' => $item->qc_blocked ?? 0,
                'ciclos_qc' => $item->qc_cycles ?? 0,
                'estado' => $newEstado,
                'tipo' => $newTipo,
                'siguiente_proceso' => $newSigProc,
                'proceso_previo' => $newProcPrev,
                'prioridad' => $newPrioridad,
            ]);

            // Extraer JSON
            $config = json_decode($item->configuracion, true);
            if (is_array($config)) {
                // Variantes (Talla / Color / Cantidad)
                $talla = $config['talla'] ?? null;
                $color = $config['color'] ?? null;
                
                // Si existía 'grupos', extraer de ahí. Si no, insertar variante genérica.
                if (!empty($config['grupos']) && is_array($config['grupos'])) {
                    foreach ($config['grupos'] as $grupo) {
                        if (!empty($grupo['tallas']) && is_array($grupo['tallas'])) {
                            foreach ($grupo['tallas'] as $t) {
                                DB::table('personalizacion_variantes')->insert([
                                    'personalizacion_id' => $item->id,
                                    'talla' => $t['talla'] ?? $talla,
                                    'color' => $t['color'] ?? $color,
                                    'genero' => $t['genero'] ?? 'Unisex',
                                    'cantidad' => $t['cantidad'] ?? $item->cantidad,
                                ]);
                            }
                        }
                    }
                } else {
                    DB::table('personalizacion_variantes')->insert([
                        'personalizacion_id' => $item->id,
                        'talla' => $talla,
                        'color' => $color,
                        'genero' => 'Unisex',
                        'cantidad' => $item->cantidad,
                    ]);
                }

                // Archivos
                if (!empty($config['layers']) && is_array($config['layers'])) {
                    foreach ($config['layers'] as $layer) {
                        DB::table('personalizacion_archivos')->insert([
                            'personalizacion_id' => $item->id,
                            'tipo_archivo' => 'mockup_visual',
                            'confirmado_fisico' => false,
                            'url_archivo' => $layer['content'] ?? null,
                        ]);
                    }
                }

                if (isset($config['workshop_matrix_sent']) || isset($config['workshop_matrix_file_url'])) {
                    DB::table('personalizacion_archivos')->insert([
                        'personalizacion_id' => $item->id,
                        'tipo_archivo' => 'matriz_bordado',
                        'confirmado_fisico' => !empty($config['workshop_matrix_sent']),
                        'url_archivo' => $config['workshop_matrix_file_url'] ?? null,
                    ]);
                }
                
                if (isset($config['workshop_dtf_sent']) || isset($config['workshop_dtf_file_url'])) {
                    DB::table('personalizacion_archivos')->insert([
                        'personalizacion_id' => $item->id,
                        'tipo_archivo' => 'diseno_dtf',
                        'confirmado_fisico' => !empty($config['workshop_dtf_sent']),
                        'url_archivo' => $config['workshop_dtf_file_url'] ?? null,
                    ]);
                }
                
                if (isset($config['workshop_stamp_sent']) || isset($config['workshop_stamp_file_url'])) {
                    DB::table('personalizacion_archivos')->insert([
                        'personalizacion_id' => $item->id,
                        'tipo_archivo' => 'diseno_estampado',
                        'confirmado_fisico' => !empty($config['workshop_stamp_sent']),
                        'url_archivo' => $config['workshop_stamp_file_url'] ?? null,
                    ]);
                }
            }
        }

        // 5. Eliminar columnas antiguas en inglés
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        try { DB::statement('ALTER TABLE personalizaciones DROP FOREIGN KEY personalizaciones_user_id_foreign'); } catch(\Exception $e) {}
        try { DB::statement('ALTER TABLE personalizaciones DROP FOREIGN KEY personalizaciones_assigned_user_id_foreign'); } catch(\Exception $e) {}
        try { DB::statement('ALTER TABLE personalizaciones DROP FOREIGN KEY personalizaciones_parent_split_id_foreign'); } catch(\Exception $e) {}
        try { DB::statement('ALTER TABLE personalizaciones DROP FOREIGN KEY personalizaciones_pte_movimiento_id_foreign'); } catch(\Exception $e) {}

        Schema::table('personalizaciones', function (Blueprint $table) {
            $table->dropColumn([
                'user_id', 
                'pte_movimiento_id', 
                'assigned_user_id', 
                'assigned_user_name', 
                'parent_split_id', 
                'producto_nombre',
                'n_item',
                'processing_started_at',
                'qc_started_at',
                'qc_blocked',
                'qc_cycles',
                'configuracion'
            ]);
        });
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // 6. Configurar las nuevas FKs
        Schema::table('personalizaciones', function (Blueprint $table) {
            $table->foreign('usuario_creador_id')->references('id')->on('users');
            $table->foreign('operario_asignado_id')->references('id')->on('users');
            $table->foreign('personalizacion_padre_id')->references('id')->on('personalizaciones');
        });

        // 7. Borrar tablas huérfanas inútiles
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Schema::dropIfExists('item_personalizacion_variables');
        Schema::dropIfExists('item_personalizacion_matrizs');
        Schema::dropIfExists('item_personalizacion_tallas');
        Schema::dropIfExists('item_personalizacion_grupos');
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    public function down(): void
    {
        Schema::dropIfExists('personalizacion_variantes');
        Schema::dropIfExists('personalizacion_archivos');
    }
};
