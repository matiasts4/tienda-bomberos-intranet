<?php

namespace Tests\Feature\Prospeccion;

use App\Models\Cargo;
use App\Models\Compania;
use App\Models\Contacto;
use App\Models\Cuerpo;
use App\Models\Estado;
use App\Models\Oportunidad;
use App\Models\ProspeccionContacto;
use App\Models\ProspeccionTipo;
use App\Models\Region;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * La cola como la trabaja un ejecutivo: baldes relativos a hoy, en orden de
 * urgencia, solo con quien se puede llamar y decide la compra, y con el
 * contexto de cada cuenta (leads abiertos, última gestión).
 */
class ProspeccionColaComercialTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-21 10:00'));
    }

    private function gestion(Contacto $contacto, string $slug, string $creada, ?string $proxima = null): void
    {
        ProspeccionContacto::factory()->create([
            'contacto_id' => $contacto->id,
            'tipo_id' => ProspeccionTipo::where('slug', $slug)->value('id'),
            'proxima_llamada' => $proxima,
            'created_at' => Carbon::parse($creada),
        ]);
    }

    private function contacto(string $nombre, array $attrs = []): Contacto
    {
        return Contacto::factory()->create(array_merge(['nombre' => $nombre, 'telefono' => '+56911111111'], $attrs));
    }

    /** @return array<string, int> nombre => prioridad, en el orden del listado */
    private function listado(string $query = ''): array
    {
        $filas = $this->getJson('/api/prospeccion/contactos?' . $query)->assertOk()->json('data');

        return array_column($filas, 'prioridad', 'nombre');
    }

    private function colaDelDia(): void
    {
        $this->gestion($this->contacto('Vencido'), 'llamar_luego', '2026-10-10', '2026-10-15');
        $this->gestion($this->contacto('Para Hoy'), 'llamar_luego', '2026-10-14', '2026-10-21');
        $this->gestion($this->contacto('Reintentar'), 'no_contesta', '2026-10-16');
        $this->contacto('Nuevo');
        $this->gestion($this->contacto('Proximo'), 'llamar_luego', '2026-10-20', '2026-10-24');
        $this->gestion($this->contacto('En Espera'), 'no_contesta', '2026-10-20');
        $this->gestion($this->contacto('Agendado Lejos'), 'llamar_luego', '2026-10-20', '2026-11-20');
    }

    public function test_la_cola_se_ordena_por_urgencia_del_dia(): void
    {
        $this->actingAsAdmin();
        $this->colaDelDia();

        $this->assertSame([
            'Vencido' => 1,
            'Para Hoy' => 2,
            'Reintentar' => 3,
            'Nuevo' => 4,
            'Proximo' => 5,
            // Dentro de cada prioridad manda la fecha agendada; sin fecha va primero.
            'En Espera' => 99,
            'Agendado Lejos' => 99,
        ], $this->listado());
    }

    public function test_cada_balde_lista_lo_que_cuenta(): void
    {
        $this->actingAsAdmin();
        $this->colaDelDia();

        $conteos = $this->getJson('/api/prospeccion/contactos/counts')->assertOk()->json();

        foreach (['vencidos' => 'Vencido', 'hoy' => 'Para Hoy', 'reintentar' => 'Reintentar', 'nuevos' => 'Nuevo', 'proximos' => 'Proximo'] as $balde => $nombre) {
            $this->assertSame([$nombre], array_keys($this->listado("filtro={$balde}")), "balde {$balde}");
            $this->assertSame(1, $conteos[$balde], "conteo {$balde}");
        }
        $this->assertSame(7, $conteos['all']);
    }

    public function test_un_seguimiento_agendado_no_cuenta_como_reintento(): void
    {
        $this->actingAsAdmin();
        // Sin respuesta hace días, pero con fecha de seguimiento: manda la agenda.
        $this->gestion($this->contacto('Agendado'), 'no_contesta', '2026-10-10', '2026-10-25');

        $this->assertSame(['Agendado' => 5], $this->listado());
        $this->assertSame([], $this->listado('filtro=reintentar'));
    }

    public function test_con_telefono_y_decisores_acotan_lista_y_conteos(): void
    {
        $this->actingAsAdmin();
        $capitan = Cargo::create(['nombre' => 'CAPITAN']);
        $bombero = Cargo::create(['nombre' => 'BOMBERO']);

        $this->contacto('Capitan Con Fono', ['cargo_id' => $capitan->id]);
        $this->contacto('Capitan Sin Fono', ['cargo_id' => $capitan->id, 'telefono' => null]);
        $this->contacto('Bombero Con Fono', ['cargo_id' => $bombero->id]);

        $this->assertSame(['Bombero Con Fono', 'Capitan Con Fono'], $this->nombres('con_telefono=1'));
        $this->assertSame(['Capitan Con Fono', 'Capitan Sin Fono'], $this->nombres('decisores=1'));
        $this->assertSame(['Capitan Con Fono'], $this->nombres('con_telefono=1&decisores=1'));

        $this->getJson('/api/prospeccion/contactos/counts?con_telefono=1&decisores=1')
            ->assertOk()
            ->assertJsonPath('all', 1)
            ->assertJsonPath('nuevos', 1);
    }

    public function test_el_arbol_muestra_leads_abiertos_y_ultima_gestion_de_cada_cuenta(): void
    {
        $this->actingAsAdmin();
        $region = Region::create(['nombre' => 'Valparaíso']);
        $cuerpo = Cuerpo::create(['id' => '0500100', 'nombre' => 'Valparaíso', 'region_id' => $region->id]);
        $primera = Compania::create(['id' => '0500101', 'cuerpo_id' => $cuerpo->id, 'nombre' => '1ª Compañía', 'numero' => 1]);
        $segunda = Compania::create(['id' => '0500102', 'cuerpo_id' => $cuerpo->id, 'nombre' => '2ª Compañía', 'numero' => 2]);

        $llamado = $this->contacto('Llamado');
        $llamado->cuerpos()->attach($cuerpo->id);
        $llamado->companias()->attach($primera->id);
        $this->gestion($llamado, 'no_contesta', '2026-10-20 15:30:00');

        $otro = $this->contacto('Otro');
        $otro->cuerpos()->attach($cuerpo->id);
        $otro->companias()->attach($segunda->id);

        $abierto = Estado::create(['nombre' => 'Cotización enviada', 'scope' => 'oportunidad', 'orden' => 1]);
        $cerrado = Estado::create(['nombre' => 'Cerrada perdida', 'scope' => 'oportunidad', 'orden' => 2]);
        Oportunidad::create(['estado_id' => $abierto->id, 'compania_id' => $primera->id, 'fecha_ingreso' => '2026-10-01']);
        Oportunidad::create(['estado_id' => $cerrado->id, 'compania_id' => $segunda->id, 'fecha_ingreso' => '2026-10-01']);

        $this->getJson('/api/prospeccion/arbol')
            ->assertOk()
            ->assertJsonPath('regiones.0.cuerpos.0.leads_abiertos', 1)
            ->assertJsonPath('regiones.0.cuerpos.0.ultima_gestion_at', '2026-10-20 15:30:00');

        // La 1ª tiene un lead abierto: sus contactos no están en la cola y la
        // compañía solo aparece entre las cuentas bloqueadas, con su contexto.
        $this->getJson("/api/prospeccion/arbol/cuerpos/{$cuerpo->id}")
            ->assertOk()
            ->assertJsonCount(1, 'companias')
            ->assertJsonPath('companias.0.id', $segunda->id)
            ->assertJsonPath('companias.0.leads_abiertos', 0)
            ->assertJsonPath('companias.0.ultima_gestion_at', null);

        $this->getJson("/api/prospeccion/arbol/cuerpos/{$cuerpo->id}?filtro=bloqueados")
            ->assertOk()
            ->assertJsonPath('companias.0.id', $primera->id)
            ->assertJsonPath('companias.0.leads_abiertos', 1)
            ->assertJsonPath('companias.0.ultima_gestion_at', '2026-10-20 15:30:00');
    }

    private function nombres(string $query): array
    {
        $nombres = array_keys($this->listado($query));
        sort($nombres);

        return $nombres;
    }
}
