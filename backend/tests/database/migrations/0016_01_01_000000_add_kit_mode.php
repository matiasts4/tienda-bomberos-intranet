<?php
use Illuminate\Database\Migrations\Migration;
return new class extends Migration {
    public function up(): void
    {
        (require __DIR__.'/../../../database/migrations/2026_10_12_040000_add_kit_mode_to_pte_sku_genericos_table.php')->up();
    }
};
