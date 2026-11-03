<?php

namespace Tests\Feature\Prospeccion;

use App\Models\Cliente;
use App\Models\Contacto;
use App\Models\ProspeccionContacto;
use App\Models\ProspeccionTipo;
use Tests\TestCase;

class ProspeccionMetricasTest extends TestCase
{
    private function contacto(): Contacto
    {
        $cliente = Cliente::factory()->create();
        return Contacto::factory()->create(['cliente_id' => $cliente->id]);
    }

    // ── metricas ───────────────────────────────────────────────────────────

    public function test_metricas_requiere_autenticacion(): void
    {
        $this->getJson('/api/prospeccion/metricas')->assertUnauthorized();
    }

    public function test_metricas_retorna_estructura_correcta(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/prospeccion/metricas')
            ->assertOk()
            ->assertJsonStructure(['llamadas', 'tasa_contacto', 'calificados', 'pendientes', 'mes']);
    }

    public function test_metricas_cuenta_llamadas_del_mes_actual(): void
    {
        $user = $this->actingAsAdmin();

        $contacto1 = $this->contacto();
        $contacto2 = $this->contacto();

        $tipoNoContesta  = ProspeccionTipo::where('slug', 'no_contesta')->first();
        $tipoLlamarLuego = ProspeccionTipo::where('slug', 'llamar_luego')->first();

        // 2 interacciones este mes
        ProspeccionContacto::factory()->create([
            'contacto_id' => $contacto1->id,
            'user_id'     => $user->id,
            'tipo_id'     => $tipoNoContesta->id,
            'created_at'  => now(),
        ]);
        ProspeccionContacto::factory()->create([
            'contacto_id' => $contacto2->id,
            'user_id'     => $user->id,
            'tipo_id'     => $tipoLlamarLuego->id,
            'created_at'  => now(),
        ]);

        $body = $this->getJson('/api/prospeccion/metricas')->assertOk()->json();

        $this->assertGreaterThanOrEqual(2, $body['llamadas']);
    }

    public function test_metricas_calcula_tasa_de_contacto(): void
    {
        $user = $this->actingAsAdmin();
        $mes  = now()->format('Y-m');

        // Contacto efectivo = hubo conversación (seguimiento acordado). No contestar
        // o reagendar sin hablar ("llamar luego") no lo son: 1 de 3.
        foreach (['no_contesta', 'llamar_luego', 'agendar_seguimiento'] as $slug) {
            ProspeccionContacto::factory()->create([
                'contacto_id' => $this->contacto()->id,
                'user_id'     => $user->id,
                'tipo_id'     => ProspeccionTipo::where('slug', $slug)->value('id'),
                'created_at'  => now(),
            ]);
        }

        $body = $this->getJson("/api/prospeccion/metricas?mes={$mes}")->assertOk()->json();
        $this->assertSame(33.3, (float) $body['tasa_contacto']);
    }

    // ── cierres diarios ────────────────────────────────────────────────────

    public function test_cierres_diarios_requiere_autenticacion(): void
    {
        $this->getJson('/api/prospeccion/cierres-diarios')->assertUnauthorized();
    }

    public function test_cierres_diarios_retorna_datos_agrupados_por_fecha(): void
    {
        $user = $this->actingAsAdmin();
        $tipo = ProspeccionTipo::where('slug', 'llamar_luego')->first();

        ProspeccionContacto::factory()->count(3)->create([
            'contacto_id' => $this->contacto()->id,
            'user_id'     => $user->id,
            'tipo_id'     => $tipo->id,
            'created_at'  => now(),
        ]);

        $mes      = now()->format('Y-m');
        $response = $this->getJson("/api/prospeccion/cierres-diarios?mes={$mes}")->assertOk();
        $body     = $response->json();

        $this->assertNotEmpty($body);
        $hoy = collect($body)->firstWhere('fecha', now()->toDateString());
        $this->assertNotNull($hoy);
        $this->assertGreaterThanOrEqual(3, (int) $hoy['llamadas_total']);
    }
}
