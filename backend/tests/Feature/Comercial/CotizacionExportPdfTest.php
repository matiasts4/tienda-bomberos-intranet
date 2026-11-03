<?php

namespace Tests\Feature\Comercial;

use App\Models\Cliente;
use App\Models\Comuna;
use App\Models\Cotizacion;
use App\Models\Cuerpo;
use App\Models\DetalleCotizacion;
use App\Models\Region;
use Barryvdh\DomPDF\Facade\Pdf;
use Mockery\MockInterface;
use Tests\TestCase;

class CotizacionExportPdfTest extends TestCase
{
    /**
     * Mockea el facade Pdf::loadView() para no depender de DomPDF real
     * (que requiere extensiones adicionales y demora). Verificamos que se
     * pase la VIEW correcta y los datos esperados al renderer.
     */
    private function mockPdf(?callable $assertOnData = null): void
    {
        // Tipamos al mock como la clase real para no romper el return type del facade
        $pdfMock = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdfMock->shouldReceive('stream')
            ->andReturn(response('%PDF-1.4 fake', 200, ['Content-Type' => 'application/pdf']));

        Pdf::shouldReceive('loadView')
            ->once()
            ->withArgs(function ($view, $data) use ($assertOnData) {
                $this->assertSame('pdf.cotizacion', $view);
                if ($assertOnData) {
                    $assertOnData($data);
                }
                return true;
            })
            ->andReturn($pdfMock);
    }

