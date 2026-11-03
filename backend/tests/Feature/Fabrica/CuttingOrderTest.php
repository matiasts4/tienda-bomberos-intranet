<?php

namespace Tests\Feature\Fabrica;

use App\Models\OrdenCorte;
use Tests\TestCase;

class CuttingOrderTest extends TestCase
{
    // ── helper ─────────────────────────────────────────────────────────────

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'client'           => 'Cuerpo Bomberos Test',
            'selected_product' => 'Parka Bombero',
            'notes'            => 'Sin observaciones',
            'estimated_days'   => 10,
            'items'            => [
                [
                    'product_type' => 'Parka',
                    'size'         => 'L',
                    'color'        => 'Azul Marino',
                    'quantity'     => 5,
                    'fabric_type'  => 'Techni-Shell',
                ],
            ],
            'supplies' => [
                [
                    'name'     => 'Cierre YKK',
                    'type'     => 'Insumo',
                    'quantity' => 10,
                    'unit'     => 'Unid',
                ],
            ],
        ], $overrides);
    }

    // ── index ──────────────────────────────────────────────────────────────

    public function test_index_requiere_autenticacion(): void
    {
        $this->getJson('/api/cutting-orders')->assertUnauthorized();
    }

    public function test_index_retorna_lista_con_estructura(): void
    {
        $this->actingAsAdmin();
        OrdenCorte::factory()->count(3)->create();

        $response = $this->getJson('/api/cutting-orders')->assertOk();
        $this->assertTrue($response->json('success'));
        $this->assertCount(3, $response->json('data'));
    }

    // ── store ──────────────────────────────────────────────────────────────

    public function test_store_requiere_autenticacion(): void
    {
        $this->postJson('/api/cutting-orders', $this->payload())->assertUnauthorized();
    }

    public function test_store_valida_campos_requeridos(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/cutting-orders', [])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_store_crea_orden_con_items_y_suministros(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/cutting-orders', $this->payload())->assertCreated();

        $this->assertTrue($response->json('success'));
        $data = $response->json('data');
        $this->assertSame('Parka Bombero', $data['selected_product']);
        $this->assertSame('order-received', $data['status']);
        $this->assertCount(1, $data['items']);
        $this->assertCount(1, $data['supplies']);

        $this->assertDatabaseHas('ordenes_corte', ['selected_product' => 'Parka Bombero']);
        $this->assertDatabaseHas('items_ordenes_corte', ['product_type' => 'Parka', 'size' => 'L']);
        $this->assertDatabaseHas('suministros_ordenes_corte', ['name' => 'Cierre YKK']);
    }

    public function test_store_genera_codigo_secuencial(): void
    {
        $this->actingAsAdmin();

        $r1 = $this->postJson('/api/cutting-orders', $this->payload())->assertCreated();
        $r2 = $this->postJson('/api/cutting-orders', $this->payload())->assertCreated();

        $code1 = (int) $r1->json('data.code');
        $code2 = (int) $r2->json('data.code');
        $this->assertSame($code1 + 1, $code2);
    }

    public function test_store_sin_suministros_crea_orden_igualmente(): void
    {
        $this->actingAsAdmin();

        $payload = $this->payload();
        unset($payload['supplies']);

        $response = $this->postJson('/api/cutting-orders', $payload)->assertCreated();
        $this->assertEmpty($response->json('data.supplies'));
    }

    // ── show ───────────────────────────────────────────────────────────────

    public function test_show_retorna_orden_con_items(): void
    {
        $this->actingAsAdmin();
        $orden = OrdenCorte::factory()->create();
        $orden->items()->create([
            'product_type' => 'Casco', 'size' => 'M', 'color' => 'Rojo',
            'quantity' => 2, 'fabric_type' => 'ABS',
        ]);

        $response = $this->getJson("/api/cutting-orders/{$orden->id}")->assertOk();
        $this->assertTrue($response->json('success'));
        $this->assertSame($orden->id, $response->json('data.id'));
        $this->assertCount(1, $response->json('data.items'));
    }

    public function test_show_retorna_404_si_no_existe(): void
    {
        $this->actingAsAdmin();
        $this->getJson('/api/cutting-orders/99999')->assertNotFound();
    }

    // ── update ─────────────────────────────────────────────────────────────

    public function test_update_cambia_estado_y_progreso(): void
    {
        $this->actingAsAdmin();
        $orden = OrdenCorte::factory()->create();

        $this->putJson("/api/cutting-orders/{$orden->id}", [
            'status'   => 'in-cutting',
            'progress' => 45,
        ])->assertOk()
          ->assertJsonPath('success', true)
          ->assertJsonPath('data.status', 'in-cutting')
          ->assertJsonPath('data.progress', 45);
    }

    public function test_update_mapea_alias_de_estado(): void
    {
        $this->actingAsAdmin();
        $orden = OrdenCorte::factory()->create();

        // 'cutting' → 'in-cutting' en el mapeo del controller
        $this->putJson("/api/cutting-orders/{$orden->id}", ['status' => 'cutting'])
            ->assertOk()
            ->assertJsonPath('data.status', 'in-cutting');
    }

    public function test_update_retorna_404_si_no_existe(): void
    {
        $this->actingAsAdmin();
        $this->putJson('/api/cutting-orders/99999', ['status' => 'in-cutting'])
            ->assertNotFound();
    }

    // ── destroy ────────────────────────────────────────────────────────────

    public function test_destroy_elimina_orden(): void
    {
        $this->actingAsAdmin();
        $orden = OrdenCorte::factory()->create();

        $this->deleteJson("/api/cutting-orders/{$orden->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('ordenes_corte', ['id' => $orden->id]);
    }

    public function test_destroy_retorna_404_si_no_existe(): void
    {
        $this->actingAsAdmin();
        $this->deleteJson('/api/cutting-orders/99999')->assertNotFound();
    }

    // ── generateNextCode ───────────────────────────────────────────────────

    public function test_generate_next_code_retorna_1000_si_no_hay_ordenes(): void
    {
        $this->actingAsAdmin();

        $response = $this->getJson('/api/cutting-orders/next-code/generate')->assertOk();
        $this->assertTrue($response->json('success'));
        $this->assertSame('1000', $response->json('data.next_code'));
    }

    public function test_generate_next_code_incrementa_correctamente(): void
    {
        $this->actingAsAdmin();
        OrdenCorte::factory()->create(['code' => '1005']);

        $response = $this->getJson('/api/cutting-orders/next-code/generate')->assertOk();
        $this->assertSame('1006', $response->json('data.next_code'));
    }
}
