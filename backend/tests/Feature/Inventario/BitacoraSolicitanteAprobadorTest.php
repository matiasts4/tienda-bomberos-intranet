<?php

namespace Tests\Feature\Inventario;

use App\Models\Cotizacion;
use App\Models\Peticion;
use App\Models\PteMovimiento;
use App\Models\PteSku;
use App\Models\PteStock;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Un ejecutivo solicita; un superadmin aprueba o rechaza. La bitácora y los PDF
 * deben distinguir quién solicitó de quién resolvió.
 */
class BitacoraSolicitanteAprobadorTest extends TestCase
{
    private User $rodrigo;

    private User $carlos;

    protected function setUp(): void
    {
        parent::setUp();
        (require database_path('migrations/2026_08_30_162519_restore_external_references_on_peticiones_table.php'))->up();
        $this->rodrigo = User::factory()->create(['role' => 'ejecutivo', 'name' => 'Rodrigo Fuentealba']);
        $this->carlos = User::factory()->create(['role' => 'superadmin', 'name' => 'Carlos Sanchez']);
        $this->travelTo(Carbon::parse('2026-09-28 10:35:00'));
    }

    private function items(int $cantidad = 2): array
    {
        $sku = PteSku::factory()->create();
        PteStock::factory()->conCantidad(10)->create(['sku' => $sku->sku, 'stock_critico' => 0]);

        return [['sku' => $sku->sku, 'quantity' => $cantidad]];
    }

    private function solicitar(User $user, array $payload): array
    {
        Sanctum::actingAs($user);

        return $this->postJson('/api/order-requests', $payload)->assertCreated()->json('data');
    }

    private function bitacora(array $query = []): array
    {
        return $this->getJson('/api/movimientos/general?'.http_build_query(['mes' => '2026-09', ...$query]))
            ->assertOk()->json('data');
    }

    public function test_request_and_approval_show_who_requested_and_who_approved(): void
    {
        $data = $this->solicitar($this->rodrigo, ['items' => $this->items(), 'is_external' => true, 'external_quote_number' => '12625', 'external_quote_name' => 'Uniformes de prueba']);
        $this->assertDatabaseHas('pte_movimientos', ['tipo_movimiento' => 'RESERVA_PRODUCTO', 'peticion_id' => $data[0]['id'], 'usuario_id' => $this->rodrigo->id]);

        // Recién solicitada: pendiente
        $reserva = collect($this->bitacora())->firstWhere('tipo_movimiento', 'RESERVA_PRODUCTO');
        $this->assertSame(['solicito', 'Rodrigo Fuentealba', 'Rodrigo Fuentealba', 'pendiente', null], [
            $reserva['rol_actor'], $reserva['usuario'], $reserva['solicitante'], $reserva['estado_solicitud'], $reserva['resuelto_por'],
        ]);

        $this->travel(2)->hours();
        Sanctum::actingAs($this->carlos);
        $this->postJson("/api/order-requests/{$data[0]['id']}/confirm")->assertOk();
        $this->assertDatabaseHas('pte_movimientos', ['tipo_movimiento' => 'SALIDA_VENTA', 'peticion_id' => $data[0]['id'], 'usuario_id' => $this->carlos->id]);

        $filas = collect($this->bitacora())->keyBy('tipo_movimiento');
        $this->assertSame(['aprobada', 'Carlos Sanchez', '28/09/2026 12:35'], [
            $filas['RESERVA_PRODUCTO']['estado_solicitud'], $filas['RESERVA_PRODUCTO']['resuelto_por'], $filas['RESERVA_PRODUCTO']['resuelto_en'],
        ]);
        // El despacho: aprobó Carlos, solicitó Rodrigo (antes no había solicitante en externas)
        $this->assertSame(['aprobo', 'Carlos Sanchez', 'Rodrigo Fuentealba'], [
            $filas['SALIDA_VENTA']['rol_actor'], $filas['SALIDA_VENTA']['usuario'], $filas['SALIDA_VENTA']['solicitante'],
        ]);

        // Filtrar por Rodrigo incluye el despacho que aprobó Carlos
        $tipos = collect($this->bitacora(['usuario_id' => $this->rodrigo->id]))->pluck('tipo_movimiento')->sort()->values()->all();
        $this->assertSame(['RESERVA_PRODUCTO', 'SALIDA_VENTA'], $tipos);
    }