    public function test_genera_pdf_y_retorna_content_type_correcto(): void
    {
        $this->seedEstadosCotizacion();
        $admin = $this->actingAsAdmin();
        $cot = Cotizacion::factory()->forUser($admin)->create();

        $this->mockPdf();

        $response = $this->get("/api/cotizaciones/{$cot->id}/pdf");
        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_pasa_datos_de_cliente_al_view(): void
    {
        $this->seedEstadosCotizacion();
        $admin = $this->actingAsAdmin();
        $cliente = Cliente::factory()->create([
            'nombre_empresa' => 'Bomberos Valparaíso',
            'rut_empresa' => '76.123.456-7',
            'direccion' => 'Av. Brasil 123',
            'giro' => 'Servicios de Emergencia',
        ]);
        $cot = Cotizacion::factory()->forUser($admin)->forCliente($cliente)->create();
        DetalleCotizacion::factory()->forCotizacion($cot)->create([
            'producto' => 'Casco',
            'cantidad' => 5,
            'precio_unitario' => 50000,
        ]);

        $captured = null;
        $this->mockPdf(function ($data) use (&$captured) {
            $captured = $data;
        });

        $this->get("/api/cotizaciones/{$cot->id}/pdf")->assertOk();

        $this->assertSame('Bomberos Valparaíso', $captured['nombreClientePdf']);
        $this->assertSame('76.123.456-7', $captured['rutClientePdf']);
        $this->assertSame('Av. Brasil 123', $captured['direccionClientePdf']);
        $this->assertSame('Servicios de Emergencia', $captured['giroClientePdf']);
        $this->assertSame($cot->id, $captured['cotizacion']->id);
        $this->assertCount(1, $captured['cotizacion']->detalles);
    }

    public function test_expande_nombre_cb_a_cuerpo_de_bomberos(): void
    {
        $this->seedEstadosCotizacion();
        $admin = $this->actingAsAdmin();
        $cliente = Cliente::factory()->create([
            'nombre_empresa' => 'CB Antuco',
        ]);
        $cot = Cotizacion::factory()->forUser($admin)->forCliente($cliente)->create();

        $captured = null;
        $this->mockPdf(function ($data) use (&$captured) {
            $captured = $data;
        });

        $this->get("/api/cotizaciones/{$cot->id}/pdf")->assertOk();

        $this->assertSame('Cuerpo de Bomberos de Antuco', $captured['nombreClientePdf']);
        $this->assertSame('Antuco', $captured['comunaClientePdf']);
    }

    public function test_usa_datos_del_parent_si_cliente_es_compania(): void
    {
        $this->seedEstadosCotizacion();
        $admin = $this->actingAsAdmin();

        $padre = Cliente::factory()->create([
            'nombre_empresa' => 'CB Concepción',
            'rut_empresa' => '70.000.000-0',
            'direccion' => 'Calle Padre 1',
        ]);
        $hijo = Cliente::factory()->create([
            'nombre_empresa' => 'Compañía 3',
            'parent_id' => $padre->id,
            'rut_empresa' => '11.111.111-1',
        ]);
        $cot = Cotizacion::factory()->forUser($admin)->forCliente($hijo)->create();

        $captured = null;
        $this->mockPdf(function ($data) use (&$captured) {
            $captured = $data;
        });

        $this->get("/api/cotizaciones/{$cot->id}/pdf")->assertOk();

        // Datos vienen del PARENT (CB Concepción), no del hijo
        $this->assertSame('Cuerpo de Bomberos de Concepción', $captured['nombreClientePdf']);
        $this->assertSame('70.000.000-0', $captured['rutClientePdf']);
        $this->assertSame('Calle Padre 1', $captured['direccionClientePdf']);
    }

    public function test_comuna_del_cliente_empresa_sale_en_el_pdf(): void
    {
        $this->seedEstadosCotizacion();
        $admin = $this->actingAsAdmin();

        $region = Region::create(['nombre' => 'Biobío']);
        $comuna = Comuna::create(['nombre' => 'Hualpén', 'region_id' => $region->id]);
        $cliente = Cliente::factory()->create([
            'nombre_empresa' => 'ENAP REFINERIAS S.A - BIOBIO',
            'direccion' => 'CAMINO A LENGA 2001',
            'region_id' => $region->id,
            'comuna_id' => $comuna->id,
        ]);
        $cot = Cotizacion::factory()->forUser($admin)->forCliente($cliente)->create();

        $captured = null;
        $this->mockPdf(function ($data) use (&$captured) {
            $captured = $data;
        });

        $this->get("/api/cotizaciones/{$cot->id}/pdf")->assertOk();

        $this->assertSame('Hualpén', $captured['comunaClientePdf']);
    }

    public function test_comuna_vacia_si_cliente_no_tiene_comuna(): void
    {
        $this->seedEstadosCotizacion();
        $admin = $this->actingAsAdmin();
        $cliente = Cliente::factory()->create(['nombre_empresa' => 'Empresa Sin Comuna']);
        $cot = Cotizacion::factory()->forUser($admin)->forCliente($cliente)->create();

        $captured = null;
        $this->mockPdf(function ($data) use (&$captured) {
            $captured = $data;
        });

        $this->get("/api/cotizaciones/{$cot->id}/pdf")->assertOk();

        // No debe caer a región/provincia: la vista imprime "---" cuando está vacío
        $this->assertSame('', $captured['comunaClientePdf']);
    }

    public function test_comuna_y_giro_del_cuerpo_salen_en_el_pdf(): void
    {
        $this->seedEstadosCotizacion();
        $admin = $this->actingAsAdmin();

        $region = Region::create(['nombre' => 'Metropolitana']);
        $comuna = Comuna::create(['nombre' => 'Quilicura', 'region_id' => $region->id]);
        $cuerpo = Cuerpo::create([
            'id' => '1300100',
            'nombre' => 'Quilicura',
            'region_id' => $region->id,
            'comuna_id' => $comuna->id,
            'giro' => 'Servicios de bomberos',
        ]);
        $cot = Cotizacion::factory()->forUser($admin)->create(['cuerpo_id' => $cuerpo->id]);

        $captured = null;
        $this->mockPdf(function ($data) use (&$captured) {
            $captured = $data;
        });

        $this->get("/api/cotizaciones/{$cot->id}/pdf")->assertOk();

        $this->assertSame('Cuerpo de Bomberos de Quilicura', $captured['nombreClientePdf']);
        $this->assertSame('Quilicura', $captured['comunaClientePdf']);
        $this->assertSame('Servicios de bomberos', $captured['giroClientePdf']);
    }

    public function test_por_defecto_el_pdf_lleva_la_fecha_de_hoy(): void
    {
        $this->seedEstadosCotizacion();
        $admin = $this->actingAsAdmin();
        $cot = Cotizacion::factory()->forUser($admin)->create(['fecha_creacion' => '2026-10-06']);

        $captured = null;
        $this->mockPdf(function ($data) use (&$captured) {
            $captured = $data;
        });

        $this->get("/api/cotizaciones/{$cot->id}/pdf")->assertOk();

        $this->assertSame(now()->toDateString(), $captured['fechaPdf']->toDateString());
    }

    public function test_fecha_creacion_imprime_la_fecha_en_que_se_creo(): void
    {
        $this->seedEstadosCotizacion();
        $admin = $this->actingAsAdmin();
        $cot = Cotizacion::factory()->forUser($admin)->create(['fecha_creacion' => '2026-10-06']);

        $captured = null;
        $this->mockPdf(function ($data) use (&$captured) {
            $captured = $data;
        });

        $this->get("/api/cotizaciones/{$cot->id}/pdf?fecha=creacion")->assertOk();

        $this->assertSame('2026-10-06', $captured['fechaPdf']->toDateString());
    }

    public function test_cotizacion_antigua_sin_fecha_creacion_usa_created_at(): void
    {
        $this->seedEstadosCotizacion();
        $admin = $this->actingAsAdmin();
        $cot = Cotizacion::factory()->forUser($admin)->create(['fecha_creacion' => null]);
        $cot->forceFill(['created_at' => '2025-03-14 10:00:00'])->save();

        $captured = null;
        $this->mockPdf(function ($data) use (&$captured) {
            $captured = $data;
        });

        $this->get("/api/cotizaciones/{$cot->id}/pdf?fecha=creacion")->assertOk();

        $this->assertSame('2025-03-14', $captured['fechaPdf']->toDateString());
    }

    public function test_rechaza_una_fecha_fuera_de_las_opciones(): void
    {
        $this->seedEstadosCotizacion();
        $admin = $this->actingAsAdmin();
        $cot = Cotizacion::factory()->forUser($admin)->create();

        $this->getJson("/api/cotizaciones/{$cot->id}/pdf?fecha=2020-01-01")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('fecha');
    }

    public function test_pdf_requiere_autenticacion(): void
    {
        // Sin actingAs, la ruta protegida por auth:sanctum debe rechazar
        $this->seedEstadosCotizacion();
        $admin = \App\Models\User::factory()->create(['role' => 'admin']);
        $cot = Cotizacion::factory()->forUser($admin)->create();

        $this->getJson("/api/cotizaciones/{$cot->id}/pdf")->assertUnauthorized();
    }
}
