<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Modificar el ENUM para asegurarse de que ambas opciones sigan existiendo 
        // o al menos que pending-design sea el default real.
        // Asumiendo que el enum actual tiene 'pending-definition', lo mantendremos en la lista 
        // para no romper rollback, pero cambiaremos el DEFAULT a 'pending-design'.
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE personalizaciones MODIFY COLUMN estado ENUM('pending-definition', 'pending-design', 'designing', 'in-correction', 'approval', 'in-production', 'ready-for-pickup') DEFAULT 'pending-design'");

        // 2. Mover todos los pendientes a Por Hacer
        \Illuminate\Support\Facades\DB::table('personalizaciones')
            ->where('estado', 'pending-definition')
            ->update(['estado' => 'pending-design']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // En caso de rollback, volver al estado anterior (opcionalmente)
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE personalizaciones MODIFY COLUMN estado ENUM('pending-definition', 'pending-design', 'designing', 'in-correction', 'approval', 'in-production', 'ready-for-pickup') DEFAULT 'pending-definition'");
    }
};
