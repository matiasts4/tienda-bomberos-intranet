<?php

namespace Tests\Feature\Comercial;

use App\Models\Compania;
use App\Models\Cotizacion;
use App\Models\Cuerpo;
use App\Models\Despacho;
use App\Models\DetalleCotizacion;
use App\Models\Estado;
use App\Models\OrdenCorte;
use Tests\TestCase;

class CompanyOrderHistoryApiTest extends TestCase
{
    private function seedInstitutionalDataset(): array
    {
        $user = $this->actingAsAdmin();

        $cuerpo = Cuerpo::create([
            'id' => '1300100',
            'nombre' => 'Santiago',
        ]);

        $cia14 = Compania::create([
            'id' => '1300114',
            'cuerpo_id' => $cuerpo->id,
            'nombre' => '14ª Compañía',
            'numero' => 14,
        ]);

        $cia05 = Compania::create([
            'id' => '1300105',
            'cuerpo_id' => $cuerpo->id,
            'nombre' => '5ª Compañía',
            'numero' => 5,
        ]);

        $estadoAprobada = Estado::create([
            'nombre' => 'Aprobada por Cliente',
            'scope' => 'cotizacion',
            'orden' => 2,
            'color' => '#16a34a',
        ]);

        $estadoBorrador = Estado::create([
            'nombre' => 'Borrador',
            'scope' => 'cotizacion',
            'orden' => 1,
            'color' => '#64748b',
        ]);

        $estadoEntregado = Estado::create([
            'nombre' => 'Entregado',
            'scope' => 'despacho',
            'orden' => 4,
            'color' => '#10b981',
        ]);

        // Cotización aprobada 2026 (Casaca) con orden terminada y despacho entregado
        $quote2026 = Cotizacion::factory()->forUser($user)->withEstado($estadoAprobada)->create([
            'cliente_id' => null,
            'cuerpo_id' => $cuerpo->id,
            'compania_id' => $cia14->id,
            'numero' => 'CT-2026-101',
            'nombre' => 'Casacas de Gala 2026',
            'fecha_creacion' => '2026-05-10',
            'fecha_venta' => '2026-05-12',
            'total_neto' => 500000,
            'iva' => 95000,
            'total' => 595000,
        ]);

        DetalleCotizacion::factory()->forCotizacion($quote2026)->create([
            'producto' => 'Casaca Institucional',
            'talla' => 'L',
            'color' => 'Azul Marino',
            'cantidad' => 10,
        ]);

        $order2026 = OrdenCorte::factory()
            ->forCotizacion($quote2026)
            ->inStage(OrdenCorte::STAGE_TERMINADO)
            ->create([
                'selected_product' => 'Casaca Institucional',
                'total_unidades' => 10,
                'created_at' => '2026-05-13 10:00:00',
            ]);

        $order2026->items()->create([
            'product_type' => 'Casaca Institucional',
            'size' => 'L',
            'color' => 'Azul Marino',
            'quantity' => 10,
            'fabric_type' => 'Gabardina',
        ]);

        Despacho::create([
            'cotizacion_id' => $quote2026->id,
            'cliente_id' => null,
            'estado_id' => $estadoEntregado->id,
            'fecha_despacho' => '2026-06-01',
        ]);

        // Cotización aprobada 2025 (Pantalón) con orden en corte
        $quote2025 = Cotizacion::factory()->forUser($user)->withEstado($estadoAprobada)->create([
            'cliente_id' => null,
            'cuerpo_id' => $cuerpo->id,
            'compania_id' => $cia14->id,
            'numero' => 'CT-2025-020',
            'nombre' => 'Pantalones Faena 2025',
            'fecha_creacion' => '2025-03-01',
            'fecha_venta' => '2025-03-05',
            'total_neto' => 200000,
            'iva' => 38000,
            'total' => 238000,
        ]);

        DetalleCotizacion::factory()->forCotizacion($quote2025)->create([
            'producto' => 'Pantalón Operativo',
            'talla' => 'M',
            'color' => 'Negro',
            'cantidad' => 6,
        ]);

        $order2025 = OrdenCorte::factory()
            ->forCotizacion($quote2025)
            ->inStage(OrdenCorte::STAGE_CORTE)
            ->create([
                'selected_product' => 'Pantalón Operativo',
                'total_unidades' => 6,
                'created_at' => '2025-03-06 09:00:00',
            ]);

        $order2025->items()->create([
            'product_type' => 'Pantalón Operativo',
            'size' => 'M',
            'color' => 'Negro',
            'quantity' => 6,
            'fabric_type' => 'Ripstop',
        ]);

        // Cotización en borrador para la misma compañía (NO debe aparecer)
        Cotizacion::factory()->forUser($user)->withEstado($estadoBorrador)->create([
            'cliente_id' => null,
            'cuerpo_id' => $cuerpo->id,
            'compania_id' => $cia14->id,
            'numero' => 'CT-2026-999',
            'fecha_creacion' => '2026-08-01',
        ]);

        // Cotización aprobada de otra compañía (5ª Cía)
        $otherQuote = Cotizacion::factory()->forUser($user)->withEstado($estadoAprobada)->create([
            'cliente_id' => null,
            'cuerpo_id' => $cuerpo->id,
            'compania_id' => $cia05->id,
            'numero' => 'CT-2026-505',
            'fecha_creacion' => '2026-07-01',
            'fecha_venta' => '2026-07-02',
            'total' => 119000,
        ]);

        DetalleCotizacion::factory()->forCotizacion($otherQuote)->create([
            'producto' => 'Polera Institucional',
            'cantidad' => 5,
        ]);

        return compact('cuerpo', 'cia14', 'cia05');
    }

