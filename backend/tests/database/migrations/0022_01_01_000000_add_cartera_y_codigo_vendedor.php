<?php

use Illuminate\Database\Migrations\Migration;

// Cartera de ejecutivos (cuerpos.ejecutivo_id) y código V1..V4 (users.codigo_vendedor).
return new class extends Migration
{
    public function up(): void
    {
        (require __DIR__.'/../../../database/migrations/2026_10_28_100000_add_cartera_y_codigo_vendedor.php')->up();
    }
};
