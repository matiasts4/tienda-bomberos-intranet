<?php

namespace Tests\Feature\Comercial;

use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\Oportunidad;
use App\Models\User;
use Tests\TestCase;

class TraspasoResponsableTest extends TestCase
{
    public function test_oportunidad_creada_guarda_creador_y_responsable(): void
    {
        $estados = $this->seedEstadosOportunidad();
        $creador = $this->actingAsEjecutivo();
        $otro = User::factory()->create(['role' => 'ejecutivo']);
        $cliente = Cliente::factory()->create();

        $this->postJson('/api/oportunidades', [
            'cliente_id' => $cliente->id,
            'user_id' => $otro->id,
            'estado_id' => $estados['Nueva']->id,
        ])->assertCreated()
            ->assertJsonPath('creado_por_id', $creador->id)
            ->assertJsonPath('user_id', $otro->id);
    }

    public function test_cotizacion_nueva_parte_con_mismo_creador_y_responsable(): void
    {
        $this->seedEstadosCotizacion();
        $creador = $this->actingAsEjecutivo();
        $cliente = Cliente::factory()->create();

        $this->postJson('/api/cotizaciones', ['cliente_id' => $cliente->id, 'nombre' => 'Uniformes'])
            ->assertCreated()
            ->assertJsonPath('creado_por_id', $creador->id)
            ->assertJsonPath('user_id', $creador->id);
    }

    public function test_responsable_traspasa_oportunidad_y_queda_historial(): void
    {
        $this->seedEstadosOportunidad();
        $creador = $this->actingAsEjecutivo();
        $destino = User::factory()->create(['role' => 'ejecutivo']);
        $op = Oportunidad::factory()->create(['user_id' => $creador->id, 'creado_por_id' => $creador->id]);

        $this->postJson("/api/oportunidades/{$op->id}/traspasar", [
            'user_id' => $destino->id,
            'motivo' => 'Vacaciones',
        ])->assertOk()
            ->assertJsonPath('user_id', $destino->id)
            ->assertJsonPath('creado_por_id', $creador->id)
            ->assertJsonPath('traspasos.0.motivo', 'Vacaciones')
            ->assertJsonPath('traspasos.0.de.id', $creador->id)
            ->assertJsonPath('traspasos.0.a.id', $destino->id)
            ->assertJsonPath('traspasos.0.por.id', $creador->id);

        $this->assertDatabaseHas('traspasos_responsable', [
            'model_type' => Oportunidad::class,
            'model_id' => $op->id,
            'de_user_id' => $creador->id,
            'a_user_id' => $destino->id,
        ]);
    }

    public function test_cotizacion_se_puede_traspasar_varias_veces_manteniendo_historia(): void
    {
        $creador = $this->actingAsEjecutivo();
        $b = User::factory()->create(['role' => 'ejecutivo']);
        $c = User::factory()->create(['role' => 'ejecutivo']);
        $cot = Cotizacion::factory()->forUser($creador)->create(['creado_por_id' => $creador->id]);

        $this->postJson("/api/cotizaciones/{$cot->id}/traspasar", ['user_id' => $b->id])->assertOk();

        // Ahora el responsable es B: el creador original ya no puede traspasarla.
        $this->postJson("/api/cotizaciones/{$cot->id}/traspasar", ['user_id' => $c->id])->assertForbidden();

        \Laravel\Sanctum\Sanctum::actingAs($b);
        $this->postJson("/api/cotizaciones/{$cot->id}/traspasar", ['user_id' => $c->id])
            ->assertOk()
            ->assertJsonPath('user_id', $c->id)
            ->assertJsonPath('creado_por_id', $creador->id)
            ->assertJsonCount(2, 'traspasos');

        $this->assertSame(2, $cot->traspasos()->count());
    }