    public function test_company_orders_requires_authentication(): void
    {
        $this->getJson('/api/companies/1300114/orders')->assertUnauthorized();
    }

    public function test_company_orders_returns_404_when_company_does_not_exist(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/companies/9999999/orders')
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_company_orders_returns_consolidated_chronological_history(): void
    {
        $data = $this->seedInstitutionalDataset();

        $response = $this->getJson("/api/companies/{$data['cia14']->id}/orders")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('company.id', '1300114')
            ->assertJsonPath('summary.total_approved_quotes', 2)
            ->assertJsonPath('summary.total_workshop_orders', 2)
            ->assertJsonPath('summary.completed_workshop_orders', 1)
            ->assertJsonPath('summary.delivered_orders', 1)
            ->assertJsonPath('summary.total_amount', 833000);

        $timeline = $response->json('timeline');
        $this->assertCount(5, $timeline);
        $this->assertSame('delivered_order', $timeline[0]['type']);
        $this->assertSame('2026-06-01', $timeline[0]['date']);
    }

    public function test_company_orders_filters_by_year_stage_and_garment_type(): void
    {
        $data = $this->seedInstitutionalDataset();

        // Filtro por año 2026
        $byYear = $this->getJson("/api/companies/{$data['cia14']->id}/orders?year=2026")
            ->assertOk();
        $this->assertSame(1, $byYear->json('summary.total_approved_quotes'));
        $this->assertSame(1, $byYear->json('summary.total_workshop_orders'));
        $this->assertSame(1, $byYear->json('summary.delivered_orders'));

        // Filtro por estado de producción (corte)
        $byStage = $this->getJson("/api/companies/{$data['cia14']->id}/orders?estado_produccion=corte")
            ->assertOk();
        $this->assertSame(1, $byStage->json('summary.total_workshop_orders'));
        $this->assertSame('Pantalón Operativo', $byStage->json('workshop_orders.0.selected_product'));

        // Filtro por tipo de prenda institucional
        $byGarment = $this->getJson("/api/companies/{$data['cia14']->id}/orders?tipo_prenda=casaca")
            ->assertOk();
        $this->assertSame(1, $byGarment->json('summary.total_approved_quotes'));
        $this->assertSame(1, $byGarment->json('summary.total_workshop_orders'));
        $this->assertSame('Casaca Institucional', $byGarment->json('workshop_orders.0.selected_product'));
    }

    public function test_fire_department_orders_aggregates_all_companies(): void
    {
        $data = $this->seedInstitutionalDataset();

        $this->getJson("/api/fire-departments/{$data['cuerpo']->id}/orders")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('fire_department.id', '1300100')
            ->assertJsonPath('summary.total_approved_quotes', 3)
            ->assertJsonPath('summary.total_workshop_orders', 2)
            ->assertJsonPath('summary.delivered_orders', 1);
    }
}
