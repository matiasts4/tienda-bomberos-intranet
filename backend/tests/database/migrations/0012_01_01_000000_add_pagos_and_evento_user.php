<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

// La agenda (EventoController@index) consulta pagos e invitados de eventos;
// alerts:vencimientos lee alerta_configs.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pagos')) {
            (require __DIR__.'/../../../database/migrations/2025_12_27_204330_create_pagos_table.php')->up();
        }

        if (! Schema::hasTable('evento_user')) {
            (require __DIR__.'/../../../database/migrations/2026_04_29_005524_create_evento_user_table.php')->up();
        }

        if (! Schema::hasTable('alerta_configs')) {
            (require __DIR__.'/../../../database/migrations/2026_01_26_080110_create_alerta_configs_table.php')->up();
        }
    }
};
