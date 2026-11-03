<?php

namespace Tests\Feature\Inventario;

use App\Models\PteSku;
use App\Models\PteStock;
use Tests\TestCase;

class StockConsultaTest extends TestCase
{
    public function test_consultar_stock_retorna_registro_existente(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);
        $sku = PteSku::factory()->create();
        PteStock::factory()->conCantidad(42)->create(['sku' => $sku->sku]);

        $this->getJson("/api/stock/{$sku->sku}")
            ->assertOk()
            ->assertJsonPath('cantidad', 42)
            ->assertJsonPath('sku', $sku->sku);
    }

    public function test_consultar_stock_retorna_cero_si_no_tiene_stock(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);
        $sku = PteSku::factory()->create();

        $this->getJson("/api/stock/{$sku->sku}")
            ->assertOk()
            ->assertJsonPath('cantidad', 0);
    }
}
