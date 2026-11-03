<?php

namespace Tests\Feature\Comercial;

use App\Models\Oportunidad;
use App\Models\OportunidadGestion;
use Tests\TestCase;

class OportunidadGestionTest extends TestCase
{
    public function test_crea_gestion(): void
    {
        $this->seedEstadosOportunidad();
        $admin = $this->actingAsAdmin();
        $op = Oportunidad::factory()->create(['user_id' => $admin->id]);

        $this->postJson("/api/oportunidades/{$op->id}/gestiones", [
            'tipo_contacto' => 'llamada',
            'glosa' => 'Cliente solicitó cotización',
            'fecha_gestion' => now()->toDateTimeString(),
        ])->assertOk()->assertJsonPath('message', 'Gestión registrada correctamente');

        $this->assertDatabaseHas('oportunidad_gestiones', [
            'oportunidad_id' => $op->id,
            'user_id' => $admin->id,
            'tipo_contacto' => 'llamada',
            'glosa' => 'Cliente solicitó cotización',
        ]);
    }

    public function test_valida_campos_requeridos(): void
    {
        $this->seedEstadosOportunidad();
        $admin = $this->actingAsAdmin();
        $op = Oportunidad::factory()->create(['user_id' => $admin->id]);

        $this->postJson("/api/oportunidades/{$op->id}/gestiones", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tipo_contacto', 'glosa', 'fecha_gestion']);
    }

    public function test_lista_gestiones_de_la_oportunidad_ordenadas_desc(): void
    {
        $this->seedEstadosOportunidad();
        $admin = $this->actingAsAdmin();
        $op = Oportunidad::factory()->create(['user_id' => $admin->id]);

        OportunidadGestion::create([
            'oportunidad_id' => $op->id,
            'user_id' => $admin->id,
            'tipo_contacto' => 'email',
            'glosa' => 'Vieja',
            'fecha_gestion' => now()->subDays(2),
        ]);
        OportunidadGestion::create([
            'oportunidad_id' => $op->id,
            'user_id' => $admin->id,
            'tipo_contacto' => 'llamada',
            'glosa' => 'Nueva',
            'fecha_gestion' => now(),
        ]);

        $response = $this->getJson("/api/oportunidades/{$op->id}/gestiones")->assertOk();

        $data = $response->json();
        $this->assertCount(2, $data);
        $this->assertSame('Nueva', $data[0]['glosa']);
        $this->assertSame('Vieja', $data[1]['glosa']);
    }
}
