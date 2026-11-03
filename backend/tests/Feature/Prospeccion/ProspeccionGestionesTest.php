<?php

namespace Tests\Feature\Prospeccion;

use App\Models\Cliente;
use App\Models\Contacto;
use App\Models\ProspeccionContacto;
use App\Models\ProspeccionTipo;
use App\Models\User;
use Tests\TestCase;

class ProspeccionGestionesTest extends TestCase
{
    private function contacto(): Contacto
    {
        $cliente = Cliente::factory()->create();
        return Contacto::factory()->create(['cliente_id' => $cliente->id]);
    }

    // ── tipos ──────────────────────────────────────────────────────────────

    public function test_tipos_requiere_autenticacion(): void
    {
        $this->getJson('/api/prospeccion/tipos')->assertUnauthorized();
    }

    public function test_tipos_retorna_los_cinco_tipos_seeded(): void
    {
        $this->actingAsAdmin();

        $response = $this->getJson('/api/prospeccion/tipos')->assertOk();
        $slugs    = array_column($response->json(), 'slug');

        $this->assertContains('no_contesta',  $slugs);
        $this->assertContains('buzon',        $slugs);
        $this->assertContains('llamar_luego', $slugs);
        $this->assertContains('no_califica',  $slugs);
        $this->assertContains('calificado',   $slugs);
        // Resultados de una conversación y datos malos (migración 2026_10_21).
        $this->assertContains('agendar_seguimiento', $slugs);
        $this->assertContains('derivado',            $slugs);
        $this->assertContains('numero_equivocado',   $slugs);
        $this->assertCount(8, $slugs);
    }

    // ── gestiones ─────────────────────────────────────────────────────────

    public function test_gestiones_requiere_autenticacion(): void
    {
        $this->getJson('/api/prospeccion/gestiones')->assertUnauthorized();
    }

    public function test_gestiones_retorna_historial_paginado(): void
    {
        $user = $this->actingAsAdmin();
        $tipo = ProspeccionTipo::where('slug', 'no_contesta')->first();

        ProspeccionContacto::factory()->count(5)->create([
            'contacto_id' => $this->contacto()->id,
            'user_id'     => $user->id,
            'tipo_id'     => $tipo->id,
        ]);

        $response = $this->getJson('/api/prospeccion/gestiones')->assertOk();

        // Paginación estándar Laravel
        $this->assertArrayHasKey('data', $response->json());
        $this->assertGreaterThanOrEqual(5, $response->json('total'));
    }

    public function test_gestiones_ejecutivo_solo_ve_las_suyas(): void
    {
        $ejecutivo = $this->actingAsEjecutivo();
        $otro      = User::factory()->create(['role' => 'ejecutivo']);
        $tipo      = ProspeccionTipo::where('slug', 'buzon')->first();

        // 2 del ejecutivo actual, 1 del otro
        ProspeccionContacto::factory()->count(2)->create([
            'contacto_id' => $this->contacto()->id,
            'user_id'     => $ejecutivo->id,
            'tipo_id'     => $tipo->id,
        ]);
        ProspeccionContacto::factory()->create([
            'contacto_id' => $this->contacto()->id,
            'user_id'     => $otro->id,
            'tipo_id'     => $tipo->id,
        ]);

        $response = $this->getJson('/api/prospeccion/gestiones')->assertOk();
        $this->assertSame(2, $response->json('total'));
    }

    public function test_gestiones_filtra_por_mes(): void
    {
        $user = $this->actingAsAdmin();
        $tipo = ProspeccionTipo::where('slug', 'llamar_luego')->first();
        $mes  = now()->format('Y-m');

        // 2 este mes, 1 mes pasado
        ProspeccionContacto::factory()->count(2)->create([
            'contacto_id' => $this->contacto()->id,
            'user_id'     => $user->id,
            'tipo_id'     => $tipo->id,
            'created_at'  => now(),
        ]);
        ProspeccionContacto::factory()->create([
            'contacto_id' => $this->contacto()->id,
            'user_id'     => $user->id,
            'tipo_id'     => $tipo->id,
            'created_at'  => now()->subMonth(),
        ]);

        $response = $this->getJson("/api/prospeccion/gestiones?mes={$mes}")->assertOk();
        $this->assertSame(2, $response->json('total'));
    }

    public function test_gestiones_busca_por_nombre_contacto(): void
    {
        $user     = $this->actingAsAdmin();
        $tipo     = ProspeccionTipo::where('slug', 'no_contesta')->first();
        $cliente  = Cliente::factory()->create();
        $buscado  = Contacto::factory()->create(['nombre' => 'Alejandro Ríos', 'cliente_id' => $cliente->id]);
        $otro     = Contacto::factory()->create(['nombre' => 'María Torres',   'cliente_id' => $cliente->id]);

        ProspeccionContacto::factory()->create(['contacto_id' => $buscado->id, 'user_id' => $user->id, 'tipo_id' => $tipo->id]);
        ProspeccionContacto::factory()->create(['contacto_id' => $otro->id,    'user_id' => $user->id, 'tipo_id' => $tipo->id]);

        $response = $this->getJson('/api/prospeccion/gestiones?q=Alejandro')->assertOk();
        $this->assertSame(1, $response->json('total'));
    }
}
