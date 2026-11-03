<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (['oportunidades', 'cotizaciones'] as $tabla) {
            $filas = DB::table($tabla)->whereNotNull('cliente_id')->get();

            foreach ($filas as $fila) {
                $cliente = DB::table('clientes')->where('id', $fila->cliente_id)->first();

                // Cliente suelto real: se deja como está, sigue siendo el ancla correcta.
                if (!$cliente || $cliente->tipo !== 'bomberos') {
                    continue;
                }

                $update = ['cliente_id' => null];
                if ($cliente->compania_id) {
                    $update['compania_id'] = $cliente->compania_id;
                } else {
                    $update['cuerpo_id'] = $cliente->id;
                }

                DB::table($tabla)->where('id', $fila->id)->update($update);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op intencional: reconstruir el estado espejo previo no tiene sentido de negocio.
    }
};
