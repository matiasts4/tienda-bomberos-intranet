<?php

namespace Tests\Feature\Comercial;

use App\Models\Cotizacion;
use App\Support\NoVigente;
use Tests\TestCase;

class CotizacionAnularTest extends TestCase
{
    public function test_anula_cotizacion_y_la_lleva_a_no_vigente_con_motivo_anulada(): void
    {
        $estados = $this->seedEstadosCotizacion();
        $admin = $this->actingAsAdmin();
        $cot = Cotizacion::factory()->forUser($admin)->create([
            'estado_id' => $estados['Cotización Enviada']->id,
        ]);

        $this->postJson("/api/cotizaciones/{$cot->id}/anular", [
            'motivo' => 'Cotización duplicada',
        ])->assertOk()->assertJsonPath('ok', true);

        $cot->refresh();
        $this->assertSame(NoVigente::estado()->id, $cot->estado_id);
        $this->assertSame('anulada', $cot->motivo_no_vigente);
        $this->assertSame('Cotización duplicada', $cot->observacion_no_vigente);
        $this->assertNotNull($cot->fecha_no_vigente);
        $this->assertSame($admin->id, $cot->no_vigente_por_id);
    }

    public function test_anular_registra_historial_con_el_motivo(): void
    {
        $estados = $this->seedEstadosCotizacion();
        $admin = $this->actingAsAdmin();
        $cot = Cotizacion::factory()->forUser($admin)->create([
            'estado_id' => $estados['Cotización Enviada']->id,
        ]);

        $this->postJson("/api/cotizaciones/{$cot->id}/anular", [
            'motivo' => 'Presupuesto excedido',
        ])->assertOk();

        $this->assertDatabaseHas('historial_etapas', [
            'model_type' => Cotizacion::class,
            'model_id' => $cot->id,
            'estado_anterior_id' => $estados['Cotización Enviada']->id,
            'estado_nuevo_id' => NoVigente::estado()->id,
            'observaciones' => 'Anulada: Presupuesto excedido',
        ]);
    }

    public function test_requiere_motivo(): void
    {
        $this->seedEstadosCotizacion();
        $admin = $this->actingAsAdmin();
        $cot = Cotizacion::factory()->forUser($admin)->create();

        $this->postJson("/api/cotizaciones/{$cot->id}/anular", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['motivo']);
    }

    public function test_no_anula_una_cotizacion_que_ya_no_esta_vigente(): void
    {
        $estados = $this->seedEstadosCotizacion();
        $admin = $this->actingAsAdmin();
        // Una cotización con finalización administrativa no se puede pisar desde "Anular".
        $cot = Cotizacion::factory()->forUser($admin)->withEstado(NoVigente::estado())
            ->create(['motivo_no_vigente' => 'finalizacion_adm', 'observacion_no_vigente' => 'Cierre de año']);
        $legacy = Cotizacion::factory()->forUser($admin)->withEstado($estados['Anulado'])->create();

        foreach ([$cot, $legacy] as $c) {
            $this->postJson("/api/cotizaciones/{$c->id}/anular", ['motivo' => 'Error de tipado'])
                ->assertStatus(422)
                ->assertJsonPath('message', 'La cotización ya no está vigente.');
        }
        $this->assertSame('finalizacion_adm', $cot->fresh()->motivo_no_vigente);
    }

    public function test_falla_si_no_existe_estado_no_vigente(): void
    {
        NoVigente::estado()->delete();
        $this->seedEstadosCotizacion();
        $admin = $this->actingAsAdmin();
        $cot = Cotizacion::factory()->forUser($admin)->create();

        $this->postJson("/api/cotizaciones/{$cot->id}/anular", [
            'motivo' => 'cualquiera',
        ])->assertStatus(422)
          ->assertJsonPath('message', 'El estado "No Vigente" no existe en el sistema.');
    }
}
