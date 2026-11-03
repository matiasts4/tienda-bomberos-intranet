<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        (require __DIR__.'/../../../database/migrations/2026_10_12_030000_add_permite_cotizar_to_pte_sku_genericos_table.php')->up();
    }
};
