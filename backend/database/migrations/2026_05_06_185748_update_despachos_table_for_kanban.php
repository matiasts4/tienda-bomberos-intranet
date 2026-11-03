<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('despachos', function (Blueprint $table) {
            if (Schema::hasColumn('despachos', 'estado_despacho')) {
                $table->dropColumn('estado_despacho');
            }
            if (!Schema::hasColumn('despachos', 'estado_id')) {
                $table->foreignId('estado_id')->nullable()->constrained('estados');
            }
        });

        // Insert new states
        $estados = [
            ['scope' => 'despacho', 'nombre' => 'Por despachar', 'color' => '#111827', 'orden' => 10, 'next_estado_id' => null],
            ['scope' => 'despacho', 'nombre' => 'Preparando despacho', 'color' => '#f59e0b', 'orden' => 20, 'next_estado_id' => null],
            ['scope' => 'despacho', 'nombre' => 'Saliendo de bodega', 'color' => '#3b82f6', 'orden' => 30, 'next_estado_id' => null],
            ['scope' => 'despacho', 'nombre' => 'Entregado a agencia', 'color' => '#10b981', 'orden' => 40, 'next_estado_id' => null],
        ];

        foreach ($estados as $e) {
            \App\Models\Estado::updateOrCreate(
                ['scope' => $e['scope'], 'nombre' => $e['nombre']],
                ['color' => $e['color'], 'orden' => $e['orden'], 'next_estado_id' => $e['next_estado_id']]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('despachos', function (Blueprint $table) {
            if (Schema::hasColumn('despachos', 'estado_id')) {
                $table->dropForeign(['estado_id']);
                $table->dropColumn('estado_id');
            }
            if (!Schema::hasColumn('despachos', 'estado_despacho')) {
                $table->string('estado_despacho', 50)->nullable();
            }
        });

        \App\Models\Estado::where('scope', 'despacho')->delete();
    }
};