    public function test_admin_puede_traspasar_tarjeta_ajena(): void
    {
        $dueno = User::factory()->create(['role' => 'ejecutivo']);
        $destino = User::factory()->create(['role' => 'ejecutivo']);
        $cot = Cotizacion::factory()->forUser($dueno)->create(['creado_por_id' => $dueno->id]);
        $this->actingAsAdmin();

        $this->postJson("/api/cotizaciones/{$cot->id}/traspasar", ['user_id' => $destino->id])->assertOk();
        $this->assertSame($destino->id, $cot->fresh()->user_id);
    }

    public function test_tercero_sin_permiso_no_puede_traspasar(): void
    {
        $dueno = User::factory()->create(['role' => 'ejecutivo']);
        $destino = User::factory()->create(['role' => 'ejecutivo']);
        $op = Oportunidad::factory()->create(['user_id' => $dueno->id]);
        $this->actingAsEjecutivo();

        $this->postJson("/api/oportunidades/{$op->id}/traspasar", ['user_id' => $destino->id])->assertForbidden();
        $this->assertSame($dueno->id, $op->fresh()->user_id);
    }

    public function test_no_se_traspasa_a_no_ejecutivo_ni_al_mismo_responsable(): void
    {
        $yo = $this->actingAsEjecutivo();
        $fabrica = User::factory()->create(['role' => 'fabrica']);
        $cot = Cotizacion::factory()->forUser($yo)->create();

        $this->postJson("/api/cotizaciones/{$cot->id}/traspasar", ['user_id' => $fabrica->id])->assertUnprocessable();
        $this->postJson("/api/cotizaciones/{$cot->id}/traspasar", ['user_id' => $yo->id])->assertUnprocessable();
        $this->postJson("/api/cotizaciones/{$cot->id}/traspasar", [])->assertUnprocessable();
        $this->assertSame(0, $cot->traspasos()->count());
    }

    public function test_cotizar_un_lead_no_le_quita_el_responsable_y_la_cotizacion_lo_hereda(): void
    {
        $estadosOp = $this->seedEstadosOportunidad();
        $this->seedEstadosCotizacion();
        $responsable = User::factory()->create(['role' => 'ejecutivo']);
        $admin = $this->actingAsAdmin();
        $op = Oportunidad::factory()->withEstado($estadosOp['Nueva'])
            ->create(['user_id' => $responsable->id, 'creado_por_id' => $responsable->id]);

        $cotId = $this->patchJson("/api/oportunidades/{$op->id}/estado", [
            'estado_id' => $estadosOp['Aprobada - Cotización']->id,
        ])->assertOk()->json('cotizacion_id');

        $this->assertSame($responsable->id, $op->fresh()->user_id);
        $cot = Cotizacion::findOrFail($cotId);
        $this->assertSame($responsable->id, $cot->user_id);
        $this->assertSame($admin->id, $cot->creado_por_id);
    }

    public function test_editar_oportunidad_con_otro_user_id_registra_traspaso(): void
    {
        $this->seedEstadosOportunidad();
        $yo = $this->actingAsEjecutivo();
        $destino = User::factory()->create(['role' => 'ejecutivo']);
        $op = Oportunidad::factory()->create(['user_id' => $yo->id, 'creado_por_id' => $yo->id]);

        $this->patchJson("/api/oportunidades/{$op->id}", ['user_id' => $destino->id])->assertOk();

        $this->assertSame($destino->id, $op->fresh()->user_id);
        $this->assertSame(1, $op->traspasos()->count());
    }

    public function test_listados_incluyen_creador_y_responsable(): void
    {
        $this->seedEstadosOportunidad();
        $yo = $this->actingAsEjecutivo();
        Oportunidad::factory()->create(['user_id' => $yo->id, 'creado_por_id' => $yo->id]);
        Cotizacion::factory()->forUser($yo)->create(['creado_por_id' => $yo->id]);

        $this->getJson('/api/oportunidades')->assertOk()
            ->assertJsonPath('data.0.creador.id', $yo->id)
            ->assertJsonPath('data.0.ejecutivo.id', $yo->id);
        $this->getJson('/api/cotizaciones')->assertOk()
            ->assertJsonPath('data.0.creador.id', $yo->id)
            ->assertJsonPath('data.0.ejecutivo.id', $yo->id);
    }
}
