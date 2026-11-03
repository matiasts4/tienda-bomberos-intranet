<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fecha en que la cotización pasó a "Ventas" / "Aprobada por Cliente".
 * Desde ahí corre el plazo de entrega (fecha_venta + plazo_produccion).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('cotizaciones', 'fecha_venta')) {
            Schema::table('cotizaciones', function (Blueprint $table) {
                $table->date('fecha_venta')->nullable()->after('fecha_vencimiento');
            });
        }

        $estadosVenta = DB::table('estados')
            ->where('scope', 'cotizacion')
            ->whereIn('nombre', ['Ventas', 'Aprobada por Cliente'])
            ->pluck('id');

        if ($estadosVenta->isEmpty()) {
            return;
        }

        // Backfill: última transición a un estado de venta según el historial;
        // sin historial, se usa updated_at.
        $ultimaTransicion = DB::table('historial_etapas')
            ->where('model_type', 'App\\Models\\Cotizacion')
            ->whereIn('estado_nuevo_id', $estadosVenta)
            ->groupBy('model_id')
            ->selectRaw('model_id, MAX(created_at) as fecha')
            ->pluck('fecha', 'model_id');

        DB::table('cotizaciones')
            ->whereIn('estado_id', $estadosVenta)
            ->whereNull('fecha_venta')
            ->select('id', 'updated_at')
            ->orderBy('id')
            ->chunkById(500, function ($cotizaciones) use ($ultimaTransicion) {
                foreach ($cotizaciones as $cot) {
                    $fecha = $ultimaTransicion[$cot->id] ?? $cot->updated_at;
                    if ($fecha) {
                        DB::table('cotizaciones')->where('id', $cot->id)
                            ->update(['fecha_venta' => substr((string) $fecha, 0, 10)]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->dropColumn('fecha_venta');
        });
    }
};
