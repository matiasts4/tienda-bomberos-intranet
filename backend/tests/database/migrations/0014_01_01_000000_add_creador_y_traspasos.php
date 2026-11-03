<?php

use Illuminate\Database\Migrations\Migration;

// Creador de la tarjeta + historial de traspasos de responsable.
return new class extends Migration
{
    public function up(): void
    {
        (require __DIR__.'/../../../database/migrations/2026_10_11_000001_add_creador_y_traspasos_a_oportunidades_y_cotizaciones.php')->up();
    }
};
