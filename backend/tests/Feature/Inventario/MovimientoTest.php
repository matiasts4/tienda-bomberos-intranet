<?php

namespace Tests\Feature\Inventario;

use App\Models\PteMovimiento;
use App\Models\PteSku;
use App\Models\PteStock;
use Tests\TestCase;

class MovimientoTest extends TestCase
{
    // ── storeIngreso ───────────────────────────────────────────────────────

    public function test_store_ingreso_requiere_autenticacion(): void
    {
        $this->postJson('/api/movimientos/ingresos', [])->assertUnauthorized();
    }

    public function test_store_ingreso_aumenta_stock_y_registra_movimiento(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);
        $sku = PteSku::factory()->create();
        PteStock::factory()->conCantidad(10)->create(['sku' => $sku->sku]);

        $this->postJson('/api/movimientos/ingresos', [
            'sku'      => $sku->sku,
            'cantidad' => 5,
        ])->assertCreated();

        $this->assertSame(15, (int) PteStock::where('sku', $sku->sku)->value('cantidad'));
        $this->assertDatabaseHas('pte_movimientos', [
            'sku'            => $sku->sku,
            'tipo_movimiento'=> 'INGRESO_BODEGA',
            'cantidad'       => 5,
            'saldo_anterior' => 10,
            'saldo_nuevo'    => 15,
        ]);
    }

    public function test_store_ingreso_crea_stock_si_no_existia(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);
        $sku = PteSku::factory()->create();

        $this->postJson('/api/movimientos/ingresos', [
            'sku'      => $sku->sku,
            'cantidad' => 8,
        ])->assertCreated();

        $this->assertSame(8, (int) PteStock::where('sku', $sku->sku)->value('cantidad'));
    }

    public function test_store_ingreso_valida_sku_existente(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);

        $this->postJson('/api/movimientos/ingresos', [
            'sku'      => 'NOEXISTE12345',
            'cantidad' => 5,
        ])->assertStatus(422);
    }

    public function test_store_ingreso_valida_cantidad_positiva(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);
        $sku = PteSku::factory()->create();

        $this->postJson('/api/movimientos/ingresos', [
            'sku'      => $sku->sku,
            'cantidad' => 0,
        ])->assertStatus(422);
    }

    // ── anularIngreso ──────────────────────────────────────────────────────

    public function test_anular_ingreso_reduce_stock_y_registra_anulacion(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);
        $sku = PteSku::factory()->create();
        $stock = PteStock::factory()->conCantidad(20)->create(['sku' => $sku->sku]);

        $mov = PteMovimiento::factory()->ingreso()->paraSku($sku)->create([
            'cantidad' => 10, 'saldo_anterior' => 10, 'saldo_nuevo' => 20,
        ]);

        $this->postJson("/api/movimientos/ingresos/{$mov->id}/anular")
            ->assertOk()
            ->assertJsonPath('message', 'Ingreso anulado y stock corregido');

        $this->assertSame(10, (int) PteStock::where('sku', $sku->sku)->value('cantidad'));
        $this->assertDatabaseHas('pte_movimientos', [
            'sku'            => $sku->sku,
            'tipo_movimiento'=> 'ANULACION_INGRESO',
            'cantidad'       => 10,
        ]);
    }

    public function test_anular_ingreso_falla_si_stock_insuficiente(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);
        $sku = PteSku::factory()->create();
        PteStock::factory()->conCantidad(3)->create(['sku' => $sku->sku]);

        $mov = PteMovimiento::factory()->ingreso()->paraSku($sku)->create(['cantidad' => 10]);

        $this->postJson("/api/movimientos/ingresos/{$mov->id}/anular")
            ->assertStatus(400)
            ->assertJsonPath('message', 'No se puede anular: El stock actual es menor a lo que se ingresó.');
    }

    public function test_anular_solo_permite_tipo_ingreso_bodega(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);
        $sku = PteSku::factory()->create();
        PteStock::factory()->conCantidad(10)->create(['sku' => $sku->sku]);

        $mov = PteMovimiento::factory()->salida()->paraSku($sku)->create(['cantidad' => 5]);

        $this->postJson("/api/movimientos/ingresos/{$mov->id}/anular")
            ->assertStatus(400)
            ->assertJsonPath('message', 'Solo se pueden anular Ingresos de Bodega aquí');
    }

    // ── storeSalidaCotizacion ──────────────────────────────────────────────

    public function test_salida_descuenta_stock_y_registra_movimiento(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);
        $sku = PteSku::factory()->create();
        PteStock::factory()->conCantidad(30)->create(['sku' => $sku->sku]);

        $this->postJson('/api/movimientos/salida', [
            'sku'          => $sku->sku,
            'cantidad'     => 10,
            'cotizacion_id'=> 999,
        ])->assertCreated();

        $this->assertSame(20, (int) PteStock::where('sku', $sku->sku)->value('cantidad'));
        $this->assertDatabaseHas('pte_movimientos', [
            'sku'            => $sku->sku,
            'tipo_movimiento'=> 'SALIDA_VENTA',
            'cantidad'       => 10,
            'saldo_anterior' => 30,
            'saldo_nuevo'    => 20,
        ]);
    }

    public function test_salida_falla_con_stock_insuficiente(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);
        $sku = PteSku::factory()->create();
        PteStock::factory()->conCantidad(3)->create(['sku' => $sku->sku]);

        $this->postJson('/api/movimientos/salida', [
            'sku'          => $sku->sku,
            'cantidad'     => 10,
            'cotizacion_id'=> 1,
        ])->assertStatus(422);
    }

    public function test_salida_falla_si_sku_no_tiene_stock_registrado(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);
        $sku = PteSku::factory()->create();
        // Sin stock creado

        $this->postJson('/api/movimientos/salida', [
            'sku'          => $sku->sku,
            'cantidad'     => 5,
            'cotizacion_id'=> 1,
        ])->assertStatus(404);
    }

    // ── historialPorSku ────────────────────────────────────────────────────

    public function test_historial_por_sku_lista_movimientos_del_producto(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);
        $sku  = PteSku::factory()->create();
        $otro = PteSku::factory()->create();
        PteMovimiento::factory()->paraSku($sku)->count(3)->create();
        PteMovimiento::factory()->paraSku($otro)->create();

        $response = $this->getJson("/api/productos/{$sku->sku}/movimientos")->assertOk();
        $this->assertCount(3, $response->json());
    }

    // ── historialGeneral ────────────────────────────────────────────────────

    public function test_historial_general_lista_todos_los_movimientos(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);
        $sku = PteSku::factory()->create();
        PteMovimiento::factory()->paraSku($sku)->count(4)->create();

        $response = $this->getJson('/api/movimientos/general')->assertOk();
        $this->assertGreaterThanOrEqual(4, count($response->json('data') ?? $response->json()));
    }

    // ── loteItems ──────────────────────────────────────────────────────────

    public function test_lote_items_agrupa_movimientos_por_id_lote(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);
        $sku  = PteSku::factory()->create();
        $lote = 'lote-test-' . uniqid();

        $items = PteMovimiento::factory()->paraSku($sku)->count(3)->create(['id_lote' => $lote, 'fecha_hora' => now()->startOfMinute()]);

        $response = $this->getJson('/api/movimientos/lote-items?movimiento_id=' . $items->first()->id)->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    // ── show ───────────────────────────────────────────────────────────────

    public function test_show_retorna_movimiento_por_id(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);
        $sku = PteSku::factory()->create();
        $mov = PteMovimiento::factory()->paraSku($sku)->create();

        $this->getJson("/api/movimientos/{$mov->id}")->assertOk()
            ->assertJsonPath('id', $mov->id);
    }

    public function test_show_retorna_404_si_no_existe(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);
        $this->getJson('/api/movimientos/99999')->assertNotFound();
    }

    // ── indexIngresos ──────────────────────────────────────────────────────

    public function test_index_ingresos_solo_lista_ingresos_bodega(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);
        $sku = PteSku::factory()->create();
        PteMovimiento::factory()->ingreso()->paraSku($sku)->count(2)->create();
        PteMovimiento::factory()->salida()->paraSku($sku)->create();

        $response = $this->getJson('/api/movimientos/ingresos')->assertOk();
        foreach ($response->json() as $mov) {
            $this->assertSame('INGRESO_BODEGA', $mov['tipo_movimiento']);
        }
        $this->assertCount(2, $response->json());
    }
}
