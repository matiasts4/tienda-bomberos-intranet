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
        if (!Schema::hasColumn('pte_movimientos', 'referencia_externa')) {
            Schema::table('pte_movimientos', function (Blueprint $table) {
                $table->string('referencia_externa', 100)->nullable()->after('cotizacion_id');
            });
        }

        // Backfill referencia_externa and cotizacion_id for existing movements from peticiones
        try {
            $peticiones = DB::table('peticiones')
                ->where(function ($q) {
                    $q->whereNotNull('referencia_externa')->where('referencia_externa', '!=', '')
                      ->orWhereNotNull('cotizacion_id');
                })
                ->get();

            foreach ($peticiones as $pet) {
                $ref = $pet->referencia_externa ? trim((string)$pet->referencia_externa) : null;
                $cotId = $pet->cotizacion_id;

                // If no direct cotizacion_id, try to resolve if reference contains an existing quote ID
                if (!$cotId && $ref) {
                    $digits = preg_replace('/[^\d]/', '', $ref);
                    if ($digits && DB::table('cotizaciones')->where('id', (int)$digits)->exists()) {
                        $cotId = (int)$digits;
                    }
                }

                $updateData = [];
                if ($ref) {
                    $updateData['referencia_externa'] = $ref;
                }
                if ($cotId) {
                    $updateData['cotizacion_id'] = $cotId;
                }

                if (!empty($updateData)) {
                    $query = DB::table('pte_movimientos')
                        ->where('sku', $pet->sku)
                        ->where('cantidad', $pet->cantidad)
                        ->whereIn('tipo_movimiento', ['RESERVA_PRODUCTO', 'SALIDA_VENTA']);

                    if (!empty($pet->cliente_externo)) {
                        $query->where('cliente_nombre', $pet->cliente_externo);
                    }

                    if (!empty($pet->fecha_creacion)) {
                        $query->where('fecha_hora', '>=', $pet->fecha_creacion . ' 00:00:00');
                    }

                    $query->update($updateData);
                }
            }
        } catch (\Throwable $e) {
            // Ignore if tables do not exist in some environments during early tests
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('pte_movimientos', 'referencia_externa')) {
            Schema::table('pte_movimientos', function (Blueprint $table) {
                $table->dropColumn('referencia_externa');
            });
        }
    }
};
