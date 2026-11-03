<?php

namespace Tests\Feature\Prospeccion;

use App\Models\Compania;
use App\Models\Contacto;
use App\Models\Cuerpo;
use App\Models\ProspeccionContacto;
use App\Models\ProspeccionTipo;
use App\Models\Region;
use Tests\TestCase;

/**
 * Árbol Región → Cuerpo → Compañía de la cola de prospección: solo conteos, y
 * los contactos de cada hoja se piden al listado con los filtros de jerarquía.
 */
class ProspeccionArbolTest extends TestCase
{
    /**
     * Red de prueba:
     *  Valparaíso (5)
     *    Cuerpo Valparaíso 0500100: Ana (1ª), Beto (2ª), Carla (sin compañía), Diego (sin compañía)
     *    Cuerpo Viña 0510200: Diego (también está en Valparaíso)
     *  Metropolitana (13)
     *    Cuerpo Santiago 1300300: Elisa, Gabriel (descartado)
     *  Sin cuerpo: Fabián
     */
    private function red(): array
    {
        $valpo = Region::create(['nombre' => 'Valparaíso']);
        $rm = Region::create(['nombre' => 'Metropolitana']);

        $cValpo = Cuerpo::create(['id' => '0500100', 'nombre' => 'Valparaíso', 'region_id' => $valpo->id]);
        $cVina = Cuerpo::create(['id' => '0510200', 'nombre' => 'Viña del Mar', 'region_id' => $valpo->id]);
        $cStgo = Cuerpo::create(['id' => '1300300', 'nombre' => 'Santiago', 'region_id' => $rm->id]);

        $primera = Compania::create(['id' => '0500101', 'cuerpo_id' => $cValpo->id, 'nombre' => '1ª Compañía', 'numero' => 1]);
        $segunda = Compania::create(['id' => '0500102', 'cuerpo_id' => $cValpo->id, 'nombre' => '2ª Compañía', 'numero' => 2]);

        $contacto = function (string $nombre, array $cuerpos = [], array $companias = []) {
            $c = Contacto::factory()->create(['nombre' => $nombre, 'cliente_id' => null]);
            $c->cuerpos()->attach($cuerpos);
            $c->companias()->attach($companias);
            return $c;
        };

        $contacto('Ana', [$cValpo->id], [$primera->id]);
        $contacto('Beto', [$cValpo->id], [$segunda->id]);
        $contacto('Carla', [$cValpo->id]);
        $contacto('Diego', [$cValpo->id, $cVina->id]);
        $contacto('Elisa', [$cStgo->id]);
        $contacto('Fabián');
        $gabriel = $contacto('Gabriel', [$cStgo->id]);

        ProspeccionContacto::factory()->create([
            'contacto_id' => $gabriel->id,
            'tipo_id' => ProspeccionTipo::where('slug', 'no_califica')->value('id'),
            'proxima_llamada' => null,
        ]);

        return compact('valpo', 'rm', 'cValpo', 'cVina', 'cStgo', 'primera', 'segunda');
    }

    /** @return array<string, array> regiones indexadas por nombre, con sus cuerpos por nombre */
    private function arbol(string $query = ''): array
    {
        $json = $this->getJson('/api/prospeccion/arbol' . ($query ? "?{$query}" : ''))->assertOk()->json();

        $regiones = [];
        foreach ($json['regiones'] as $region) {
            $region['cuerpos'] = array_column($region['cuerpos'], 'total', 'nombre');
            $regiones[$region['nombre']] = $region;
        }

        return ['regiones' => $regiones, 'sin_cuerpo' => $json['sin_cuerpo']];
    }

    private function nombres(string $query): array
    {
        $nombres = array_column($this->getJson("/api/prospeccion/contactos?{$query}")->assertOk()->json('data'), 'nombre');
        sort($nombres);

        return $nombres;
    }

    public function test_arbol_requiere_autenticacion(): void
    {
        $this->getJson('/api/prospeccion/arbol')->assertUnauthorized();
    }

