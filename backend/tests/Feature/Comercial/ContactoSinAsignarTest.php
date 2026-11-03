<?php

namespace Tests\Feature\Comercial;

use App\Models\Cliente;
use App\Models\Compania;
use App\Models\Contacto;
use App\Models\ContactoActividad;
use App\Models\Cotizacion;
use App\Models\Cuerpo;
use App\Models\Estado;
use App\Models\Oportunidad;
use Barryvdh\DomPDF\Facade\Pdf;
use Tests\TestCase;

/**
 * Un contacto sin Cuerpo, Compañía ni empresa se puede prospectar, calificar,
 * cotizar como persona natural y asignar después a una organización.
 */
class ContactoSinAsignarTest extends TestCase
{
    private Cuerpo $cuerpo;
    private Compania $compania;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsUser(['role' => 'superadmin']);
        $this->cuerpo = Cuerpo::create(['id' => '0500100', 'nombre' => 'Valparaíso']);
        $this->compania = Compania::create(['id' => '0500101', 'cuerpo_id' => $this->cuerpo->id, 'nombre' => '1ª Compañía', 'numero' => 1]);
    }

    private function empresa(string $nombre, string $tipo = 'empresa'): Cliente
    {
        return Cliente::factory()->create(['nombre_empresa' => $nombre, 'tipo' => $tipo]);
    }

    /** @return array<int, string> */
    private function nombresConAsignacion(string $asignacion): array
    {
        return array_column($this->getJson("/api/contactos?asignacion={$asignacion}")->assertOk()->json('data'), 'nombre');
    }

    public function test_contacto_sin_asignar_recorre_prospeccion_lead_cotizacion_y_pdf_y_se_asigna_despues(): void
    {
        $estadosOp = $this->seedEstadosOportunidad();
        Estado::create(['nombre' => 'Nuevo', 'scope' => 'oportunidad', 'orden' => 0]);
        $this->seedEstadosCotizacion();

        // 1. Se crea sin organización (Clientes y Cuerpos → Contactos, o "Nueva Oportunidad" sin entidad)
        $contactoId = $this->postJson('/api/contactos', [
            'nombre' => 'Juan Pérez',
            'rut' => '11.111.111-1',
            'telefono' => '+56911111111',
        ])->assertCreated()->json('id');

        $this->assertSame(['Juan Pérez'], $this->nombresConAsignacion('sin_asignar'));
        $this->getJson('/api/contactos/conteos')->assertOk()->assertJsonPath('sin_asignar', 1);

        // 2. Aparece en Prospección, en el nodo "Sin cuerpo asignado"
        $cola = $this->getJson('/api/prospeccion/contactos?sin_cuerpo=1')->assertOk()->json('data');
        $this->assertSame(['Juan Pérez'], array_column($cola, 'nombre'));

        // 3. Se califica: el lead queda a nombre de la persona
        $oportunidadId = $this->postJson("/api/prospeccion/contactos/{$contactoId}/calificar", [
            'necesidad' => 'Pantalones',
            'cantidad' => 10,
            'user_id' => auth()->id(),
        ])->assertCreated()->json('oportunidad_id');

        $oportunidad = Oportunidad::findOrFail($oportunidadId);
        $this->assertNull($oportunidad->cuerpo_id);
        $this->assertNull($oportunidad->compania_id);
        $this->assertNull($oportunidad->cliente_id);
        $this->assertSame('persona_natural', $oportunidad->tipo_destinatario);

        // 4. Se cotiza: la cotización hereda el contacto y el PDF sale a su nombre
        $cotizacionId = $this->patchJson("/api/oportunidades/{$oportunidadId}/estado", [
            'estado_id' => $estadosOp['Cotización Enviada']->id,
        ])->assertOk()->json('cotizacion_id');

        $this->getJson("/api/cotizaciones/{$cotizacionId}")
            ->assertOk()
            ->assertJsonPath('contacto_id', $contactoId)
            ->assertJsonPath('tipo_destinatario', 'persona_natural')
            ->assertJsonPath('nombre_cliente', 'Juan Pérez');

        $pdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('stream')->andReturn(response('%PDF-1.4 fake', 200, ['Content-Type' => 'application/pdf']));
        $datosPdf = null;
        Pdf::shouldReceive('loadView')->once()->withArgs(function ($vista, $datos) use (&$datosPdf) {
            $datosPdf = $datos;

            return true;
        })->andReturn($pdf);
        $this->get("/api/cotizaciones/{$cotizacionId}/pdf")->assertOk();
        $this->assertSame('Juan Pérez', $datosPdf['nombreClientePdf']);
        $this->assertSame('11.111.111-1', $datosPdf['rutClientePdf']);

        // 5. Después se sabe de qué Compañía es: se asigna y pasan con él el lead y la cotización
        $this->getJson("/api/contactos/{$contactoId}/registros-sin-entidad")
            ->assertOk()
            ->assertJsonPath('oportunidades.0.id', $oportunidadId)
            ->assertJsonPath('oportunidades.0.abierta', true)
            ->assertJsonPath('cotizaciones.0.id', $cotizacionId)
            ->assertJsonPath('cotizaciones.0.abierta', true);

        $this->postJson("/api/contactos/{$contactoId}/asignar", [
            'tipo' => 'compania',
            'id' => $this->compania->id,
            'oportunidad_ids' => [$oportunidadId],
            'cotizacion_ids' => [$cotizacionId],
        ])->assertOk()
            ->assertJsonPath('contacto.companias.0.id', $this->compania->id)
            ->assertJsonPath('oportunidades_actualizadas', [$oportunidadId])
            ->assertJsonPath('cotizaciones_actualizadas', [$cotizacionId]);

        $contacto = Contacto::findOrFail($contactoId);
        $this->assertTrue($contacto->companias()->whereKey($this->compania->id)->exists());
        $this->assertTrue($contacto->cuerpos()->whereKey($this->cuerpo->id)->exists(), 'Quien es de una Compañía es también de su Cuerpo');

        $this->assertSame($this->compania->id, $oportunidad->fresh()->compania_id);
        $this->assertSame('bomberos', $oportunidad->fresh()->tipo_destinatario);
        $this->getJson("/api/cotizaciones/{$cotizacionId}")
            ->assertJsonPath('tipo_destinatario', 'bomberos')
            ->assertJsonPath('nombre_cliente', 'Cuerpo de Bomberos de Valparaíso – 1ª Compañía');

        $actividad = ContactoActividad::where('contacto_id', $contactoId)->where('accion', ContactoActividad::ACCION_VINCULOS)->sole();
        $this->assertSame('Asignó el contacto a 1ª Compañía (Valparaíso)', $actividad->descripcion);

        $this->assertSame([], $this->nombresConAsignacion('sin_asignar'));
        $this->getJson('/api/contactos/conteos')->assertJsonPath('sin_asignar', 0)->assertJsonPath('red_bomberos', 1);
    }

    public function test_asignar_no_cambia_ventas_ni_leads_cerrados(): void
    {
        $contacto = Contacto::factory()->create(['nombre' => 'Rosa Díaz']);
        $abierto = Estado::create(['nombre' => 'Enviar cotización', 'scope' => 'oportunidad', 'orden' => 1]);
        $ganado = Estado::create(['nombre' => 'Cerrada ganada', 'scope' => 'oportunidad', 'orden' => 2]);
        $enviada = Estado::create(['nombre' => 'Cotización Enviada', 'scope' => 'cotizacion', 'orden' => 1]);
        $venta = Estado::create(['nombre' => 'Ventas', 'scope' => 'cotizacion', 'orden' => 2]);

        $leadAbierto = Oportunidad::create(['contacto_id' => $contacto->id, 'estado_id' => $abierto->id, 'user_id' => auth()->id()]);
        $leadGanado = Oportunidad::create(['contacto_id' => $contacto->id, 'estado_id' => $ganado->id, 'user_id' => auth()->id()]);
        $cotAbierta = Cotizacion::factory()->create(['cliente_id' => null, 'contacto_id' => $contacto->id, 'estado_id' => $enviada->id]);
        $cotVendida = Cotizacion::factory()->create(['cliente_id' => null, 'contacto_id' => $contacto->id, 'estado_id' => $venta->id]);

        $this->getJson("/api/contactos/{$contacto->id}/registros-sin-entidad")
            ->assertOk()
            ->assertJsonPath('oportunidades.1.abierta', false)
            ->assertJsonPath('cotizaciones.1.abierta', false);

        // Aunque se pidan todos, solo pasan a la organización los abiertos
        $this->postJson("/api/contactos/{$contacto->id}/asignar", [
            'tipo' => 'cuerpo',
            'id' => $this->cuerpo->id,
            'oportunidad_ids' => [$leadAbierto->id, $leadGanado->id],
            'cotizacion_ids' => [$cotAbierta->id, $cotVendida->id],
        ])->assertOk()
            ->assertJsonPath('oportunidades_actualizadas', [$leadAbierto->id])
            ->assertJsonPath('cotizaciones_actualizadas', [$cotAbierta->id]);

        $this->assertSame($this->cuerpo->id, $leadAbierto->fresh()->cuerpo_id);
        $this->assertSame($this->cuerpo->id, $cotAbierta->fresh()->cuerpo_id);
        $this->assertNull($leadGanado->fresh()->cuerpo_id);
        $this->assertNull($cotVendida->fresh()->cuerpo_id, 'La venta queda como se emitió: a nombre de la persona');
    }

    public function test_asignar_sin_elegir_registros_solo_vincula_el_contacto(): void
    {
        $contacto = Contacto::factory()->create();
        $estado = Estado::create(['nombre' => 'Nuevo', 'scope' => 'oportunidad', 'orden' => 1]);
        $lead = Oportunidad::create(['contacto_id' => $contacto->id, 'estado_id' => $estado->id, 'user_id' => auth()->id()]);

        $this->postJson("/api/contactos/{$contacto->id}/asignar", ['tipo' => 'cuerpo', 'id' => $this->cuerpo->id])
            ->assertOk()
            ->assertJsonPath('oportunidades_actualizadas', []);

        $this->assertTrue($contacto->cuerpos()->whereKey($this->cuerpo->id)->exists());
        $this->assertNull($lead->fresh()->cuerpo_id);
    }

    public function test_asignar_a_empresa_la_reemplaza_y_no_acepta_copias_bomberos(): void
    {
        $anterior = $this->empresa('Constructora Andes');
        $nueva = $this->empresa('Minera Norte');
        $copia = $this->empresa('CB Valparaíso', 'bomberos');
        $contacto = Contacto::factory()->create(['cliente_id' => $anterior->id]);

        $this->postJson("/api/contactos/{$contacto->id}/asignar", ['tipo' => 'empresa', 'id' => $nueva->id])
            ->assertOk()
            ->assertJsonPath('contacto.cliente.nombre_empresa', 'Minera Norte');

        $actividad = ContactoActividad::where('contacto_id', $contacto->id)->where('accion', ContactoActividad::ACCION_VINCULOS)->sole();
        $this->assertSame(['agregados' => [$nueva->id], 'quitados' => [$anterior->id]], $actividad->cambios['clientes']);
        $this->assertFalse(
            ContactoActividad::where('contacto_id', $contacto->id)->where('accion', ContactoActividad::ACCION_ACTUALIZADO)->exists(),
            'El cambio de empresa queda como vínculo, no como "datos editados"'
        );

        $this->postJson("/api/contactos/{$contacto->id}/asignar", ['tipo' => 'empresa', 'id' => $copia->id])
            ->assertStatus(422)->assertJsonValidationErrors(['id']);
        $this->postJson("/api/contactos/{$contacto->id}/asignar", ['tipo' => 'region', 'id' => '1'])
            ->assertStatus(422)->assertJsonValidationErrors(['tipo']);
        $this->postJson("/api/contactos/{$contacto->id}/asignar", ['tipo' => 'compania', 'id' => 'NO-EXISTE'])
            ->assertStatus(422)->assertJsonValidationErrors(['id']);
    }

    public function test_filtro_de_asignacion_y_conteos(): void
    {
        Contacto::factory()->create(['nombre' => 'De Cuerpo'])->cuerpos()->attach($this->cuerpo->id);
        Contacto::factory()->create(['nombre' => 'De Compañía'])->companias()->attach($this->compania->id);
        Contacto::factory()->create(['nombre' => 'De Empresa', 'cliente_id' => $this->empresa('Minera Norte')->id]);
        Contacto::factory()->create(['nombre' => 'Suelto']);
        Contacto::factory()->create(['nombre' => 'Empresa borrada', 'cliente_id' => 'NO-EXISTE']);

        $this->assertSame(['Empresa borrada', 'Suelto'], $this->nombresConAsignacion('sin_asignar'));
        $this->assertSame(['De Compañía', 'De Cuerpo'], $this->nombresConAsignacion('red_bomberos'));
        $this->assertSame(['De Empresa'], $this->nombresConAsignacion('empresa'));
        $this->assertCount(5, $this->getJson('/api/contactos?asignacion=cualquiera')->json('data'));

        $this->getJson('/api/contactos/conteos')->assertOk()->assertExactJson([
            'total' => 5,
            'sin_asignar' => 2,
            'red_bomberos' => 2,
            'empresa' => 1,
        ]);
    }

    public function test_la_cola_de_prospeccion_trae_la_empresa_del_contacto(): void
    {
        Contacto::factory()->create(['nombre' => 'De Empresa', 'cliente_id' => $this->empresa('Minera Norte')->id]);

        $this->getJson('/api/prospeccion/contactos')
            ->assertOk()
            ->assertJsonPath('data.0.cliente.nombre_empresa', 'Minera Norte');
    }

    public function test_un_lead_sin_destinatario_se_rechaza(): void
    {
        $estados = $this->seedEstadosOportunidad();

        $this->postJson('/api/oportunidades', [
            'estado_id' => $estados['Nueva']->id,
            'user_id' => auth()->id(),
        ])->assertStatus(422)->assertJsonValidationErrors(['entidad']);

        $this->assertSame(0, Oportunidad::count());
    }

    public function test_editar_un_lead_no_puede_dejarlo_sin_destinatario(): void
    {
        $estados = $this->seedEstadosOportunidad();
        $contacto = Contacto::factory()->create();
        $lead = Oportunidad::create(['contacto_id' => $contacto->id, 'estado_id' => $estados['Nueva']->id, 'user_id' => auth()->id()]);

        $this->patchJson("/api/oportunidades/{$lead->id}", [
            'cliente_id' => null, 'cuerpo_id' => null, 'compania_id' => null, 'contacto_id' => null,
        ])->assertStatus(422)->assertJsonValidationErrors(['entidad']);
        $this->assertSame($contacto->id, $lead->fresh()->contacto_id);

        // Un lead antiguo sin destinatario se puede seguir editando en lo demás
        $antiguo = Oportunidad::create(['estado_id' => $estados['Nueva']->id, 'user_id' => auth()->id()]);
        $this->patchJson("/api/oportunidades/{$antiguo->id}", ['referido_por' => 'Web'])->assertOk();
    }

    public function test_al_dar_organizacion_a_un_lead_su_contacto_queda_en_ella(): void
    {
        $estados = $this->seedEstadosOportunidad();
        $contacto = Contacto::factory()->create(['nombre' => 'Ana Rojas']);
        $lead = Oportunidad::create(['contacto_id' => $contacto->id, 'estado_id' => $estados['Nueva']->id, 'user_id' => auth()->id()]);

        $this->patchJson("/api/oportunidades/{$lead->id}", [
            'compania_id' => $this->compania->id,
            'contacto_id' => $contacto->id,
        ])->assertOk();

        $this->assertTrue($contacto->companias()->whereKey($this->compania->id)->exists());
        $this->assertTrue($contacto->cuerpos()->whereKey($this->cuerpo->id)->exists());
        $this->assertTrue(ContactoActividad::where('contacto_id', $contacto->id)
            ->where('accion', ContactoActividad::ACCION_VINCULOS)->where('origen', 'oportunidades')->exists());
    }

    public function test_un_lead_de_otra_empresa_no_le_quita_la_empresa_al_contacto(): void
    {
        $estados = $this->seedEstadosOportunidad();
        $suya = $this->empresa('Constructora Andes');
        $otra = $this->empresa('Minera Norte');
        $contacto = Contacto::factory()->create(['cliente_id' => $suya->id]);

        $this->postJson('/api/oportunidades', [
            'estado_id' => $estados['Nueva']->id,
            'user_id' => auth()->id(),
            'cliente_id' => $otra->id,
            'contacto_id' => $contacto->id,
        ])->assertCreated();

        $this->assertSame($suya->id, $contacto->fresh()->cliente_id);
    }
}
