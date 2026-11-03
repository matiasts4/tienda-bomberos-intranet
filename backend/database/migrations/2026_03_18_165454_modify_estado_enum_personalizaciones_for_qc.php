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
        // 1. Ampliar ENUM temporalmente para todos los estados (viejos + nuevos)
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE personalizaciones MODIFY COLUMN estado ENUM('pending-definition', 'pending-design', 'designing', 'in-correction', 'approval', 'in-production', 'ready-for-pickup', 'quality-control', 'completed') DEFAULT 'pending-design'");
        
        // 2. Mover registros en estados viejos al estado 'completed' (o 'quality-control' según su flujo, para no perderlos, 'completed' es más seguro).
        \Illuminate\Support\Facades\DB::table('personalizaciones')->where('estado', 'in-production')->update(['estado' => 'completed']);
        \Illuminate\Support\Facades\DB::table('personalizaciones')->where('estado', 'ready-for-pickup')->update(['estado' => 'completed']);
        
        // 3. Restringir el ENUM a los estados finales solicitados
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE personalizaciones MODIFY COLUMN estado ENUM('pending-definition', 'pending-design', 'designing', 'in-correction', 'approval', 'quality-control', 'completed') DEFAULT 'pending-design'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Ampliar ENUM temporalmente
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE personalizaciones MODIFY COLUMN estado ENUM('pending-definition', 'pending-design', 'designing', 'in-correction', 'approval', 'in-production', 'ready-for-pickup', 'quality-control', 'completed') DEFAULT 'pending-design'");
        
        // 2. Revertir registros de los nuevos estados (asignar a uno existente)
        \Illuminate\Support\Facades\DB::table('personalizaciones')->where('estado', 'quality-control')->update(['estado' => 'approval']);
        \Illuminate\Support\Facades\DB::table('personalizaciones')->where('estado', 'completed')->update(['estado' => 'ready-for-pickup']);
        
        // 3. Restringir ENUM a los estados originales
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE personalizaciones MODIFY COLUMN estado ENUM('pending-definition', 'pending-design', 'designing', 'in-correction', 'approval', 'in-production', 'ready-for-pickup') DEFAULT 'pending-design'");
    }
};
