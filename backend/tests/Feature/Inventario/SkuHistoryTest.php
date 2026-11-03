<?php

namespace Tests\Feature\Inventario;

use App\Models\Cotizacion;
use App\Models\PteMovimiento;
use App\Models\PteSku;
use App\Models\PteStock;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SkuHistoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (require database_path('migrations/2026_08_30_162519_restore_external_references_on_peticiones_table.php'))->up();
        $this->actingAsUser(['role' => 'superadmin']);
    }

    public function test_internal_quote_has_id_number_client_and_legacy_fields(): void
    {
        $quote = Cotizacion::factory()->create(['numero' => 'C22967850', 'nombre' => 'Uniformes']);
        $sku = PteSku::factory()->create();
        $mov = PteMovimiento::factory()->paraSku($sku)->create(['cotizacion_id' => $quote->id, 'cliente_nombre' => 'Cliente al reservar']);
        $row = $this->getJson("/api/productos/{$sku->sku}/movimientos")->assertOk()->json('0');
        $this->assertSame($quote->id, $row['cotizacion_id']);
        $this->assertSame('C22967850', $row['cotizacion_numero']);
        $this->assertSame('Uniformes', $row['cotizacion_nombre']);
        $this->assertSame('Cliente al reservar', $row['cliente_mostrado']);
        $this->assertTrue($row['cotizacion_disponible']);
        $this->assertSame($mov->id, $row['id']);
        $this->assertArrayHasKey('sku_producto', $row);
        $this->assertArrayHasKey('usuario', $row);
        $this->assertArrayNotHasKey('cotizacion', $row);
    }

    public function test_external_reference_never_links_to_matching_internal_number(): void
    {
        Cotizacion::factory()->create(['numero' => '12625']);
        $sku = PteSku::factory()->create();
        PteStock::factory()->conCantidad(10)->create(['sku' => $sku->sku]);
        $this->postJson('/api/order-requests', ['items' => [['sku' => $sku->sku, 'quantity' => 3]], 'is_external' => true,
            'external_quote_number' => '12625', 'external_quote_name' => 'Uniformes externos', 'external_client_name' => 'Cliente externo',
        ])->assertCreated();
        $row = $this->getJson("/api/productos/{$sku->sku}/movimientos")->assertOk()->json('0');
        $this->assertNull($row['cotizacion_id']);
        $this->assertSame('12625', $row['referencia_externa']);
        $this->assertSame('externa', $row['cotizacion_origen']);
        $this->assertSame('Uniformes externos', $row['cotizacion_nombre']);
        $this->assertSame('Cliente externo', $row['cliente_mostrado']);
        $this->assertSame(-3, $row['variacion_disponible']);
    }

    public function test_approval_preserves_balances_and_distinguishes_requester_and_actor(): void
    {
        $requester = User::factory()->create(['role' => 'ejecutivo', 'name' => 'Solicitante']);
        $approver = User::factory()->create(['role' => 'superadmin', 'name' => 'Aprobador']);
        $sku = PteSku::factory()->create();
        PteStock::factory()->conCantidad(10)->create(['sku' => $sku->sku]);
        Sanctum::actingAs($requester);
        $id = $this->postJson('/api/order-requests', ['items' => [['sku' => $sku->sku, 'quantity' => 3]], 'is_external' => true,
            'external_quote_number' => 'EXT-3', 'external_quote_name' => 'Equipo', 'observation' => 'Pedido urgente',
        ])->assertCreated()->json('data.0.id');
        Sanctum::actingAs($approver);
        $this->postJson("/api/order-requests/{$id}/confirm")->assertOk();
        $before = PteStock::where('sku', $sku->sku)->first()->toArray();
        $rows = collect($this->getJson("/api/productos/{$sku->sku}/movimientos")->assertOk()->json())->keyBy('tipo_movimiento');
        $this->assertSame('Solicitante', $rows['SALIDA_VENTA']['solicitante']);
        $this->assertSame('Aprobador', $rows['SALIDA_VENTA']['usuario_nombre']);
        $this->assertSame('Aprobó', $rows['SALIDA_VENTA']['rol_actor']);
        $this->assertSame(0, $rows['SALIDA_VENTA']['variacion_disponible']);
        $this->assertSame('Aprobada', $rows['RESERVA_PRODUCTO']['estado_solicitud_actual']);
        $this->assertSame('Aprobador', $rows['RESERVA_PRODUCTO']['resuelto_por']);
        $this->assertSame('Pedido urgente', $rows['RESERVA_PRODUCTO']['observacion_solicitud']);
        $this->assertSame($before, PteStock::where('sku', $sku->sku)->first()->toArray());
        $this->assertSame(2, PteMovimiento::where('sku', $sku->sku)->count());
    }

    public function test_missing_links_and_same_time_are_stable_and_only_include_target_sku(): void
    {
        $sku = PteSku::factory()->create();
        $first = PteMovimiento::factory()->paraSku($sku)->create(['fecha_hora' => '2026-10-05 10:00:00']);
        $last = PteMovimiento::factory()->paraSku($sku)->create(['fecha_hora' => '2026-10-05 10:00:00']);
        PteMovimiento::factory()->create();
        $rows = $this->getJson("/api/productos/{$sku->sku}/movimientos")->assertOk()->json();
        $this->assertSame([$last->id, $first->id], array_column($rows, 'id'));
        $this->assertNull($rows[0]['solicitante']);
        $this->assertNull($rows[0]['cotizacion_id']);
        $this->assertNull($rows[0]['estado_solicitud_actual']);
    }

    public function test_rejection_returns_available_units_and_shows_resolver(): void
    {
        $sku = PteSku::factory()->create();
        PteStock::factory()->conCantidad(10)->create(['sku' => $sku->sku]);
        $id = $this->postJson('/api/order-requests', ['items' => [['sku' => $sku->sku, 'quantity' => 3]], 'is_external' => true,
            'external_quote_number' => 'EXT-REJ', 'external_quote_name' => 'Rechazo de prueba',
        ])->assertCreated()->json('data.0.id');
        $this->postJson("/api/order-requests/{$id}/reject")->assertOk();
        $rows = collect($this->getJson("/api/productos/{$sku->sku}/movimientos")->assertOk()->json())->keyBy('tipo_movimiento');
        $this->assertSame(3, $rows['ANULACION_RESERVA']['variacion_disponible']);
        $this->assertSame('Rechazó', $rows['ANULACION_RESERVA']['rol_actor']);
        $this->assertSame('Rechazada', $rows['RESERVA_PRODUCTO']['estado_solicitud_actual']);
        $this->assertSame('ANULACION_RESERVA', $rows['RESERVA_PRODUCTO']['resolucion_tipo']);
    }

    public function test_editing_metadata_leaves_available_reserved_and_movement_history_unchanged(): void
    {
        $sku = PteSku::factory()->create(['precio_venta' => 14990]);
        $stock = PteStock::factory()->conCantidad(7)->create(['sku' => $sku->sku, 'reserved_stock' => 3, 'stock_critico' => 5]);
        PteMovimiento::factory()->paraSku($sku)->create();
        $before = PteMovimiento::where('sku', $sku->sku)->get()->toArray();
        $this->putJson("/api/productos/{$sku->sku}", ['precio_venta' => 0, 'stock_critico' => 8])->assertOk();
        $this->assertSame(0, $sku->fresh()->precio_venta);
        $this->assertSame(7, $stock->fresh()->cantidad);
        $this->assertSame(3, $stock->fresh()->reserved_stock);
        $this->assertSame(8, $stock->fresh()->stock_critico);
        $this->assertSame($before, PteMovimiento::where('sku', $sku->sku)->get()->toArray());
    }

    public function test_pdf_keeps_two_references_in_the_same_minute_separate(): void
    {
        $sku = PteSku::factory()->create();
        foreach (['EXT-1', 'EXT-2'] as $reference) {
            PteMovimiento::factory()->paraSku($sku)->create(['referencia_externa' => $reference, 'tipo_movimiento' => 'RESERVA_PRODUCTO',
                'usuario_id' => auth()->id(), 'fecha_hora' => '2026-10-05 10:00:00', 'id_lote' => 'same-lot']);
        }
        $pdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('setPaper')->with('a4', 'landscape')->once()->andReturnSelf();
        $pdf->shouldReceive('stream')->once()->andReturn(response('%PDF-test', 200, ['Content-Type' => 'application/pdf']));
        Pdf::shouldReceive('loadView')->once()->with('pdf.historial_sku', \Mockery::on(function ($data) {
            $this->assertCount(2, $data['movimientos']);
            $html = view('pdf.historial_sku', $data)->render();
            $this->assertStringContainsString('Externa N.º EXT-1', $html);
            $this->assertStringContainsString('Externa N.º EXT-2', $html);
            return true;
        }))->andReturn($pdf);
        $this->getJson("/api/productos/{$sku->sku}/movimientos/pdf")->assertOk();
    }

    public function test_unavailable_quote_keeps_saved_id_without_inventing_a_reference(): void
    {
        $sku = PteSku::factory()->create();
        PteMovimiento::factory()->paraSku($sku)->create(['cotizacion_id' => 999999]);
        $row = $this->getJson("/api/productos/{$sku->sku}/movimientos")->assertOk()->json('0');
        $this->assertSame(999999, $row['cotizacion_id']);
        $this->assertNull($row['cotizacion_numero']);
        $this->assertFalse($row['cotizacion_disponible']);
    }

    public function test_actual_pdf_and_empty_history(): void
    {
        $sku = PteSku::factory()->create();
        $this->getJson("/api/productos/{$sku->sku}/movimientos")->assertExactJson([]);
        $this->getJson("/api/productos/{$sku->sku}/movimientos/pdf")->assertNotFound();
        PteMovimiento::factory()->paraSku($sku)->create(['observacion' => '<script>test</script>']);
        $pdf = $this->getJson("/api/productos/{$sku->sku}/movimientos/pdf")->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
    }
}
