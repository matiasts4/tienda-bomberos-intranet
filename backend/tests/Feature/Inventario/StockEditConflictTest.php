<?php

namespace Tests\Feature\Inventario;

use App\Models\PteMovimiento;
use App\Models\PteSku;
use App\Models\PteStock;
use Tests\TestCase;

/**
 * PUT /api/productos/{sku} desde Inventario PTE ("Editar" y edición masiva).
 * Caso real: la pantalla se abre con un stock, alguien reserva y luego se
 * guarda el valor viejo; antes eso devolvía la reserva al disponible.
 */
class StockEditConflictTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Las columnas de peticiones vienen del esquema de pruebas (0011).
        (require database_path('migrations/2026_08_30_162519_restore_external_references_on_peticiones_table.php'))->up();
        $this->actingAsUser(['role' => 'superadmin']);
    }

    private function skuConStock(int $cantidad, array $atributos = []): PteSku
    {
        $sku = PteSku::factory()->create($atributos);
        PteStock::factory()->conCantidad($cantidad)->create(['sku' => $sku->sku, 'stock_critico' => 4]);

        return $sku;
    }

    private function reservar(PteSku $sku, int $cantidad): void
    {
        $this->postJson('/api/order-requests', [
            'items' => [['sku' => $sku->sku, 'quantity' => $cantidad]],
            'is_external' => true,
            'external_quote_number' => '12644', 'external_quote_name' => 'Uniformes de prueba',
        ])->assertCreated();
    }

    public function test_guardar_un_stock_visto_antes_de_una_reserva_no_la_pisa(): void
    {
        $sku = $this->skuConStock(19);
        // La pantalla mostraba 19; mientras tanto un ejecutivo reserva 3.
        $this->reservar($sku, 3);

        $this->putJson("/api/productos/{$sku->sku}", ['cantidad' => 19, 'cantidad_anterior' => 19])
            ->assertStatus(409)
            ->assertJsonPath('cantidad_actual', 16)
            ->assertJsonPath('reserved_stock', 3);

        $this->assertDatabaseHas('pte_stocks', ['sku' => $sku->sku, 'cantidad' => 16, 'reserved_stock' => 3]);
        $this->assertSame(0, PteMovimiento::where('tipo_movimiento', 'AJUSTE_MANUAL')->count());
    }

    public function test_guardar_con_el_stock_vigente_aplica_el_ajuste(): void
    {
        $sku = $this->skuConStock(19);
        $this->reservar($sku, 3);

        $this->putJson("/api/productos/{$sku->sku}", ['cantidad' => 20, 'cantidad_anterior' => 16])->assertOk();

        $this->assertDatabaseHas('pte_stocks', ['sku' => $sku->sku, 'cantidad' => 20, 'reserved_stock' => 3]);
        $this->assertDatabaseHas('pte_movimientos', [
            'sku' => $sku->sku,
            'tipo_movimiento' => 'AJUSTE_MANUAL',
            'cantidad' => 4,
            'saldo_anterior' => 16,
            'saldo_nuevo' => 20,
        ]);
    }

    public function test_editar_solo_el_stock_no_toca_nombre_precio_descripcion_ni_critico(): void
    {
        // SKU sin precio, como el Cinturón táctico (#112).
        $sku = $this->skuConStock(10, ['nombre' => 'Cinturon Tactico Negro M', 'precio_venta' => null, 'descripcion' => 'Nota interna']);

        $this->putJson("/api/productos/{$sku->sku}", ['cantidad' => 7, 'cantidad_anterior' => 10])->assertOk();

        $fresco = $sku->fresh();
        $this->assertSame('Cinturon Tactico Negro M', $fresco->nombre);
        $this->assertNull($fresco->precio_venta);
        $this->assertSame('Nota interna', $fresco->descripcion);
        $this->assertDatabaseHas('pte_stocks', ['sku' => $sku->sku, 'cantidad' => 7, 'stock_critico' => 4]);
    }

    public function test_editar_solo_el_nombre_no_cambia_el_stock(): void
    {
        $sku = $this->skuConStock(10);

        $this->putJson("/api/productos/{$sku->sku}", ['nombre' => 'Nombre corregido'])->assertOk();

        $this->assertSame('Nombre corregido', $sku->fresh()->nombre);
        $this->assertDatabaseHas('pte_stocks', ['sku' => $sku->sku, 'cantidad' => 10]);
        $this->assertSame(0, PteMovimiento::count());
    }

    public function test_cambiar_nombre_no_choca_aunque_el_stock_haya_cambiado(): void
    {
        $sku = $this->skuConStock(10);
        $this->reservar($sku, 2);

        // Sin "cantidad" no hay nada que comparar: no corresponde un 409.
        $this->putJson("/api/productos/{$sku->sku}", ['nombre' => 'Otro nombre', 'stock_critico' => 6])->assertOk();

        $this->assertDatabaseHas('pte_stocks', ['sku' => $sku->sku, 'cantidad' => 8, 'reserved_stock' => 2, 'stock_critico' => 6]);
    }

    public function test_cantidad_negativa_o_vacia_es_rechazada(): void
    {
        $sku = $this->skuConStock(10);

        $this->putJson("/api/productos/{$sku->sku}", ['cantidad' => -1, 'cantidad_anterior' => 10])
            ->assertUnprocessable()->assertJsonValidationErrors('cantidad');
        $this->putJson("/api/productos/{$sku->sku}", ['cantidad' => null, 'cantidad_anterior' => 10])
            ->assertUnprocessable()->assertJsonValidationErrors('cantidad');

        $this->assertDatabaseHas('pte_stocks', ['sku' => $sku->sku, 'cantidad' => 10]);
    }
}
