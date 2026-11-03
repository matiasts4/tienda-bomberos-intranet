<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            // Si ya existe ejecutivo_id, lo usamos como base o lo renombramos
            // Pero para evitar romper relaciones existentes, añadiremos user_id
            // y lo usaremos como el nuevo estándar.
            if (!Schema::hasColumn('eventos', 'user_id')) {
                $table->foreignId('user_id')->nullable()->constrained('users')->after('id');
            }

            // Campos para recurrencia (Estilo Google Calendar / RFC 5545)
            $table->text('rrule')->nullable()->after('descripcion');
            $table->boolean('is_all_day')->default(false)->after('fin');
            
            // Relación Polimórfica (Para ligar a cualquier entidad)
            $table->nullableMorphs('eventable'); // eventable_id, eventable_type
            
            // Estética
            $table->string('color')->nullable()->after('tipo');
            
            // Metadatos adicionales
            $table->json('metadata')->nullable()->after('color');
        });

        // Migración de datos inicial: copiar ejecutivo_id a user_id si ya hay datos
        \DB::table('eventos')->whereNotNull('ejecutivo_id')->whereNull('user_id')->update([
            'user_id' => \DB::raw('ejecutivo_id')
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->dropMorphs('eventable');
            $table->dropColumn(['user_id', 'rrule', 'is_all_day', 'color', 'metadata']);
        });
    }
};
