<?php

namespace Tests\Feature\Comercial;

use App\Models\Cotizacion;
use Tests\TestCase;

class CotizacionGestionTest extends TestCase
{
    public function test_crea_gestion_y_actualiza_fecha_vencimiento(): void
    {
        $this->seedEstadosCotizacion();
        $admin = $this->actingAsAdmin();
        $cot = Cotizacion::factory()->forUser($admin)->create([
            'fecha_vencimiento' => '2026-05-01',
        ]);

        $this->postJson("/api/cotizaciones/{$cot->id}/gestion", [
            'glosa' => 'Llamada al cliente, pidió extensión',
            'fecha_vencimiento_nueva' => '2026-06-15',
        ])->assertOk();

        $cot->refresh();
        $this->assertSame('2026-06-15', $cot->fecha_vencimiento->toDateString());

        $this->assertDatabaseHas('cotizacion_gestiones', [
            'cotizacion_id' => $cot->id,
            'glosa' => 'Llamada al cliente, pidió extensión',
            'fecha_vencimiento_nueva' => '2026-06-15 00:00:00',
        ]);
    }

    public function test_requiere_fecha_vencimiento_nueva(): void
    {
        $this->seedEstadosCotizacion();
        $admin = $this->actingAsAdmin();
        $cot = Cotizacion::factory()->forUser($admin)->create();

        $this->postJson("/api/cotizaciones/{$cot->id}/gestion", [
            'glosa' => 'Sin fecha',
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['fecha_vencimiento_nueva']);
    }
}
