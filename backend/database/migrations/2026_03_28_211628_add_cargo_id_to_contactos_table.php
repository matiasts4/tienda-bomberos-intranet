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
        // 1. Add cargo_id column
        Schema::table('contactos', function (Blueprint $table) {
            $table->foreignId('cargo_id')->nullable()->after('cliente_id')->constrained('cargos')->nullOnDelete();
        });

        // 2. Migrate existing data
        $contactos = \Illuminate\Support\Facades\DB::table('contactos')->whereNotNull('cargo')->get();
        foreach ($contactos as $contacto) {
            $cargoName = trim($contacto->cargo);
            if (empty($cargoName)) continue;

            // Find or create the cargo
            $cargoId = \Illuminate\Support\Facades\DB::table('cargos')->where('nombre', $cargoName)->value('id');
            if (!$cargoId) {
                $cargoId = \Illuminate\Support\Facades\DB::table('cargos')->insertGetId([
                    'nombre' => $cargoName,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Update the contacto
            \Illuminate\Support\Facades\DB::table('contactos')->where('id', $contacto->id)->update(['cargo_id' => $cargoId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contactos', function (Blueprint $table) {
            $table->dropForeign(['cargo_id']);
            $table->dropColumn('cargo_id');
        });
    }
};
