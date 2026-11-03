<?php

namespace Tests\Feature\Comercial;

use App\Models\Cliente;
use App\Models\Contacto;
use App\Models\Cotizacion;
use App\Models\Oportunidad;
use Barryvdh\DomPDF\Facade\Pdf;
use Tests\TestCase;

class CotizacionDestinatarioTest extends TestCase
{
    private function cotizacionPara(array $attrs): Cotizacion
    {
        $this->seedEstadosCotizacion();

        return Cotizacion::factory()->create(array_merge(['cliente_id' => null], $attrs));
    }

    public function test_deduce_a_quien_va_dirigida_la_cotizacion(): void
    {
        $empresa = Cliente::factory()->create(['tipo' => 'empresa']);
        $persona = Cliente::factory()->create(['tipo' => 'particular']);
        $espejo = Cliente::factory()->create(['tipo' => 'bomberos']);
        $contacto = Contacto::factory()->create();

        $this->assertSame('empresa', $this->cotizacionPara(['cliente_id' => $empresa->id])->tipo_destinatario);
        $this->assertSame('persona_natural', $this->cotizacionPara(['cliente_id' => $persona->id])->tipo_destinatario);
        $this->assertSame('bomberos', $this->cotizacionPara(['cliente_id' => $espejo->id])->tipo_destinatario);
        $this->assertSame('bomberos', $this->cotizacionPara(['cuerpo_id' => '13001'])->tipo_destinatario);
        $this->assertSame('bomberos', $this->cotizacionPara(['compania_id' => '1300101'])->tipo_destinatario);
        $this->assertSame('persona_natural', $this->cotizacionPara(['contacto_id' => $contacto->id])->tipo_destinatario, 'solo un contacto = persona natural');
    }

    public function test_cuerpo_manda_sobre_un_cliente_particular(): void
    {
        $persona = Cliente::factory()->create(['tipo' => 'particular']);

        $this->assertSame('bomberos', $this->cotizacionPara(['cliente_id' => $persona->id, 'cuerpo_id' => '13001'])->tipo_destinatario);
    }

    public function test_se_puede_crear_una_cotizacion_a_una_persona_natural(): void
    {
        $this->seedEstadosCotizacion();
        $this->actingAsEjecutivo();
        $persona = Cliente::factory()->create(['tipo' => 'particular', 'nombre_empresa' => 'Juan Pérez Soto']);

        $this->postJson('/api/cotizaciones', ['cliente_id' => $persona->id, 'nombre' => 'Uniforme personal'])
            ->assertCreated()
            ->assertJsonPath('tipo_destinatario', 'persona_natural')
            ->assertJsonPath('nombre_cliente', 'Juan Pérez Soto');
    }

    public function test_listado_expone_y_filtra_por_tipo_destinatario(): void
    {
        $this->seedEstadosCotizacion();
        $this->actingAsEjecutivo();
        $empresa = Cliente::factory()->create(['tipo' => 'empresa']);
        $persona = Cliente::factory()->create(['tipo' => 'particular']);
        $contacto = Contacto::factory()->create();
        $cEmpresa = Cotizacion::factory()->forCliente($empresa)->create();
        $cPersona = Cotizacion::factory()->forCliente($persona)->create();
        $cContacto = Cotizacion::factory()->create(['cliente_id' => null, 'contacto_id' => $contacto->id]);
        $cBomberos = Cotizacion::factory()->create(['cliente_id' => null, 'cuerpo_id' => '13001']);

        $ids = fn (string $tipo) => collect($this->getJson("/api/cotizaciones?tipo_destinatario={$tipo}")->assertOk()->json('data'))->pluck('id')->sort()->values()->all();

        $this->assertSame([$cPersona->id, $cContacto->id], $ids('persona_natural'));
        $this->assertSame([$cEmpresa->id], $ids('empresa'));
        $this->assertSame([$cBomberos->id], $ids('bomberos'));

        $todas = $this->getJson('/api/cotizaciones')->assertOk()->json('data');
        $this->assertCount(4, $todas);
        $this->assertContains('persona_natural', array_column($todas, 'tipo_destinatario'));
    }

    public function test_oportunidad_tambien_expone_y_filtra_el_destinatario(): void
    {
        $this->seedEstadosOportunidad();
        $this->actingAsEjecutivo();
        $persona = Cliente::factory()->create(['tipo' => 'particular']);
        $empresa = Cliente::factory()->create(['tipo' => 'empresa']);
        $opPersona = Oportunidad::factory()->forCliente($persona)->create();
        Oportunidad::factory()->forCliente($empresa)->create();

        $res = $this->getJson('/api/oportunidades?tipo_destinatario=persona_natural')->assertOk();
        $this->assertSame([$opPersona->id], array_column($res->json('data'), 'id'));
        $this->assertSame('persona_natural', $res->json('data.0.tipo_destinatario'));
    }

    public function test_pdf_de_persona_natural_no_muestra_giro(): void
    {
        $this->seedEstadosCotizacion();
        $this->actingAsEjecutivo();
        $persona = Cliente::factory()->create(['tipo' => 'particular', 'giro' => 'Servicios varios']);
        $empresa = Cliente::factory()->create(['tipo' => 'empresa', 'giro' => 'Servicios varios']);

        $render = function (Cliente $cliente): string {
            $cot = Cotizacion::factory()->forCliente($cliente)->create();
            $captured = null;
            $pdfMock = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
            $pdfMock->shouldReceive('stream')->andReturn(response('%PDF', 200));
            Pdf::shouldReceive('loadView')->once()->withArgs(function ($view, $data) use (&$captured) {
                $captured = $data;
                return true;
            })->andReturn($pdfMock);

            $this->get("/api/cotizaciones/{$cot->id}/pdf")->assertOk();

            return view('pdf.cotizacion', $captured)->render();
        };

        $this->assertStringNotContainsString('Giro:', $render($persona));
        $this->assertStringContainsString('Giro:', $render($empresa));
    }

    public function test_lead_anclado_solo_a_una_persona_del_buscador_es_persona_natural(): void
    {
        $estados = $this->seedEstadosOportunidad();
        $this->seedEstadosCotizacion();
        $this->actingAsEjecutivo();
        $persona = Contacto::factory()->create(['nombre' => 'Aaron Pérez']);

        $op = $this->postJson('/api/oportunidades', [
            'contacto_id' => $persona->id,
            'user_id' => auth()->id(),
            'estado_id' => $estados['Nueva']->id,
        ])->assertCreated()->assertJsonPath('tipo_destinatario', 'persona_natural')->json();

        $cotId = $this->patchJson("/api/oportunidades/{$op['id']}/estado", ['estado_id' => $estados['Aprobada - Cotización']->id])
            ->assertOk()->json('cotizacion_id');

        $cot = Cotizacion::findOrFail($cotId);
        $this->assertSame('persona_natural', $cot->tipo_destinatario);
        $this->assertSame('Aaron Pérez', $cot->nombre_cliente);
    }
}
