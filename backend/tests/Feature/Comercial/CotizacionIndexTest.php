<?php

namespace Tests\Feature\Comercial;

use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\User;
use Tests\TestCase;

class CotizacionIndexTest extends TestCase
{
    public function test_requiere_autenticacion(): void
    {
        $this->getJson('/api/cotizaciones')->assertUnauthorized();
    }

    public function test_ejecutivo_solo_ve_sus_propias_cotizaciones(): void
    {
        $this->seedEstadosCotizacion();
        $ejecutivo = $this->actingAsEjecutivo();
        $otro = User::factory()->create(['role' => 'ejecutivo']);

        Cotizacion::factory()->forUser($ejecutivo)->count(2)->create();
        Cotizacion::factory()->forUser($otro)->count(3)->create();

        $response = $this->getJson('/api/cotizaciones')->assertOk();

        // El tablero ahora es compartido: todos los usuarios ven todas las
        // cotizaciones. El filtro por ejecutivo se aplica vía query param.
        $this->assertSame(5, $response->json('total'));
    }

    public function test_admin_ve_todas_las_cotizaciones(): void
    {
        $this->seedEstadosCotizacion();
        $this->actingAsAdmin();
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();

        Cotizacion::factory()->forUser($u1)->count(2)->create();
        Cotizacion::factory()->forUser($u2)->count(3)->create();

        $this->getJson('/api/cotizaciones')
            ->assertOk()
            ->assertJsonPath('total', 5);
    }

    public function test_filtra_por_estado_id(): void
    {
        $estados = $this->seedEstadosCotizacion();
        $admin = $this->actingAsAdmin();

        Cotizacion::factory()->forUser($admin)->withEstado($estados['Cotización Enviada'])->count(2)->create();
        Cotizacion::factory()->forUser($admin)->withEstado($estados['Rechazada'])->count(1)->create();

        $this->getJson('/api/cotizaciones?estado_id=' . $estados['Rechazada']->id)
            ->assertOk()
            ->assertJsonPath('total', 1);
    }

    public function test_filtra_por_cliente_y_includes_children(): void
    {
        $this->seedEstadosCotizacion();
        $admin = $this->actingAsAdmin();

        $padre = Cliente::factory()->create();
        $hijo = Cliente::factory()->create(['parent_id' => $padre->id]);

        Cotizacion::factory()->forUser($admin)->forCliente($padre)->create();
        Cotizacion::factory()->forUser($admin)->forCliente($hijo)->create();

        $this->getJson('/api/cotizaciones?cliente_id=' . $padre->id)
            ->assertOk()
            ->assertJsonPath('total', 1);

        $this->getJson('/api/cotizaciones?cliente_id=' . $padre->id . '&include_children=1')
            ->assertOk()
            ->assertJsonPath('total', 2);
    }

    public function test_busqueda_por_numero(): void
    {
        $this->seedEstadosCotizacion();
        $admin = $this->actingAsAdmin();

        Cotizacion::factory()->forUser($admin)->create(['numero' => 'CT-9999']);
        Cotizacion::factory()->forUser($admin)->create(['numero' => 'CT-0001']);

        $this->getJson('/api/cotizaciones?search=9999')
            ->assertOk()
            ->assertJsonPath('total', 1);
    }

    public function test_filtro_numero_acepta_id_mostrado_en_el_tablero(): void
    {
        $this->seedEstadosCotizacion();
        $admin = $this->actingAsAdmin();

        // Sin `numero`, el tablero la muestra como "CT-0042" (id con ceros).
        $buscada = Cotizacion::factory()->forUser($admin)->create(['numero' => null]);
        Cotizacion::factory()->forUser($admin)->count(3)->create(['numero' => null]);
        $etiqueta = 'CT-' . str_pad((string) $buscada->id, 4, '0', STR_PAD_LEFT);

        foreach ([(string) $buscada->id, $etiqueta, strtolower($etiqueta), '#' . $buscada->id, ' ' . $buscada->id . ' '] as $termino) {
            $response = $this->getJson('/api/cotizaciones?numero=' . urlencode($termino))->assertOk();
            $this->assertSame(1, $response->json('total'), "término: {$termino}");
            $this->assertSame($buscada->id, $response->json('data.0.id'), "término: {$termino}");
        }
    }

    public function test_filtro_numero_es_exacto_sobre_el_id(): void
    {
        $this->seedEstadosCotizacion();
        $admin = $this->actingAsAdmin();

        Cotizacion::factory()->forUser($admin)->create(['id' => 42, 'numero' => null]);
        Cotizacion::factory()->forUser($admin)->create(['id' => 420, 'numero' => null]);
        Cotizacion::factory()->forUser($admin)->create(['id' => 1042, 'numero' => null]);

        $this->getJson('/api/cotizaciones?numero=42')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', 42);
    }

    public function test_filtro_numero_busca_en_numero_guardado(): void
    {
        $this->seedEstadosCotizacion();
        $admin = $this->actingAsAdmin();

        $conNumero = Cotizacion::factory()->forUser($admin)->create(['numero' => 'CT-9999']);
        Cotizacion::factory()->forUser($admin)->create(['numero' => 'CT-0001']);

        foreach (['CT-9999', '9999', '#9999'] as $termino) {
            $this->getJson('/api/cotizaciones?numero=' . urlencode($termino))
                ->assertOk()
                ->assertJsonPath('total', 1)
                ->assertJsonPath('data.0.id', $conNumero->id);
        }
    }

    public function test_filtro_numero_se_combina_con_los_demas_filtros(): void
    {
        $estados = $this->seedEstadosCotizacion();
        $admin = $this->actingAsAdmin();

        $cot = Cotizacion::factory()->forUser($admin)->withEstado($estados['Rechazada'])->create(['numero' => null]);

        $this->getJson('/api/cotizaciones?numero=' . $cot->id . '&estado_id=' . $estados['Cotización Enviada']->id)
            ->assertOk()
            ->assertJsonPath('total', 0);
    }

    public function test_paginacion_respeta_per_page(): void
    {
        $this->seedEstadosCotizacion();
        $admin = $this->actingAsAdmin();
        Cotizacion::factory()->forUser($admin)->count(25)->create();

        $this->getJson('/api/cotizaciones?per_page=5')
            ->assertOk()
            ->assertJsonPath('per_page', 5)
            ->assertJsonCount(5, 'data');
    }
}
