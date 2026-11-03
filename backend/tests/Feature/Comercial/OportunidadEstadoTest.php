<?php

namespace Tests\Feature\Comercial;

use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\Oportunidad;
use Tests\TestCase;

class OportunidadEstadoTest extends TestCase
{
    public function test_cambia_estado_y_registra_historial(): void
    {
        $estados = $this->seedEstadosOportunidad();
        $admin = $this->actingAsAdmin();
        $op = Oportunidad::factory()->withEstado($estados['Nueva'])->create(['user_id' => $admin->id]);

        $this->patchJson("/api/oportunidades/{$op->id}/estado", [
            'estado_id' => $estados['En Contacto']->id,
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertSame($estados['En Contacto']->id, $op->fresh()->estado_id);
        $this->assertDatabaseHas('historial_etapas', [
            'model_type' => Oportunidad::class,
            'model_id' => $op->id,
            'estado_anterior_id' => $estados['Nueva']->id,
            'estado_nuevo_id' => $estados['En Contacto']->id,
        ]);
    }

    public function test_pasar_a_aprobada_cotizacion_genera_cotizacion(): void
    {
        $estadosOp = $this->seedEstadosOportunidad();
        $this->seedEstadosCotizacion();
        $admin = $this->actingAsAdmin();
        $cliente = Cliente::factory()->create();
        $op = Oportunidad::factory()->forCliente($cliente)->withEstado($estadosOp['Nueva'])->create(['user_id' => $admin->id]);

        $response = $this->patchJson("/api/oportunidades/{$op->id}/estado", [
            'estado_id' => $estadosOp['Aprobada - Cotización']->id,
        ])->assertOk();

        $cotId = $response->json('cotizacion_id');
        $this->assertNotNull($cotId);
        $this->assertDatabaseHas('cotizaciones', [
            'id' => $cotId,
            'oportunidad_id' => $op->id,
            'cliente_id' => $cliente->id,
            'origen' => 'oportunidades',
        ]);
    }

    public function test_es_idempotente_no_crea_segunda_cotizacion(): void
    {
        $estadosOp = $this->seedEstadosOportunidad();
        $this->seedEstadosCotizacion();
        $admin = $this->actingAsAdmin();
        $cliente = Cliente::factory()->create();
        $op = Oportunidad::factory()->forCliente($cliente)->withEstado($estadosOp['Nueva'])->create(['user_id' => $admin->id]);

        $this->patchJson("/api/oportunidades/{$op->id}/estado", [
            'estado_id' => $estadosOp['Aprobada - Cotización']->id,
        ])->assertOk();

        $this->patchJson("/api/oportunidades/{$op->id}/estado", [
            'estado_id' => $estadosOp['Aprobada - Cotización']->id,
        ])->assertOk();

        $this->assertSame(1, Cotizacion::where('oportunidad_id', $op->id)->count());
    }
}
