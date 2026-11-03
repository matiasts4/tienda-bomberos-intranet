<?php

namespace Tests\Feature\Prospeccion;

use App\Models\Cliente;
use App\Models\Contacto;
use App\Models\ProspeccionContacto;
use App\Models\ProspeccionTipo;
use App\Models\User;
use Tests\TestCase;

class ProspeccionIndexTest extends TestCase
{
    // ── auth ───────────────────────────────────────────────────────────────

    public function test_index_requiere_autenticacion(): void
    {
        $this->getJson('/api/prospeccion/contactos')->assertUnauthorized();
    }

    // ── lista base ─────────────────────────────────────────────────────────

    public function test_index_retorna_contactos_paginados(): void
    {
        $this->actingAsAdmin();
        $cliente = Cliente::factory()->create();
        Contacto::factory()->count(3)->create(['cliente_id' => $cliente->id]);

        $response = $this->getJson('/api/prospeccion/contactos')->assertOk();
        $this->assertGreaterThanOrEqual(3, $response->json('total'));
    }

    // ── filtros ────────────────────────────────────────────────────────────

    public function test_index_filtra_por_nombre(): void
    {
        $this->actingAsAdmin();
        $cliente = Cliente::factory()->create();
        Contacto::factory()->create(['nombre' => 'Juan Bombero', 'cliente_id' => $cliente->id]);
        Contacto::factory()->create(['nombre' => 'Pedro Pérez',  'cliente_id' => $cliente->id]);

        $response = $this->getJson('/api/prospeccion/contactos?q=Juan')->assertOk();
        $nombres = array_column($response->json('data'), 'nombre');

        $this->assertContains('Juan Bombero', $nombres);
        $this->assertNotContains('Pedro Pérez', $nombres);
    }

    // ── exclusiones ────────────────────────────────────────────────────────

    public function test_index_excluye_contactos_no_califica(): void
    {
        $this->actingAsAdmin();
        $cliente    = Cliente::factory()->create();
        $descartado = Contacto::factory()->create(['nombre' => 'Descartado X', 'cliente_id' => $cliente->id]);
        $tipoNoCalifica = ProspeccionTipo::where('slug', 'no_califica')->first();

        ProspeccionContacto::factory()->create([
            'contacto_id' => $descartado->id,
            'tipo_id'     => $tipoNoCalifica->id,
        ]);

        $response = $this->getJson('/api/prospeccion/contactos')->assertOk();
        $nombres  = array_column($response->json('data'), 'nombre');

        $this->assertNotContains('Descartado X', $nombres);
    }

    public function test_index_excluye_contactos_ya_calificados(): void
    {
        $this->actingAsAdmin();
        $cliente       = Cliente::factory()->create();
        $calificado    = Contacto::factory()->create(['nombre' => 'Calificado Y', 'cliente_id' => $cliente->id]);
        $tipoCalificado = ProspeccionTipo::where('slug', 'calificado')->first();

        ProspeccionContacto::factory()->create([
            'contacto_id' => $calificado->id,
            'tipo_id'     => $tipoCalificado->id,
        ]);

        $response = $this->getJson('/api/prospeccion/contactos')->assertOk();
        $nombres  = array_column($response->json('data'), 'nombre');

        $this->assertNotContains('Calificado Y', $nombres);
    }

    public function test_index_incluye_contactos_sin_interaccion(): void
    {
        $this->actingAsAdmin();
        $cliente = Cliente::factory()->create();
        Contacto::factory()->create(['nombre' => 'Sin Historial', 'cliente_id' => $cliente->id]);

        $response = $this->getJson('/api/prospeccion/contactos')->assertOk();
        $nombres  = array_column($response->json('data'), 'nombre');

        $this->assertContains('Sin Historial', $nombres);
    }

    // ── filtros del selector: lista y conteo deben calzar ──────────────────