    public function test_rejection_shows_who_rejected_and_who_requested(): void
    {
        $data = $this->solicitar($this->rodrigo, ['items' => $this->items(), 'is_external' => true, 'external_quote_number' => '5370', 'external_quote_name' => 'Uniformes de prueba']);
        Sanctum::actingAs($this->carlos);
        $this->postJson("/api/order-requests/{$data[0]['id']}/reject")->assertOk();

        $filas = collect($this->bitacora())->keyBy('tipo_movimiento');
        $this->assertSame(['rechazada', 'Carlos Sanchez'], [$filas['RESERVA_PRODUCTO']['estado_solicitud'], $filas['RESERVA_PRODUCTO']['resuelto_por']]);
        $this->assertSame(['rechazo', 'Carlos Sanchez', 'Rodrigo Fuentealba'], [
            $filas['ANULACION_RESERVA']['rol_actor'], $filas['ANULACION_RESERVA']['usuario'], $filas['ANULACION_RESERVA']['solicitante'],
        ]);
    }

    public function test_requester_is_who_asked_not_the_quote_owner(): void
    {
        $jose = User::factory()->create(['role' => 'ejecutivo', 'name' => 'Jose Luis Rodriguez']);
        $quote = Cotizacion::factory()->create(['user_id' => $jose->id]);
        $this->solicitar($this->rodrigo, ['items' => $this->items(), 'quote_identifier' => $quote->id]);

        $reserva = collect($this->bitacora())->firstWhere('tipo_movimiento', 'RESERVA_PRODUCTO');
        $this->assertSame('Rodrigo Fuentealba', $reserva['solicitante']);
    }

    public function test_external_reference_is_never_linked_to_an_internal_quote_by_digits(): void
    {
        $interna = Cotizacion::factory()->create();
        $data = $this->solicitar($this->rodrigo, [
            'items' => $this->items(), 'is_external' => true, 'external_quote_number' => 'Factura '.$interna->id, 'external_quote_name' => 'Uniformes de prueba',
        ]);

        $this->assertNull($data[0]['cotizacion_id']);
        $this->assertDatabaseHas('pte_movimientos', ['peticion_id' => $data[0]['id'], 'cotizacion_id' => null]);
    }

    public function test_dispatch_pdf_shows_the_approver_even_when_the_executive_downloads_it(): void
    {
        $data = $this->solicitar($this->rodrigo, ['items' => $this->items(), 'is_external' => true, 'external_quote_number' => '12625', 'external_quote_name' => 'Uniformes de prueba']);
        Sanctum::actingAs($this->carlos);
        $this->postJson("/api/order-requests/{$data[0]['id']}/confirm")->assertOk();

        $pdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('stream')->once()->andReturn(response('%PDF-test', 200, ['Content-Type' => 'application/pdf']));
        Pdf::shouldReceive('loadView')->once()
            ->with('pdf.order_request', \Mockery::on(fn ($view) => $view['ejecutivo'] === 'Carlos Sanchez' && $view['order']->user->name === 'Rodrigo Fuentealba'))
            ->andReturn($pdf);

        Sanctum::actingAs($this->rodrigo);
        $this->get("/api/order-requests/{$data[0]['id']}/dispatch-pdf")->assertOk();
    }

    public function test_individual_pdf_of_a_request_shows_its_resolution(): void
    {
        $data = $this->solicitar($this->rodrigo, ['items' => $this->items(), 'is_external' => true, 'external_quote_number' => '12625', 'external_quote_name' => 'Uniformes de prueba']);
        Sanctum::actingAs($this->carlos);
        $this->postJson("/api/order-requests/{$data[0]['id']}/confirm")->assertOk();
        $reserva = PteMovimiento::where('tipo_movimiento', 'RESERVA_PRODUCTO')->firstOrFail();

        $pdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('stream')->once()->andReturn(response('%PDF-test', 200, ['Content-Type' => 'application/pdf']));
        Pdf::shouldReceive('loadView')->once()->with('pdf.bitacora', \Mockery::on(function ($view) {
            $lote = $view['reporte'][0];

            return $lote['solicitante'] === 'Rodrigo Fuentealba'
                && str_starts_with((string) $lote['estado_solicitud'], 'Aprobada por Carlos Sanchez');
        }))->andReturn($pdf);

        $this->get('/api/movimientos/pdf/individual?movimiento_id='.$reserva->id)->assertOk();
    }

