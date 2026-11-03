<?php

namespace Tests\Feature\Inventario;

use App\Models\Cotizacion;
use App\Models\PteSku;
use App\Models\PteStock;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ExternalReservationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Las columnas de peticiones vienen del esquema de pruebas (0011).
        (require database_path('migrations/2026_08_30_162519_restore_external_references_on_peticiones_table.php'))->up();
        $this->actingAsUser(['role' => 'superadmin']);
    }

    private function items(): array
    {
        $sku = PteSku::factory()->create();
        PteStock::factory()->conCantidad(10)->create(['sku' => $sku->sku, 'stock_critico' => 0]);
        return [['sku' => $sku->sku, 'quantity' => 2]];
    }

    public function test_external_reservation_saves_reference_and_reserves_stock(): void
    {
        $items = $this->items();
        $this->postJson('/api/order-requests', [
            'items' => $items, 'is_external' => true,
            'external_quote_number' => '30219', 'external_quote_name' => 'Uniformes de prueba', 'external_client_name' => 'Bomberos de prueba',
        ])->assertCreated()->assertJsonPath('data.0.cotizacion_id', null);
        $this->assertDatabaseHas('peticiones', ['referencia_externa' => '30219', 'cliente_externo' => 'Bomberos de prueba']);
        $this->assertDatabaseHas('pte_movimientos', ['sku' => $items[0]['sku'], 'tipo_movimiento' => 'RESERVA_PRODUCTO', 'cliente_nombre' => 'Bomberos de prueba']);
        $this->assertDatabaseHas('pte_stocks', ['sku' => $items[0]['sku'], 'cantidad' => 8, 'reserved_stock' => 2]);
        $this->getJson('/api/order-requests')->assertOk()->assertJsonPath('0.referencia_externa', '30219');
    }

    public function test_external_reference_is_required_and_client_is_optional(): void
    {
        $items = $this->items();
        $this->postJson('/api/order-requests', ['items' => $items, 'is_external' => true, 'external_quote_number' => '   ', 'external_quote_name' => 'Uniformes de prueba'])
            ->assertUnprocessable()->assertJsonValidationErrors('external_quote_number');
        $this->postJson('/api/order-requests', ['items' => $items, 'is_external' => true, 'external_quote_number' => 'COT-2024-55', 'external_quote_name' => 'Uniformes de prueba'])
            ->assertCreated()->assertJsonPath('data.0.cliente_externo', null);
    }

    public function test_external_quote_name_is_required_and_shown_in_pending(): void
    {
        $items = $this->items();
        foreach ([[], ['external_quote_name' => '   ']] as $sinNombre) {
            $this->postJson('/api/order-requests', ['items' => $items, 'is_external' => true, 'external_quote_number' => '30220'] + $sinNombre)
                ->assertUnprocessable()->assertJsonValidationErrors('external_quote_name');
        }
        // Rechazar por falta de nombre no reserva nada.
        $this->assertDatabaseHas('pte_stocks', ['sku' => $items[0]['sku'], 'cantidad' => 10, 'reserved_stock' => 0]);

        $this->postJson('/api/order-requests', [
            'items' => $items, 'is_external' => true,
            'external_quote_number' => '30220', 'external_quote_name' => '  Uniformes Brigada Forestal 2026 ',
        ])->assertCreated();

        $this->assertDatabaseHas('peticiones', ['referencia_externa' => '30220', 'nombre_cotizacion' => 'Uniformes Brigada Forestal 2026']);
        $this->getJson('/api/order-requests')->assertOk()->assertJsonPath('0.nombre_cotizacion', 'Uniformes Brigada Forestal 2026');
    }

    public function test_system_quote_does_not_need_external_name_and_lists_its_name(): void
    {
        $quote = Cotizacion::factory()->create(['nombre' => 'Cotización Camisetas Aniversario']);

        $this->getJson('/api/order-requests/quotes')->assertOk()
            ->assertJsonFragment(['id' => $quote->id, 'nombre' => 'Cotización Camisetas Aniversario']);
        $this->postJson('/api/order-requests', ['items' => $this->items(), 'quote_identifier' => $quote->id])
            ->assertCreated()->assertJsonPath('data.0.nombre_cotizacion', null);
        $this->getJson('/api/order-requests')->assertOk()->assertJsonPath('0.cotizacion.nombre', 'Cotización Camisetas Aniversario');
    }

    public function test_system_reservation_still_uses_existing_quote(): void
    {
        $quote = Cotizacion::factory()->create();
        $this->postJson('/api/order-requests', ['items' => $this->items(), 'quote_identifier' => $quote->id])
            ->assertCreated()->assertJsonPath('data.0.cotizacion_id', $quote->id);
    }

    public function test_executive_and_warehouse_can_request_but_cannot_modify_stock_or_approve(): void
    {
        foreach (['ejecutivo', 'bodega'] as $role) {
            $user = $this->actingAsUser(['role' => $role]);
            $items = $this->items();
            $id = $this->postJson('/api/order-requests', ['items' => $items, 'is_external' => true, 'external_quote_number' => $role, 'external_quote_name' => 'Uniformes de prueba'])
                ->assertCreated()->json('data.0.id');
            $this->getJson('/api/order-requests')->assertOk();
            foreach (['confirm', 'reject'] as $action) {
                $this->postJson("/api/order-requests/{$id}/{$action}")->assertForbidden();
                $this->postJson("/api/order-requests/bulk-{$action}", ['ids' => [$id]])->assertForbidden();
            }
            $this->putJson('/api/productos/' . $items[0]['sku'], ['cantidad' => 99])->assertForbidden();
            foreach (['/api/movimientos/importar', '/api/stock/ingreso', '/api/movimientos/ingresos', '/api/movimientos/salida', '/api/productos', '/api/productos/bulk'] as $url) {
                $this->postJson($url, [])->assertForbidden();
            }
            $this->putJson('/api/users/' . $user->id, ['role' => 'superadmin'])->assertForbidden();
            $this->assertDatabaseHas('pte_stocks', ['sku' => $items[0]['sku'], 'cantidad' => 8, 'reserved_stock' => 2]);
        }
    }

    public function test_only_actual_superadmin_has_admin_permissions(): void
    {
        foreach (['admin', 'vendedor', 'comercial', 'fabrica', 'personalizacion'] as $role) {
            $this->actingAsUser(['role' => $role]);
            $this->postJson('/api/movimientos/importar', [])->assertForbidden();
            $this->postJson('/api/order-requests', [])->assertForbidden();
        }
    }

    public function test_approval_does_not_discount_twice_and_rejection_restores_stock_once(): void
    {
        foreach (['confirm', 'reject'] as $action) {
            $items = $this->items();
            $id = $this->postJson('/api/order-requests', ['items' => $items, 'is_external' => true, 'external_quote_number' => $action, 'external_quote_name' => 'Uniformes de prueba', 'external_client_name' => 'Cliente externo'])->assertCreated()->json('data.0.id');
            $this->postJson("/api/order-requests/{$id}/{$action}")->assertOk();
            $this->assertDatabaseHas('pte_movimientos', ['sku' => $items[0]['sku'], 'tipo_movimiento' => $action === 'confirm' ? 'SALIDA_VENTA' : 'ANULACION_RESERVA', 'cliente_nombre' => 'Cliente externo']);
            $this->postJson("/api/order-requests/{$id}/{$action}")->assertStatus(400);
            $this->assertDatabaseHas('pte_stocks', ['sku' => $items[0]['sku'], 'cantidad' => $action === 'confirm' ? 8 : 10, 'reserved_stock' => 0]);
        }
    }

    public function test_insufficient_stock_rolls_back_every_item(): void
    {
        $items = $this->items();
        $items[0]['quantity'] = 11;
        $this->postJson('/api/order-requests', ['items' => $items, 'is_external' => true, 'external_quote_number' => 'sin-stock', 'external_quote_name' => 'Uniformes de prueba'])
            ->assertUnprocessable();
        $this->assertDatabaseHas('pte_stocks', ['sku' => $items[0]['sku'], 'cantidad' => 10, 'reserved_stock' => 0]);
        $this->assertDatabaseMissing('peticiones', ['referencia_externa' => 'sin-stock']);
    }

    public function test_receipt_contains_only_requested_lines_not_older_orders(): void
    {
        $items = $this->items();
        $payload = ['items' => $items, 'is_external' => true, 'external_quote_number' => 'misma-cotizacion', 'external_quote_name' => 'Uniformes de prueba'];
        $this->postJson('/api/order-requests', $payload)->assertCreated();
        $id = $this->postJson('/api/order-requests', $payload)->assertCreated()->json('data.0.id');
        $pdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('stream')->once()->andReturn(response('%PDF-test', 200, ['Content-Type' => 'application/pdf']));
        \Barryvdh\DomPDF\Facade\Pdf::shouldReceive('loadView')->once()->withArgs(function ($view, $data) use ($id) {
            return $view === 'pdf.quote_request' && $data['requestReceipt'] === true && $data['items']->pluck('id')->all() === [$id];
        })->andReturn($pdf);
        $this->get('/api/order-requests/receipt?' . http_build_query(['ids' => [$id]]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_superadmin_can_change_stock_manually(): void
    {
        $items = $this->items();
        $this->postJson('/api/stock/ingreso', ['sku' => $items[0]['sku'], 'cantidad' => 3, 'motivo' => 'Ajuste de prueba'])->assertSuccessful();
        $this->assertDatabaseHas('pte_stocks', ['sku' => $items[0]['sku'], 'cantidad' => 13]);
    }

    public function test_bulk_approval_and_rejection_process_each_line_once(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-08-30 22:38:00'));
        foreach (['confirm', 'reject'] as $action) {
            $items = array_merge($this->items(), $this->items(), $this->items());
            $response = $this->postJson('/api/order-requests', ['items' => $items, 'is_external' => true, 'external_quote_number' => 'bulk-' . $action, 'external_quote_name' => 'Uniformes de prueba'])->assertCreated();
            $ids = array_column($response->json('data'), 'id');
            $this->postJson('/api/order-requests/bulk-' . $action, ['ids' => array_merge($ids, $ids)])->assertOk();
            $this->postJson('/api/order-requests/bulk-' . $action, ['ids' => $ids])->assertOk();
            $type = $action === 'confirm' ? 'SALIDA_VENTA' : 'ANULACION_RESERVA';
            $summary = $this->getJson('/api/movimientos/general?mes=2026-08&tipo='.$type)
                ->assertOk()->assertJsonPath('pagination.total', 1)
                ->assertJsonPath('data.0.total_registros', 3)->assertJsonPath('data.0.total_unidades', 6);
            $anchor = $summary->json('data.0.movimiento_id');
            $this->getJson('/api/movimientos/lote-items?movimiento_id='.$anchor)
                ->assertOk()->assertJsonCount(3, 'data')->assertJsonPath('pagination.total', 3);
            foreach ($items as $item) {
                $this->assertDatabaseHas('pte_stocks', ['sku' => $item['sku'], 'cantidad' => $action === 'confirm' ? 8 : 10, 'reserved_stock' => 0]);
            }
        }
    }

    public function test_separate_reservations_at_same_second_remain_separate_audit_operations(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-08-30 22:38:00'));
        $items = array_merge($this->items(), $this->items(), $this->items());
        $payload = ['items' => $items, 'is_external' => true, 'external_quote_number' => 'same-quote', 'external_quote_name' => 'Uniformes de prueba'];
        $first = $this->postJson('/api/order-requests', $payload)->assertCreated()->json('data');
        $second = $this->postJson('/api/order-requests', $payload)->assertCreated()->json('data');
        $this->getJson('/api/movimientos/general?mes=2026-08&tipo=RESERVA_PRODUCTO')
            ->assertOk()->assertJsonPath('pagination.total', 2)
            ->assertJsonPath('data.0.total_registros', 3)->assertJsonPath('data.1.total_registros', 3);
        foreach ([$first, $second] as $requests) {
            $this->postJson('/api/order-requests/bulk-confirm', ['ids' => array_column($requests, 'id')])->assertOk();
        }
        $result = $this->getJson('/api/movimientos/general?mes=2026-08&tipo=SALIDA_VENTA')
            ->assertOk()->assertJsonPath('pagination.total', 2)
            ->assertJsonPath('data.0.total_registros', 3)->assertJsonPath('data.1.total_registros', 3);
        $anchor = $result->json('data.0.movimiento_id');
        $pdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('stream')->once()->andReturn(response('%PDF-test', 200, ['Content-Type' => 'application/pdf']));
        \Barryvdh\DomPDF\Facade\Pdf::shouldReceive('loadView')->once()->with('pdf.bitacora', \Mockery::on(fn ($data) => $data['reporte'][0]['total_registros'] === 3))->andReturn($pdf);
        $this->getJson('/api/movimientos/pdf/individual?movimiento_id='.$anchor)->assertOk();
    }

    public function test_bulk_action_does_not_merge_different_external_quotes_for_same_client(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-08-30 22:38:00'));
        $ids = [];
        foreach (['EXT-A', 'EXT-B'] as $reference) {
            $response = $this->postJson('/api/order-requests', [
                'items' => array_merge($this->items(), $this->items()), 'is_external' => true,
                'external_quote_number' => $reference, 'external_quote_name' => 'Uniformes de prueba', 'external_client_name' => 'Mismo cliente',
            ])->assertCreated();
            $ids = array_merge($ids, array_column($response->json('data'), 'id'));
        }
        $this->postJson('/api/order-requests/bulk-confirm', ['ids' => $ids])->assertOk();
        $this->getJson('/api/movimientos/general?mes=2026-08&tipo=SALIDA_VENTA')
            ->assertOk()->assertJsonPath('pagination.total', 2)
            ->assertJsonPath('data.0.total_registros', 2)->assertJsonPath('data.1.total_registros', 2);
    }

    public function test_duplicate_sku_cannot_reserve_more_than_available(): void
    {
        $items = $this->items();
        $items[0]['quantity'] = 6;
        $this->postJson('/api/order-requests', ['items' => array_merge($items, $items), 'is_external' => true, 'external_quote_number' => 'duplicado', 'external_quote_name' => 'Uniformes de prueba'])->assertUnprocessable();
        $this->assertDatabaseHas('pte_stocks', ['sku' => $items[0]['sku'], 'cantidad' => 10, 'reserved_stock' => 0]);
        $this->assertDatabaseMissing('peticiones', ['referencia_externa' => 'duplicado']);
    }

    public function test_external_receipt_and_approved_dispatch_render_as_pdf(): void
    {
        $id = $this->postJson('/api/order-requests', ['items' => $this->items(), 'is_external' => true, 'external_quote_number' => 'pdf-test', 'external_quote_name' => 'Uniformes de prueba'])->assertCreated()->json('data.0.id');
        $this->get('/api/order-requests/receipt?' . http_build_query(['ids' => [$id]]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->postJson("/api/order-requests/{$id}/confirm")->assertOk();
        $this->get("/api/order-requests/{$id}/dispatch-pdf")->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_executive_can_download_receipt_and_request_remains_pending(): void
    {
        $user = $this->actingAsUser(['role' => 'ejecutivo']);
        foreach ([true, false] as $external) {
            $quoteData = $external
                ? ['is_external' => true, 'external_quote_number' => 'EJ-PDF', 'external_quote_name' => 'Uniformes de prueba']
                : ['quote_identifier' => Cotizacion::factory()->create()->id];
            $id = $this->postJson('/api/order-requests', ['items' => $this->items(), ...$quoteData])->assertCreated()->json('data.0.id');
            $response = $this->get('/api/order-requests/receipt?' . http_build_query(['ids' => [$id]]))
                ->assertOk()->assertHeader('Content-Type', 'application/pdf');
            $this->assertStringStartsWith('%PDF-', $response->getContent());
            $order = \App\Models\Peticion::with('estado')->findOrFail($id);
            $this->assertSame('Pendiente', $order->estado->nombre);
            $this->assertSame($user->id, $order->user_id);
            $this->getJson('/api/order-requests')->assertOk()->assertJsonFragment(['id' => $id]);
            $this->postJson("/api/order-requests/{$id}/confirm")->assertForbidden();
        }
    }

    public function test_external_quote_reference_is_visible_in_bitacora(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-08-30 14:00:00'));
        $payload = [
            'items' => $this->items(),
            'is_external' => true,
            'external_quote_number' => 'COT 5373', 'external_quote_name' => 'Uniformes de prueba',
            'external_client_name' => 'TIENDA BOMBEROS'
        ];
        $created = $this->postJson('/api/order-requests', $payload)->assertCreated()->json('data');
        
        // Assert that the reservation movement includes the external quote number in bitácora
        $general = $this->getJson('/api/movimientos/general?mes=2026-08&tipo=RESERVA_PRODUCTO')->assertOk();
        $lot = collect($general->json('data'))->firstWhere('cliente_nombre', 'TIENDA BOMBEROS');
        $this->assertNotNull($lot);
        $this->assertSame('COT 5373', $lot['cotizacion_numero']);
        $this->assertSame('COT 5373', $lot['referencia_externa']);

        // Check lot items
        $itemsRes = $this->getJson('/api/movimientos/lote-items?movimiento_id='.$lot['movimiento_id'])->assertOk();
        $this->assertSame('COT 5373', $itemsRes->json('data.0.cotizacion_numero'));
        $this->assertSame('COT 5373', $itemsRes->json('data.0.referencia_externa'));

        // Confirm dispatch
        $id = $created[0]['id'];
        $this->postJson("/api/order-requests/{$id}/confirm")->assertOk();

        // Assert that the dispatch movement (SALIDA_VENTA) also includes the external quote number
        $dispatchGeneral = $this->getJson('/api/movimientos/general?mes=2026-08&tipo=SALIDA_VENTA')->assertOk();
        $dispatchLot = collect($dispatchGeneral->json('data'))->firstWhere('cliente_nombre', 'TIENDA BOMBEROS');
        $this->assertNotNull($dispatchLot);
        $this->assertSame('COT 5373', $dispatchLot['cotizacion_numero']);
        $this->assertSame('COT 5373', $dispatchLot['referencia_externa']);
    }
}

