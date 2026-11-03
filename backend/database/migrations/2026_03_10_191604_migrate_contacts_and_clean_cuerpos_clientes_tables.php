<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. MIGRATION OF DATA
        $cuerpos = DB::table('cuerpos')->get();
        $now = Carbon::now();

        foreach ($cuerpos as $cuerpo) {
            $inserts = [];

            if (!empty(trim($cuerpo->superintendente))) {
                $inserts[] = [
                    'cliente_id' => $cuerpo->id,
                    'nombre' => trim($cuerpo->superintendente),
                    'cargo' => 'Superintendente',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (!empty(trim($cuerpo->comandante))) {
                $inserts[] = [
                    'cliente_id' => $cuerpo->id,
                    'nombre' => trim($cuerpo->comandante),
                    'cargo' => 'Comandante',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (!empty($inserts)) {
                DB::table('contactos')->insert($inserts);
            }
        }

        // Migrate from Clientes table just in case they have it but not the cuerpo
        $clientes = DB::table('clientes')->whereNull('cuerpo_id')->whereNotNull('nombre_contacto')->get();
        foreach ($clientes as $cliente) {
            if (!empty(trim($cliente->nombre_contacto))) {
                DB::table('contactos')->insert([
                    'cliente_id' => $cliente->id,
                    'nombre' => trim($cliente->nombre_contacto),
                    'rut' => $cliente->rut_contacto ?? null,
                    'cargo' => $cliente->cargo_contacto ?? 'Contacto',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }


        // 2. DROPPING COLUMNS
        Schema::table('cuerpos', function (Blueprint $table) {
            $table->dropColumn(['superintendente', 'comandante']);
        });

        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn(['superintendente', 'comandante', 'nombre_contacto', 'rut_contacto', 'cargo_contacto']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cuerpos', function (Blueprint $table) {
            $table->string('superintendente')->nullable();
            $table->string('comandante')->nullable();
        });

        Schema::table('clientes', function (Blueprint $table) {
            $table->string('superintendente')->nullable();
            $table->string('comandante')->nullable();
            $table->string('nombre_contacto')->nullable();
            $table->string('rut_contacto')->nullable();
            $table->string('cargo_contacto')->nullable();
        });

        // Note: Reversing this migration won't recover the deleted strings accurately 
        // because the 'contactos' table has its own lifecycle.
    }
};
