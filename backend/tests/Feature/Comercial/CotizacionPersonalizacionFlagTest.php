<?php

namespace Tests\Feature\Comercial;

use App\Models\Cotizacion;
use Tests\TestCase;

class CotizacionPersonalizacionFlagTest extends TestCase
{
    public function test_marca_personalizacion_completada(): void
    {
        $this->seedEstadosCotizacion();
        $admin = $this->actingAsUser(['role' => 'superadmin']);
        $cot = Cotizacion::factory()->forUser($admin)->withEstado(\App\Models\Estado::where('nombre', 'Aprobada por Cliente')->firstOrFail())->create([
            'personalizacion_completada' => false,
            'estado_personalizacion' => null,
        ]);

        $this->patchJson("/api/cotizaciones/{$cot->id}/personalizacion-completada")
            ->assertOk()
            ->assertJsonPath('ok', true);

        $cot->refresh();
        $this->assertTrue((bool) $cot->personalizacion_completada);
        $this->assertSame('configured', $cot->estado_personalizacion);
    }

    public function test_send_to_personalizacion_falla_si_no_esta_configured(): void
    {
        $this->seedEstadosCotizacion();
        $admin = $this->actingAsUser(['role' => 'superadmin']);
        $cot = Cotizacion::factory()->forUser($admin)->withEstado(\App\Models\Estado::where('nombre', 'Aprobada por Cliente')->firstOrFail())->create([
            'estado_personalizacion' => null,
        ]);

        $this->postJson("/api/cotizaciones/{$cot->id}/send-personalizacion")
            ->assertStatus(422)
            ->assertJsonPath('message', 'La cotización no está configurada o ya fue enviada.');
    }
}
