<?php

use Illuminate\Database\Migrations\Migration;

// Etapa "No Vigente": motivo, observación y responsable del cierre.
return new class extends Migration
{
    public function up(): void
    {
        (require __DIR__.'/../../../database/migrations/2026_10_25_100000_add_no_vigente_a_cotizaciones.php')->up();
    }
};
