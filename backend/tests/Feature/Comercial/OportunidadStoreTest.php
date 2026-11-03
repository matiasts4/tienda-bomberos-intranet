<?php

namespace Tests\Feature\Comercial;

use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\Oportunidad;
use Tests\TestCase;

class OportunidadStoreTest extends TestCase
{
    public function test_estado_id_es_obligatorio_y_debe_ser_de_scope_oportunidad(): void
    {
        $this->seedEstadosOportunidad();
        $estadosCot = $this->seedEstadosCotizacion();
        $this->actingAsAdmin();

        $this->postJson('/api/oportunidades', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['estado_id']);

        // estado existe pero scope=cotizacion → debe fallar
        $this->postJson('/api/oportunidades', [
            'estado_id' => $estadosCot['Cotización Enviada']->id,
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['estado_id']);
    }

    public function test_crea_oportunidad_con_fecha_ingreso_default_hoy(): void
    {
        $estados = $this->seedEstadosOportunidad();
        // user_id en oportunidades exige rol ejecutivo|superadmin
        $admin = $this->actingAsUser(['role' => 'superadmin']);

        $response = $this->postJson('/api/oportunidades', [
            'estado_id' => $estados['Nueva']->id,
            'user_id' => $admin->id, // dev-diego: user_id es ahora required
            // Un lead necesita destinatario (ver ContactoSinAsignarTest)
            'nombre_contacto' => 'Persona de prueba',
        ])->assertCreated();

        $op = Oportunidad::first();
        $this->assertSame($estados['Nueva']->id, $op->estado_id);
        $this->assertSame(now()->toDateString(), $op->fecha_ingreso->toDateString());

        // Sin gatillo de cotización para estado "Nueva"
        $this->assertNull($response->json('cotizacion_id'));
        $this->assertSame(0, Cotizacion::count());
    }

    public function test_user_id_es_requerido(): void
    {
        // dev-diego: user_id pasó de nullable → required en POST /oportunidades
        $estados = $this->seedEstadosOportunidad();
        $this->actingAsAdmin();

        $this->postJson('/api/oportunidades', [
            'estado_id' => $estados['Nueva']->id,
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['user_id']);
    }

    public function test_crea_contacto_manual_si_se_envian_datos_de_contacto(): void
    {
        $estados = $this->seedEstadosOportunidad();
        // user_id en oportunidades exige rol ejecutivo|superadmin
        $admin = $this->actingAsUser(['role' => 'superadmin']);
        $cliente = Cliente::factory()->create();

        $this->postJson('/api/oportunidades', [
            'estado_id' => $estados['Nueva']->id,
            'user_id' => $admin->id,
            'cliente_id' => $cliente->id,
            'nombre_contacto' => 'Pedro Capitán',
            'numero_contacto' => '+56912345678',
            'email_contacto' => 'pedro@bomberos.cl',
            'cargo_contacto' => 'Capitán',
        ])->assertCreated();

        $this->assertDatabaseHas('contactos', [
            'cliente_id' => $cliente->id,
            'nombre' => 'Pedro Capitán',
            'telefono' => '+56912345678',
            'email' => 'pedro@bomberos.cl',
            'cargo' => 'Capitán',
        ]);

        $op = Oportunidad::first();
        $this->assertNotNull($op->contacto_id);
    }

    public function test_gatilla_creacion_de_cotizacion_si_estado_es_aprobada_cotizacion(): void
    {
        $estadosOp = $this->seedEstadosOportunidad();
        $this->seedEstadosCotizacion();
        // user_id en oportunidades exige rol ejecutivo|superadmin
        $admin = $this->actingAsUser(['role' => 'superadmin']);
        $cliente = Cliente::factory()->create();

        $response = $this->postJson('/api/oportunidades', [
            'estado_id' => $estadosOp['Aprobada - Cotización']->id,
            'user_id' => $admin->id,
            'cliente_id' => $cliente->id,
        ])->assertCreated();

        $cotizacionId = $response->json('cotizacion_id');
        $this->assertNotNull($cotizacionId);

        $cot = Cotizacion::find($cotizacionId);
        $this->assertSame($cliente->id, $cot->cliente_id);
        $this->assertSame('oportunidades', $cot->origen);
    }
}