    private function gestion(Contacto $contacto, string $slug, $createdAt, array $attrs = []): ProspeccionContacto
    {
        return ProspeccionContacto::factory()->create(array_merge([
            'contacto_id'     => $contacto->id,
            'tipo_id'         => ProspeccionTipo::where('slug', $slug)->first()->id,
            'proxima_llamada' => null,
            'created_at'      => $createdAt,
        ], $attrs));
    }

    /** @return array{0: string[], 1: array} [nombres listados, conteos] */
    private function listarYContar(string $filtro, string $mes): array
    {
        $lista  = $this->getJson("/api/prospeccion/contactos?filtro={$filtro}&mes={$mes}")->assertOk();
        $counts = $this->getJson("/api/prospeccion/contactos/counts?mes={$mes}")->assertOk();

        return [array_column($lista->json('data'), 'nombre'), $counts->json()];
    }

    public function test_sin_respuesta_cuenta_contactos_sin_proxima_llamada(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);
        $contacto = Contacto::factory()->create(['nombre' => 'Sin Respuesta']);
        $this->gestion($contacto, 'no_contesta', now()->subDays(5));

        [$nombres, $counts] = $this->listarYContar('3', now()->format('Y-m'));

        $this->assertSame(['Sin Respuesta'], $nombres);
        $this->assertSame(1, $counts['p3']);
    }

    public function test_filtros_de_resueltos_muestran_descartados_y_calificados(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);
        $mes = now()->format('Y-m');
        $this->gestion(Contacto::factory()->create(['nombre' => 'Descartado']), 'no_califica', now());
        $this->gestion(Contacto::factory()->create(['nombre' => 'Calificado']), 'calificado', now());
        Contacto::factory()->create(['nombre' => 'En Cola']);

        [$descartados, $counts] = $this->listarYContar('no_califica', $mes);
        [$calificados] = $this->listarYContar('calificado', $mes);

        $this->assertSame(['Descartado'], $descartados);
        $this->assertSame(['Calificado'], $calificados);
        $this->assertSame(1, $counts['no_califica']);
        $this->assertSame(1, $counts['calificado']);
        // "Todos" sigue siendo la cola: los resueltos no cuentan ahí.
        $this->assertSame(1, $counts['all']);
    }

    public function test_llamadas_mes_incluye_resueltos_y_gestiones_que_no_son_la_ultima(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);
        $mes = now()->startOfMonth();

        $this->gestion(Contacto::factory()->create(['nombre' => 'Calificado Este Mes']), 'calificado', $mes->copy()->addDay());

        // Llamado este mes y de nuevo el mes siguiente: su última gestión cae fuera.
        $vuelto = Contacto::factory()->create(['nombre' => 'Llamado Dos Veces']);
        $this->gestion($vuelto, 'no_contesta', $mes->copy()->addDays(2));
        $this->gestion($vuelto, 'no_contesta', $mes->copy()->addMonth()->addDay());

        $this->gestion(Contacto::factory()->create(['nombre' => 'Mes Pasado']), 'no_contesta', $mes->copy()->subMonth());

        [$nombres, $counts] = $this->listarYContar('llamadas_mes', $mes->format('Y-m'));

        sort($nombres);
        $this->assertSame(['Calificado Este Mes', 'Llamado Dos Veces'], $nombres);
        $this->assertSame(2, $counts['llamadas_mes']);
    }

    public function test_llamadas_mes_de_ejecutivo_solo_considera_sus_gestiones(): void
    {
        $ejecutivo = $this->actingAsEjecutivo();
        $otro      = User::factory()->create();

        $this->gestion(Contacto::factory()->create(['nombre' => 'Mio']), 'llamar_luego', now(), ['user_id' => $ejecutivo->id]);
        $this->gestion(Contacto::factory()->create(['nombre' => 'De Otro']), 'llamar_luego', now(), ['user_id' => $otro->id]);

        [$nombres, $counts] = $this->listarYContar('llamadas_mes', now()->format('Y-m'));

        $this->assertSame(['Mio'], $nombres);
        $this->assertSame(1, $counts['llamadas_mes']);
    }
}
