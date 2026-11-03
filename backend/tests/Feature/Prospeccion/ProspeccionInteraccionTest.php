<?php

namespace Tests\Feature\Prospeccion;

use App\Models\Cliente;
use App\Models\Contacto;
use App\Models\Evento;
use App\Models\ProspeccionContacto;
use App\Models\ProspeccionTipo;
use Tests\TestCase;

class ProspeccionInteraccionTest extends TestCase
{
    private function contacto(): Contacto
    {
        $cliente = Cliente::factory()->create();
        return Contacto::factory()->create(['cliente_id' => $cliente->id]);
    }

    // ── auth ───────────────────────────────────────────────────────────────

    public function test_interaccion_requiere_autenticacion(): void
    {
        $contacto = $this->contacto();
        $this->postJson("/api/prospeccion/contactos/{$contacto->id}/interaccion", [])
            ->assertUnauthorized();
    }

    // ── validación ────────────────────────────────────────────────────────

    public function test_interaccion_valida_tipo_requerido(): void
    {
        $this->actingAsAdmin();
        $contacto = $this->contacto();

        $this->postJson("/api/prospeccion/contactos/{$contacto->id}/interaccion", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tipo']);
    }

    public function test_interaccion_valida_que_tipo_exista_en_db(): void
    {
        $this->actingAsAdmin();
        $contacto = $this->contacto();

        $this->postJson("/api/prospeccion/contactos/{$contacto->id}/interaccion", [
            'tipo' => 'tipo_inexistente',
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['tipo']);
    }

    // ── crear interacción ─────────────────────────────────────────────────

    public function test_interaccion_crea_registro_y_retorna_201(): void
    {
        $this->actingAsAdmin();
        $contacto = $this->contacto();

        $response = $this->postJson("/api/prospeccion/contactos/{$contacto->id}/interaccion", [
            'tipo'  => 'no_contesta',
            'notas' => 'Llamé dos veces sin respuesta.',
        ])->assertCreated()
          ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('prospeccion_contactos', [
            'contacto_id' => $contacto->id,
            'notas'       => 'Llamé dos veces sin respuesta.',
        ]);

        $tipoId = ProspeccionTipo::where('slug', 'no_contesta')->value('id');
        $this->assertDatabaseHas('prospeccion_contactos', [
            'contacto_id' => $contacto->id,
            'tipo_id'     => $tipoId,
        ]);
    }

    // ── evento con proxima_llamada ─────────────────────────────────────────

    public function test_interaccion_con_proxima_llamada_crea_evento(): void
    {
        $this->actingAsAdmin();
        $contacto       = $this->contacto();
        $proximaLlamada = now()->addDays(5)->toDateString();

        $this->postJson("/api/prospeccion/contactos/{$contacto->id}/interaccion", [
            'tipo'            => 'llamar_luego',
            'notas'           => 'Prefiere que llame el jueves.',
            'proxima_llamada' => $proximaLlamada,
        ])->assertCreated();

        // La proxima_llamada se persiste en prospeccion_contactos y el calendario
        // la renderiza directamente vía EventoController (ya no se crea un Evento
        // duplicado por diseño).
        $interaccion = \App\Models\ProspeccionContacto::where('contacto_id', $contacto->id)->first();
        $this->assertNotNull($interaccion);
        $this->assertSame($proximaLlamada, $interaccion->proxima_llamada->toDateString());
    }

    public function test_interaccion_sin_proxima_llamada_no_crea_evento(): void
    {
        $this->actingAsAdmin();
        $contacto = $this->contacto();

        $this->postJson("/api/prospeccion/contactos/{$contacto->id}/interaccion", [
            'tipo'  => 'buzon',
            'notas' => 'Dejé mensaje.',
        ])->assertCreated();

        $this->assertSame(0, Evento::count());
    }

    // ── historial ─────────────────────────────────────────────────────────

    public function test_historial_requiere_autenticacion(): void
    {
        $contacto = $this->contacto();
        $this->getJson("/api/prospeccion/contactos/{$contacto->id}/historial")
            ->assertUnauthorized();
    }

    public function test_historial_retorna_interacciones_del_contacto(): void
    {
        $this->actingAsAdmin();
        $contacto = $this->contacto();
        $otro     = $this->contacto();

        ProspeccionContacto::factory()->count(3)->create(['contacto_id' => $contacto->id]);
        ProspeccionContacto::factory()->create(['contacto_id' => $otro->id]);

        $response = $this->getJson("/api/prospeccion/contactos/{$contacto->id}/historial")
            ->assertOk();

        $this->assertCount(3, $response->json());
        foreach ($response->json() as $item) {
            $this->assertArrayHasKey('tipo', $item);
            $this->assertArrayHasKey('notas', $item);
            $this->assertArrayHasKey('created_at', $item);
        }
    }
}