    public function test_arbol_cuenta_contactos_por_region_y_cuerpo(): void
    {
        $this->actingAsAdmin();
        $this->red();

        $arbol = $this->arbol();

        // Diego está en dos cuerpos de Valparaíso: cuenta en ambos, pero una sola
        // vez en la región. Gabriel fue descartado y ya no está en la cola.
        $this->assertSame(4, $arbol['regiones']['Valparaíso']['total']);
        $this->assertSame(['Valparaíso' => 4, 'Viña del Mar' => 1], $arbol['regiones']['Valparaíso']['cuerpos']);
        $this->assertSame(1, $arbol['regiones']['Metropolitana']['total']);
        $this->assertSame(['Santiago' => 1], $arbol['regiones']['Metropolitana']['cuerpos']);
        $this->assertSame(1, $arbol['sin_cuerpo']);
    }

    public function test_arbol_respeta_busqueda_y_filtro(): void
    {
        $this->actingAsAdmin();
        $this->red();

        $buscado = $this->arbol('q=Beto');
        $this->assertSame(['Valparaíso'], array_keys($buscado['regiones']));
        $this->assertSame(['Valparaíso' => 1], $buscado['regiones']['Valparaíso']['cuerpos']);
        $this->assertSame(0, $buscado['sin_cuerpo']);

        // "No califica" muestra a los descartados, que la cola normal esconde.
        $descartados = $this->arbol('filtro=no_califica');
        $this->assertSame(['Metropolitana'], array_keys($descartados['regiones']));
        $this->assertSame(['Santiago' => 1], $descartados['regiones']['Metropolitana']['cuerpos']);
    }

    public function test_cuerpo_lista_companias_y_contactos_sin_compania(): void
    {
        $this->actingAsAdmin();
        $red = $this->red();

        $this->getJson("/api/prospeccion/arbol/cuerpos/{$red['cValpo']->id}")
            ->assertOk()
            ->assertJson([
                'cuerpo_id' => '0500100',
                'total' => 4,
                'sin_compania' => 2,
                'companias' => [
                    ['id' => '0500101', 'nombre' => '1ª Compañía', 'numero' => 1, 'total' => 1],
                    ['id' => '0500102', 'nombre' => '2ª Compañía', 'numero' => 2, 'total' => 1],
                ],
            ]);
    }

    public function test_listado_filtra_por_hoja_del_arbol(): void
    {
        $this->actingAsAdmin();
        $red = $this->red();

        $this->assertSame(['Ana'], $this->nombres("compania_id={$red['primera']->id}"));
        $this->assertSame(['Carla', 'Diego'], $this->nombres("cuerpo_id={$red['cValpo']->id}&sin_compania=1"));
        $this->assertSame(['Diego'], $this->nombres("cuerpo_id={$red['cVina']->id}&sin_compania=1"));
        $this->assertSame(['Fabián'], $this->nombres('sin_cuerpo=1'));
    }

    public function test_dos_gestiones_en_el_mismo_segundo_no_duplican_al_contacto(): void
    {
        $this->actingAsAdmin();
        $contacto = Contacto::factory()->create(['nombre' => 'Doble Gestión']);
        $mismoSegundo = now()->subDays(5)->startOfSecond();

        foreach (['no_contesta', 'llamar_luego'] as $slug) {
            ProspeccionContacto::factory()->create([
                'contacto_id' => $contacto->id,
                'tipo_id' => ProspeccionTipo::where('slug', $slug)->value('id'),
                'proxima_llamada' => null,
                'created_at' => $mismoSegundo,
            ]);
        }

        $filas = array_values(array_filter(
            $this->getJson('/api/prospeccion/contactos')->assertOk()->json('data'),
            fn ($fila) => $fila['nombre'] === 'Doble Gestión'
        ));

        $this->assertCount(1, $filas);
        // La última gestión es la última insertada, aunque compartan created_at.
        $this->assertSame('llamar_luego', $filas[0]['ultima_tipo']);
    }
}
