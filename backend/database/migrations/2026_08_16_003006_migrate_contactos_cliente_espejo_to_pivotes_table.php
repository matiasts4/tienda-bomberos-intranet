<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $contactos = DB::table('contactos')->whereNotNull('cliente_id')->get();

        foreach ($contactos as $contacto) {
            $cliente = DB::table('clientes')->where('id', $contacto->cliente_id)->first();

            // Cliente suelto real (empresa/particular): no es un espejo, no se toca.
            if (!$cliente || $cliente->tipo !== 'bomberos') {
                continue;
            }

            if ($cliente->compania_id) {
                DB::table('compania_contacto')->updateOrInsert(
                    ['contacto_id' => $contacto->id, 'compania_id' => $cliente->compania_id],
                    ['created_at' => now(), 'updated_at' => now()]
                );
            } else {
                // Espejo de Cuerpo: el propio id del cliente-espejo ES el id del cuerpo.
                DB::table('cuerpo_contacto')->updateOrInsert(
                    ['contacto_id' => $contacto->id, 'cuerpo_id' => $cliente->id],
                    ['created_at' => now(), 'updated_at' => now()]
                );
            }

            DB::table('contactos')->where('id', $contacto->id)->update(['cliente_id' => null]);
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
