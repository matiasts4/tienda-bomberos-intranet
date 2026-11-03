<?php

namespace Tests\Feature\Prospeccion;

use App\Models\Cliente;
use App\Models\Contacto;
use App\Models\Estado;
use App\Models\Oportunidad;
use App\Models\OportunidadGestion;
use App\Models\ProspeccionContacto;
use App\Models\User;
use Tests\TestCase;

class ProspeccionCalificarTest extends TestCase
{
    private function contacto(): Contacto
    {
        $cliente = Cliente::factory()->create();
        return Contacto::factory()->create(['cliente_id' => $cliente->id]);
    }

    private function ejecutivo(): User
    {
        return User::factory()->create(['role' => 'ejecutivo']);
    }

    // ── auth ───────────────────────────────────────────────────────────────

    public function test_calificar_requiere_autenticacion(): void
    {
        $contacto = $this->contacto();
        $this->postJson("/api/prospeccion/contactos/{$contacto->id}/calificar", [])
            ->assertUnauthorized();
    }

    // ── validación ────────────────────────────────────────────────────────

    public function test_calificar_valida_campos_requeridos(): void
    {
        $this->actingAsAdmin();
        $contacto = $this->contacto();

        $this->postJson("/api/prospeccion/contactos/{$contacto->id}/calificar", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['notas', 'user_id']);
    }

    public function test_calificar_valida_que_user_id_exista(): void
    {
        $this->actingAsAdmin();
        $contacto = $this->contacto();

        $this->postJson("/api/prospeccion/contactos/{$contacto->id}/calificar", [
            'notas'   => 'Cliente muy interesado.',
            'user_id' => 99999,
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['user_id']);
    }

    // ── calificar exitoso ─────────────────────────────────────────────────

    public function test_calificar_crea_oportunidad_gestion_e_interaccion(): void
    {
        $this->actingAsAdmin();
        $contacto  = $this->contacto();
        $ejecutivo = $this->ejecutivo();

        // Estado 'Nuevo' requerido por el controller
        $this->seedEstadosOportunidad();

        $response = $this->postJson("/api/prospeccion/contactos/{$contacto->id}/calificar", [
            'notas'   => 'Muy interesado en parkas.',
            'user_id' => $ejecutivo->id,
            'canal'   => 'Teléfono',
        ])->assertCreated()
          ->assertJsonPath('ok', true);

        $this->assertNotNull($response->json('oportunidad_id'));

        // Oportunidad creada
        $this->assertDatabaseHas('oportunidades', [
            'contacto_id' => $contacto->id,
            'user_id'     => $ejecutivo->id,
        ]);

        // Gestión creada
        $oportunidadId = $response->json('oportunidad_id');
        $this->assertDatabaseHas('oportunidad_gestiones', [
            'oportunidad_id' => $oportunidadId,
            'tipo_contacto'  => 'llamada',
        ]);

        // Interacción de prospección creada
        $this->assertDatabaseHas('prospeccion_contactos', [
            'contacto_id' => $contacto->id,
        ]);
    }

    public function test_calificar_usa_canal_default_telefono(): void
    {
        $this->actingAsAdmin();
        $contacto  = $this->contacto();
        $ejecutivo = $this->ejecutivo();
        $this->seedEstadosOportunidad();

        $this->postJson("/api/prospeccion/contactos/{$contacto->id}/calificar", [
            'notas'   => 'Interesado.',
            'user_id' => $ejecutivo->id,
            // Sin canal → default 'Teléfono'
        ])->assertCreated();

        $this->assertDatabaseHas('oportunidades', ['canal' => 'Teléfono']);
    }

    public function test_calificar_falla_si_no_hay_estados_oportunidad(): void
    {
        $this->actingAsAdmin();
        $contacto  = $this->contacto();
        $ejecutivo = $this->ejecutivo();
        // No se seedean estados

        $this->postJson("/api/prospeccion/contactos/{$contacto->id}/calificar", [
            'notas'   => 'Interesado.',
            'user_id' => $ejecutivo->id,
        ])->assertStatus(422)
          ->assertJsonPath('error', 'No hay estados de oportunidad configurados.');
    }
}
