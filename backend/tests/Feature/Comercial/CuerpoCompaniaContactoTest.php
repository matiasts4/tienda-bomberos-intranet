<?php

namespace Tests\Feature\Comercial;

use App\Models\Cliente;
use App\Models\Compania;
use App\Models\Contacto;
use App\Models\Cotizacion;
use App\Models\Cuerpo;
use App\Models\Region;
use Tests\TestCase;

class CuerpoCompaniaContactoTest extends TestCase
{
    public function test_crear_cuerpo_no_genera_cliente_espejo(): void
    {
        $this->actingAsAdmin();
        $region = Region::create(['nombre' => 'Valparaíso']);
        $antes = Cliente::count();

        $this->postJson('/api/cuerpos', [
            'region_id' => $region->id,
            'nombre' => 'Valparaíso',
        ])->assertCreated();

        $this->assertSame($antes, Cliente::count());
    }

    public function test_actualizar_cuerpo_guarda_giro(): void
    {
        $this->actingAsAdmin();
        $cuerpo = Cuerpo::create(['id' => '0100100', 'nombre' => 'Quilicura']);

        $this->patchJson("/api/cuerpos/{$cuerpo->id}", [
            'nombre' => 'Quilicura',
            'giro' => 'Cuerpo de Bomberos',
        ])->assertOk()->assertJsonPath('giro', 'Cuerpo de Bomberos');

        $this->assertSame('Cuerpo de Bomberos', $cuerpo->fresh()->giro);
    }

    public function test_crear_compania_no_genera_cliente_espejo(): void
    {
        $this->actingAsAdmin();
        $cuerpo = Cuerpo::create(['id' => '0100100', 'nombre' => 'Valparaíso']);
        $antes = Cliente::count();

        $this->postJson('/api/companias', [
            'cuerpo_id' => $cuerpo->id,
            'nombre' => '1ª Compañía',
            'numero' => 1,
        ])->assertCreated();

        $this->assertSame($antes, Cliente::count());
    }

    public function test_contacto_se_vincula_a_varios_cuerpos_y_companias(): void
    {
        $cuerpo1 = Cuerpo::create(['id' => '0100100', 'nombre' => 'Valparaíso']);
        $cuerpo2 = Cuerpo::create(['id' => '0200100', 'nombre' => 'Concepción']);
        $compania = Compania::create(['id' => '0100101', 'cuerpo_id' => $cuerpo1->id, 'nombre' => '1ª Compañía', 'numero' => 1]);

        $contacto = Contacto::factory()->create(['nombre' => 'Juan Pérez']);
        $contacto->cuerpos()->attach([$cuerpo1->id, $cuerpo2->id]);
        $contacto->companias()->attach($compania->id);

        $this->assertCount(2, $contacto->cuerpos);
        $this->assertCount(1, $contacto->companias);
        $this->assertTrue($cuerpo1->contactos->contains($contacto));
        $this->assertTrue($compania->contactos->contains($contacto));
    }

    public function test_lista_contactos_por_cuerpo_y_por_compania(): void
    {
        $this->actingAsAdmin();
        $cuerpo = Cuerpo::create(['id' => '0100100', 'nombre' => 'Valparaíso']);
        $compania = Compania::create(['id' => '0100101', 'cuerpo_id' => $cuerpo->id, 'nombre' => '1ª Compañía', 'numero' => 1]);

        $delCuerpo = Contacto::factory()->create(['nombre' => 'Comandante Cuerpo']);
        $delCuerpo->cuerpos()->attach($cuerpo->id);

        $delaCompania = Contacto::factory()->create(['nombre' => 'Capitán Compañía']);
        $delaCompania->companias()->attach($compania->id);

        $this->getJson("/api/cuerpos/{$cuerpo->id}/contactos")
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.nombre', 'Comandante Cuerpo');

        $this->getJson("/api/companias/{$compania->id}/contactos")
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.nombre', 'Capitán Compañía');
    }

    public function test_contactos_directos_del_cuerpo_excluyen_a_los_de_sus_companias(): void
    {
        $this->actingAsAdmin();
        $cuerpo = Cuerpo::create(['id' => '0100100', 'nombre' => 'Valparaíso']);
        $compania = Compania::create(['id' => '0100101', 'cuerpo_id' => $cuerpo->id, 'nombre' => '1ª Compañía', 'numero' => 1]);

        $comandante = Contacto::factory()->create(['nombre' => 'Comandante Cuerpo']);
        $comandante->cuerpos()->attach($cuerpo->id);
        $capitan = Contacto::factory()->create(['nombre' => 'Capitán Compañía']);
        $capitan->cuerpos()->attach($cuerpo->id);
        $capitan->companias()->attach($compania->id);

        $this->getJson("/api/cuerpos/{$cuerpo->id}/contactos")->assertOk()->assertJsonCount(2);

        $this->getJson("/api/cuerpos/{$cuerpo->id}/contactos?solo_directos=1")
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.nombre', 'Comandante Cuerpo');
    }

