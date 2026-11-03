<?php

namespace Tests\Feature\Comercial;

use App\Models\Cliente;
use App\Models\Contacto;
use App\Models\Cotizacion;
use App\Models\Oportunidad;
use Tests\TestCase;

class OportunidadIndexTest extends TestCase
{
    public function test_requiere_autenticacion(): void
    {
        $this->getJson('/api/oportunidades')->assertUnauthorized();
    }

    public function test_lista_oportunidades_paginadas(): void
    {
        $this->seedEstadosOportunidad();
        $admin = $this->actingAsAdmin();
        Oportunidad::factory()->count(3)->create(['user_id' => $admin->id]);

        $this->getJson('/api/oportunidades?per_page=10')
            ->assertOk()
            ->assertJsonPath('total', 3)
            ->assertJsonPath('per_page', 10);
    }

    public function test_filtra_por_estado_id(): void
    {
        $estados = $this->seedEstadosOportunidad();
        $admin = $this->actingAsAdmin();

        Oportunidad::factory()->withEstado($estados['Nueva'])->count(2)->create(['user_id' => $admin->id]);
        Oportunidad::factory()->withEstado($estados['Perdida'])->create(['user_id' => $admin->id]);

        $this->getJson('/api/oportunidades?estado_id=' . $estados['Perdida']->id)
            ->assertOk()
            ->assertJsonPath('total', 1);
    }

    public function test_exclude_has_quotation_filtra_las_ya_cotizadas(): void
    {
        $estadosOp = $this->seedEstadosOportunidad();
        $estadosCot = $this->seedEstadosCotizacion();
        $admin = $this->actingAsAdmin();

        $opSinCot = Oportunidad::factory()->create(['user_id' => $admin->id]);
        $opConCot = Oportunidad::factory()->create(['user_id' => $admin->id]);

        Cotizacion::factory()->forUser($admin)->create([
            'oportunidad_id' => $opConCot->id,
            'estado_id' => $estadosCot['Cotización Enviada']->id,
        ]);

        $response = $this->getJson('/api/oportunidades?exclude_has_quotation=1')
            ->assertOk()
            ->assertJsonPath('total', 1);

        $this->assertSame($opSinCot->id, $response->json('data.0.id'));
    }

    public function test_busqueda_q_por_nombre_de_contacto_o_empresa(): void
    {
        $this->seedEstadosOportunidad();
        $admin = $this->actingAsAdmin();

        $cliente = Cliente::factory()->create(['nombre_empresa' => 'Bomberos Antofagasta']);
        Oportunidad::factory()->forCliente($cliente)->create(['user_id' => $admin->id]);

        $contacto = Contacto::factory()->create(['nombre' => 'Juan Comandante']);
        Oportunidad::factory()->create([
            'cliente_id' => $contacto->cliente_id,
            'contacto_id' => $contacto->id,
            'user_id' => $admin->id,
        ]);

        $this->getJson('/api/oportunidades?q=Antofagasta')
            ->assertOk()
            ->assertJsonPath('total', 1);

        $this->getJson('/api/oportunidades?q=Comandante')
            ->assertOk()
            ->assertJsonPath('total', 1);
    }
}
