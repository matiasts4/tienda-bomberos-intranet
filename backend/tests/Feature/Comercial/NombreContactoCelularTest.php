<?php

namespace Tests\Feature\Comercial;

use App\Models\Compania;
use App\Models\Contacto;
use App\Models\Cotizacion;
use App\Models\Cuerpo;
use App\Models\User;
use App\Support\NombreContactoCelular;
use Tests\TestCase;

class NombreContactoCelularTest extends TestCase
{
    public function test_abrevia_cargos_con_su_ordinal(): void
    {
        $casos = [
            'Director' => 'DIR',
            'DIRECTOR(A) 2do' => 'DIR2',
            'Teniente 1°' => 'TEN1',
            'TENIENTE 1ro' => 'TEN1',
            'Segundo Comandante' => 'CMDT2',
            'Cuarto Comandante' => 'CMDT4',
            'Comandante' => 'CMDT',
            'Vice Superintendente' => 'VSUP',
            'Superintendente' => 'SUP',
            'Capitán' => 'CAP',
            'Pro Tesorera Compañía' => 'PTES',
            'Secretario General' => 'SEC',
            'Cuartelero Conductor' => 'CUAR',
            'Bombero Compañía' => 'BOM',
        ];
        foreach ($casos as $cargo => $esperado) {
            $this->assertSame($esperado, NombreContactoCelular::abreviarCargo($cargo), $cargo);
        }
        $this->assertNull(NombreContactoCelular::abreviarCargo(null));
    }

    public function test_arma_el_nombre_de_contacto_de_compania_y_de_cuerpo(): void
    {
        $this->actingAsAdmin();
        $v2 = User::factory()->create(['name' => 'Claudia Sánchez', 'codigo_vendedor' => 'V2']);
        $cuerpo = Cuerpo::create(['id' => '0500100', 'nombre' => 'La Ligua', 'ejecutivo_id' => $v2->id]);
        $compania = Compania::create(['id' => '0500105', 'cuerpo_id' => $cuerpo->id, 'nombre' => '5ª Compañía La Ligua', 'numero' => 5]);

        $teniente = Contacto::create(['nombre' => 'Toto  Pavo', 'cargo' => 'Teniente 1°']);
        $teniente->cuerpos()->attach($cuerpo->id);
        $teniente->companias()->attach($compania->id);

        $director = Contacto::create(['nombre' => 'Pepe Pollo', 'cargo' => 'Director']);
        $director->cuerpos()->attach($cuerpo->id);

        $res = $this->getJson("/api/cuerpos/{$cuerpo->id}/contactos")->assertOk()->json();
        $nombres = collect($res)->pluck('nom_contac_celu', 'nombre');

        $this->assertSame('CB La Ligua - 5 - Toto Pavo - TEN1 - V2', $nombres['Toto  Pavo']);
        $this->assertSame('CB La Ligua - 0 - Pepe Pollo - DIR - V2', $nombres['Pepe Pollo']);
    }

    public function test_marca_lo_que_falta_y_no_aplica_a_empresas(): void
    {
        $this->actingAsAdmin();
        $cuerpo = Cuerpo::create(['id' => '0500200', 'nombre' => 'Cuerpo de Bomberos de Limache']);
        $sinCartera = Contacto::create(['nombre' => 'Ana Ruiz']);
        $sinCartera->cuerpos()->attach($cuerpo->id);
        $suelto = Contacto::create(['nombre' => 'Sin Entidad']);

        $this->assertSame('CB Limache - 0 - Ana Ruiz - ? - ?', NombreContactoCelular::para($sinCartera->load(NombreContactoCelular::RELACIONES)));
        $this->assertNull(NombreContactoCelular::para($suelto->load(NombreContactoCelular::RELACIONES)));
    }

    public function test_codigo_vendedor_es_unico_y_con_formato(): void
    {
        $this->actingAsAdmin(['role' => 'superadmin']);
        $u1 = User::factory()->create(['codigo_vendedor' => 'V1']);
        $u2 = User::factory()->create();

        $this->putJson("/api/users/{$u2->id}", ['codigo_vendedor' => 'V1'])->assertStatus(422);
        $this->putJson("/api/users/{$u2->id}", ['codigo_vendedor' => 'X9'])->assertStatus(422);
        $this->putJson("/api/users/{$u2->id}", ['codigo_vendedor' => 'V3'])->assertOk();
        $this->assertSame('V3', $u2->fresh()->codigo_vendedor);
    }

    public function test_cuerpo_se_asigna_a_una_cartera(): void
    {
        $this->actingAsAdmin();
        $ejecutivo = User::factory()->create(['codigo_vendedor' => 'V4']);
        $cuerpo = Cuerpo::create(['id' => '0500300', 'nombre' => 'Quillota']);

        $this->patchJson("/api/cuerpos/{$cuerpo->id}", ['ejecutivo_id' => $ejecutivo->id])
            ->assertOk()->assertJsonPath('ejecutivo.codigo_vendedor', 'V4');
    }

    public function test_reporte_red_bomberos_cuenta_ventas_por_cuerpo_y_compania(): void
    {
        $this->seedEstadosCotizacion();
        $admin = $this->actingAsAdmin();
        $cuerpo = Cuerpo::create(['id' => '0500400', 'nombre' => 'Quilpué', 'region_id' => null, 'ejecutivo_id' => $admin->id]);
        $c1 = Compania::create(['id' => '0500401', 'cuerpo_id' => $cuerpo->id, 'nombre' => '1ª', 'numero' => 1]);
        Compania::create(['id' => '0500402', 'cuerpo_id' => $cuerpo->id, 'nombre' => '2ª', 'numero' => 2]);
        Cuerpo::create(['id' => '0500500', 'nombre' => 'Villa Alemana']);

        Cotizacion::factory()->forUser($admin)->create(['compania_id' => $c1->id, 'cuerpo_id' => null, 'fecha_venta' => '2026-09-01', 'total' => 1000]);
        Cotizacion::factory()->forUser($admin)->create(['cuerpo_id' => $cuerpo->id, 'fecha_venta' => '2026-09-02', 'total' => 500]);
        // Venta que terminó desistida: no cuenta
        Cotizacion::factory()->forUser($admin)->create(['cuerpo_id' => $cuerpo->id, 'fecha_venta' => '2026-09-03', 'total' => 9999, 'motivo_no_vigente' => 'desistida']);
        // Sin venta: no cuenta
        Cotizacion::factory()->forUser($admin)->create(['cuerpo_id' => $cuerpo->id, 'fecha_venta' => null, 'total' => 7777]);

        $res = $this->getJson('/api/reportes/red-bomberos')->assertOk();
        $fila = collect($res->json('cuerpos'))->firstWhere('id', '0500400');

        $this->assertSame(2, $fila['ventas']);
        $this->assertEquals(1500, $fila['monto']);
        $this->assertSame(2, $fila['companias']);
        $this->assertSame(1, $fila['companias_con_venta']);
        $this->assertSame(0, collect($res->json('cuerpos'))->firstWhere('id', '0500500')['ventas']);

        $this->getJson('/api/reportes/red-bomberos?desde=2026-09-02')->assertOk()
            ->assertJsonPath('cuerpos.0.ventas', 1);
    }
}