    public function test_companias_del_cuerpo_traen_su_conteo_de_contactos(): void
    {
        $this->actingAsAdmin();
        $cuerpo = Cuerpo::create(['id' => '0100100', 'nombre' => 'Valparaíso']);
        $otro = Cuerpo::create(['id' => '0200100', 'nombre' => 'Concepción']);
        $segunda = Compania::create(['id' => '0100102', 'cuerpo_id' => $cuerpo->id, 'nombre' => '2ª Compañía', 'numero' => 2]);
        $primera = Compania::create(['id' => '0100101', 'cuerpo_id' => $cuerpo->id, 'nombre' => '1ª Compañía', 'numero' => 1]);
        Compania::create(['id' => '0200101', 'cuerpo_id' => $otro->id, 'nombre' => '1ª Compañía Concepción', 'numero' => 1]);

        Contacto::factory()->count(2)->create()->each(fn ($c) => $c->companias()->attach($primera->id));

        $this->getJson("/api/cuerpos/{$cuerpo->id}/companias")
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.id', $primera->id)
            ->assertJsonPath('0.contactos_count', 2)
            ->assertJsonPath('1.id', $segunda->id)
            ->assertJsonPath('1.contactos_count', 0);
    }

    public function test_contacto_se_puede_crear_directo_con_cuerpo_ids_y_compania_ids(): void
    {
        $this->actingAsAdmin();
        $cuerpo = Cuerpo::create(['id' => '0100100', 'nombre' => 'Valparaíso']);
        $compania = Compania::create(['id' => '0100101', 'cuerpo_id' => $cuerpo->id, 'nombre' => '1ª Compañía', 'numero' => 1]);

        $response = $this->postJson('/api/contactos', [
            'nombre' => 'Ana Soto',
            'cuerpo_ids' => [$cuerpo->id],
            'compania_ids' => [$compania->id],
        ])->assertCreated();

        $contacto = Contacto::findOrFail($response->json('id'));
        $this->assertNull($contacto->cliente_id);
        $this->assertCount(1, $contacto->cuerpos);
        $this->assertCount(1, $contacto->companias);
    }

    public function test_contacto_creado_solo_con_compania_queda_tambien_en_su_cuerpo(): void
    {
        $this->actingAsAdmin();
        $cuerpo = Cuerpo::create(['id' => '0100100', 'nombre' => 'Valparaíso']);
        $compania = Compania::create(['id' => '0100101', 'cuerpo_id' => $cuerpo->id, 'nombre' => '1ª Compañía', 'numero' => 1]);

        // Así lo crea el "Nuevo contacto" de prospección: solo con la compañía.
        $response = $this->postJson('/api/contactos', [
            'nombre' => 'Ana Soto',
            'compania_ids' => [$compania->id],
        ])->assertCreated();

        $contacto = Contacto::findOrFail($response->json('id'));
        $this->assertSame([$cuerpo->id], $contacto->cuerpos->pluck('id')->all());
    }

    public function test_no_se_quita_el_cuerpo_de_un_contacto_que_sigue_en_su_compania(): void
    {
        $this->actingAsAdmin();
        $valpo = Cuerpo::create(['id' => '0100100', 'nombre' => 'Valparaíso']);
        $vina = Cuerpo::create(['id' => '0100200', 'nombre' => 'Viña del Mar']);
        $compania = Compania::create(['id' => '0100101', 'cuerpo_id' => $valpo->id, 'nombre' => '1ª Compañía', 'numero' => 1]);

        $contacto = Contacto::factory()->create();
        $contacto->cuerpos()->attach([$valpo->id, $vina->id]);
        $contacto->companias()->attach($compania->id);

        $this->patchJson("/api/contactos/{$contacto->id}", ['cuerpo_ids' => []])->assertOk();

        // Viña sale; Valparaíso se queda porque el contacto sigue en su 1ª Compañía.
        $this->assertSame([$valpo->id], $contacto->cuerpos()->pluck('cuerpos.id')->all());
    }

    public function test_cotizacion_anclada_a_cuerpo_resuelve_pdf_sin_cliente_espejo(): void
    {
        $this->seedEstadosCotizacion();
        $admin = $this->actingAsAdmin();

        $cuerpo = Cuerpo::create(['id' => '0100100', 'nombre' => 'Valparaíso']);
        $contacto = Contacto::factory()->create(['nombre' => 'Juan Pérez']);
        $contacto->cuerpos()->attach($cuerpo->id);

        $cot = Cotizacion::factory()->forUser($admin)->create([
            'cliente_id' => null,
            'contacto_id' => $contacto->id,
            'cuerpo_id' => $cuerpo->id,
        ]);

        \Barryvdh\DomPDF\Facade\Pdf::shouldReceive('loadView')
            ->once()
            ->withArgs(function ($view, $data) {
                $this->assertSame('Cuerpo de Bomberos de Valparaíso', $data['nombreClientePdf']);
                return true;
            })
            ->andReturn(\Mockery::mock(\Barryvdh\DomPDF\PDF::class)->shouldReceive('stream')
                ->andReturn(response('%PDF-1.4 fake', 200, ['Content-Type' => 'application/pdf']))
                ->getMock());

        $this->get("/api/cotizaciones/{$cot->id}/pdf")->assertOk();
    }
}
