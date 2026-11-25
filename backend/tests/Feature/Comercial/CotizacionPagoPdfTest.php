<?php

namespace Tests\Feature\Comercial;

use App\Models\Cliente;
use App\Models\Cotizacion;
use Barryvdh\DomPDF\Facade\Pdf;
use Tests\TestCase;

/**
 * Los datos bancarios de la empresa no van escritos en la plantilla (el repositorio
 * es público): salen de config('empresa.pago') y el bloque se omite si no están.
 */
class CotizacionPagoPdfTest extends TestCase
{
    private function renderPdf(): string
    {
        $this->seedEstadosCotizacion();
        $this->actingAsEjecutivo();
        $cot = Cotizacion::factory()->forCliente(Cliente::factory()->create(['tipo' => 'empresa']))->create();

        $captured = null;
        $pdfMock = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdfMock->shouldReceive('stream')->andReturn(response('%PDF', 200));
        Pdf::shouldReceive('loadView')->once()->withArgs(function ($view, $data) use (&$captured) {
            $captured = $data;
            return true;
        })->andReturn($pdfMock);

        $this->get("/api/cotizaciones/{$cot->id}/pdf")->assertOk();

        return view('pdf.cotizacion', $captured)->render();
    }

    public function test_sin_datos_bancarios_configurados_omite_el_bloque_de_pago(): void
    {
        config(['empresa.pago' => ['banco' => null, 'cuenta' => null, 'email' => null]]);

        $html = $this->renderPdf();

        $this->assertStringNotContainsString('Información de Pago:', $html);
        $this->assertStringNotContainsString('Cuenta Corriente', $html);
    }

    public function test_con_datos_bancarios_configurados_los_muestra(): void
    {
        config(['empresa.pago' => [
            'banco' => 'Cuenta Corriente Banco de Prueba',
            'cuenta' => '000111222',
            'email' => 'pagos@ejemplo.cl',
        ]]);

        $html = $this->renderPdf();

        $this->assertStringContainsString('Información de Pago:', $html);
        $this->assertStringContainsString('Cuenta Corriente Banco de Prueba', $html);
        $this->assertStringContainsString('000111222', $html);
        $this->assertStringContainsString('pagos@ejemplo.cl', $html);
    }
}