    public function test_pending_requests_are_counted_per_requester(): void
    {
        $claudia = User::factory()->create(['role' => 'ejecutivo', 'name' => 'Claudia Sanchez']);
        $this->solicitar($this->rodrigo, ['items' => $this->items(1), 'is_external' => true, 'external_quote_number' => '12608', 'external_quote_name' => 'Uniformes de prueba']);
        $this->solicitar($claudia, ['items' => $this->items(1), 'is_external' => true, 'external_quote_number' => '12608', 'external_quote_name' => 'Uniformes de prueba']);

        $this->getJson('/api/order-requests/count')->assertOk()->assertJsonPath('count', 2);
    }

    public function test_link_command_is_dry_run_by_default_and_links_only_unique_matches(): void
    {
        // Historial previo al vínculo: se quitan los peticion_id recién guardados.
        $a = $this->solicitar($this->rodrigo, ['items' => $this->items(), 'is_external' => true, 'external_quote_number' => '12625', 'external_quote_name' => 'Uniformes de prueba']);
        Sanctum::actingAs($this->carlos);
        $this->travel(5)->minutes();
        $this->postJson("/api/order-requests/{$a[0]['id']}/confirm")->assertOk();
        // Dos reservas idénticas del mismo usuario y minuto: ambiguas, no se vinculan.
        $sku = $this->items()[0]['sku'];
        $ambiguas = $this->solicitar($this->rodrigo, ['items' => [['sku' => $sku, 'quantity' => 1]], 'is_external' => true, 'external_quote_number' => 'X-1', 'external_quote_name' => 'Uniformes de prueba']);
        $this->solicitar($this->rodrigo, ['items' => [['sku' => $sku, 'quantity' => 1]], 'is_external' => true, 'external_quote_number' => 'X-1', 'external_quote_name' => 'Uniformes de prueba']);
        DB::table('pte_movimientos')->update(['peticion_id' => null]);

        $this->artisan('pte:vincular-movimientos-peticiones')->assertSuccessful();
        $this->assertSame(0, PteMovimiento::whereNotNull('peticion_id')->count());

        $this->artisan('pte:vincular-movimientos-peticiones', ['--apply' => true])->assertSuccessful();
        $this->assertDatabaseHas('pte_movimientos', ['tipo_movimiento' => 'RESERVA_PRODUCTO', 'peticion_id' => $a[0]['id']]);
        $this->assertDatabaseHas('pte_movimientos', ['tipo_movimiento' => 'SALIDA_VENTA', 'peticion_id' => $a[0]['id']]);
        $this->assertSame(0, PteMovimiento::where('referencia_externa', 'X-1')->whereNotNull('peticion_id')->count());
        $this->assertSame('Pendiente', Peticion::with('estado')->find($ambiguas[0]['id'])->estado->nombre);

        // Idempotente
        $this->artisan('pte:vincular-movimientos-peticiones', ['--apply' => true])->assertSuccessful();
        $this->assertSame(2, PteMovimiento::whereNotNull('peticion_id')->count());
    }

    public function test_link_command_matches_history_saved_before_movements_had_external_reference(): void
    {
        // Antes del 23-09 el movimiento no guardaba referencia_externa; la solicitud sí.
        $a = $this->solicitar($this->rodrigo, ['items' => $this->items(), 'is_external' => true, 'external_quote_number' => '12500', 'external_quote_name' => 'Uniformes de prueba']);
        Sanctum::actingAs($this->carlos);
        $this->postJson("/api/order-requests/{$a[0]['id']}/reject")->assertOk();
        DB::table('pte_movimientos')->update(['peticion_id' => null, 'referencia_externa' => null]);

        $this->artisan('pte:vincular-movimientos-peticiones', ['--apply' => true])->assertSuccessful();

        $this->assertDatabaseHas('pte_movimientos', ['tipo_movimiento' => 'RESERVA_PRODUCTO', 'peticion_id' => $a[0]['id']]);
        $this->assertDatabaseHas('pte_movimientos', ['tipo_movimiento' => 'ANULACION_RESERVA', 'peticion_id' => $a[0]['id']]);
    }
}
