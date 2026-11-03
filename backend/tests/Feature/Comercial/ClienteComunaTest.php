<?php

namespace Tests\Feature\Comercial;

use App\Models\Cliente;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClienteComunaTest extends TestCase
{
    private int $regionNuble;
    private int $yungay;

    protected function setUp(): void
    {
        parent::setUp();

        $this->regionNuble = DB::table('regiones')->insertGetId(['nombre' => 'Ñuble', 'created_at' => now(), 'updated_at' => now()]);
        $this->yungay = DB::table('comunas')->insertGetId([
            'nombre' => 'Yungay',
            'region_id' => $this->regionNuble,
            'provincia_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_cliente_nuevo_guarda_la_comuna_elegida(): void
    {
        $this->actingAsEjecutivo();

        $this->postJson('/api/clientes', [
            'nombre_empresa' => 'Tienda Ñuble',
            'tipo' => 'empresa',
            'comuna_id' => $this->yungay,
        ])->assertCreated();

        $cliente = Cliente::where('nombre_empresa', 'Tienda Ñuble')->firstOrFail();
        $this->assertSame($this->yungay, (int) $cliente->comuna_id);
    }

    public function test_comuna_sin_region_completa_la_region_de_la_comuna(): void
    {
        $this->actingAsEjecutivo();

        $this->postJson('/api/clientes', [
            'nombre_empresa' => 'Tienda Ñuble',
            'tipo' => 'empresa',
            'region_id' => null,
            'comuna_id' => $this->yungay,
        ])->assertCreated();

        $this->assertSame($this->regionNuble, (int) Cliente::where('nombre_empresa', 'Tienda Ñuble')->value('region_id'));
    }

    public function test_editar_cliente_con_comuna_guarda_comuna_y_region(): void
    {
        $this->actingAsEjecutivo();
        $cliente = Cliente::factory()->create(['region_id' => null, 'comuna_id' => null]);

        $this->patchJson("/api/clientes/{$cliente->id}", ['comuna_id' => $this->yungay, 'region_id' => null])
            ->assertOk()
            ->assertJsonPath('comuna.nombre', 'Yungay');

        $fresh = $cliente->fresh();
        $this->assertSame($this->yungay, (int) $fresh->comuna_id);
        $this->assertSame($this->regionNuble, (int) $fresh->region_id);
    }

    public function test_region_enviada_explicitamente_se_respeta(): void
    {
        $this->actingAsEjecutivo();
        $otraRegion = DB::table('regiones')->insertGetId(['nombre' => 'Biobío', 'created_at' => now(), 'updated_at' => now()]);

        $this->postJson('/api/clientes', [
            'nombre_empresa' => 'Tienda X',
            'tipo' => 'empresa',
            'region_id' => $otraRegion,
            'comuna_id' => $this->yungay,
        ])->assertCreated();

        $this->assertSame($otraRegion, (int) Cliente::where('nombre_empresa', 'Tienda X')->value('region_id'));
    }

    public function test_buscador_encuentra_personas_por_rut_sin_formato_nombre_y_telefono(): void
    {
        $this->actingAsEjecutivo();
        $persona = Cliente::factory()->create([
            'tipo' => 'particular',
            'nombre_empresa' => 'Juan Pérez Soto',
            'rut_empresa' => '12.345.678-9',
            'telefono' => '+56911112222',
            'correo' => null,
        ]);
        Cliente::factory()->create(['tipo' => 'empresa', 'nombre_empresa' => 'Otra', 'rut_empresa' => '76.000.000-0']);

        foreach (['12345678', '12.345.678-9', 'pérez', '1111222'] as $termino) {
            $ids = collect($this->getJson('/api/clientes?search=' . urlencode($termino))->assertOk()->json('data'))->pluck('id')->all();
            $this->assertSame([(string) $persona->id], array_map('strval', $ids), "búsqueda: {$termino}");
        }
    }
}
