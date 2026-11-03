<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Modificar la columna 'estado' para incluir los nuevos valores
        DB::statement("ALTER TABLE personalizaciones MODIFY COLUMN estado ENUM(
            'pending-design',
            'waiting-approval',
            'in-correction',
            'approved',
            'in-production',
            'ready-for-pickup',
            'finished',
            'pending-art',
            'art-sent',
            'art-approved',
            'in-personalization',
            'personalization-finished'
        ) NOT NULL DEFAULT 'pending-design'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir a los valores antiguos (aunque esto podría fallar si hay datos nuevos)
        // Por seguridad, dejaremos el down vacío o podríamos intentar revertir si estamos seguros
        // DB::statement("ALTER TABLE personalizaciones MODIFY COLUMN estado ENUM(...)");
    }
};
