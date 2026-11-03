<?php

namespace Tests\Feature\Inventario;

use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CatalogoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Limpiar cache entre tests para evitar contaminación
        Cache::flush();
    }

    public function test_catalogo_todos_retorna_estructura_completa(): void
    {
        $this->getJson('/api/catalogos/todos')
            ->assertOk()
            ->assertJsonStructure([
                'unidades', 'origenes', 'grupos', 'familias',
                'subfamilias', 'tipos', 'formatos',
                'generos', 'colores', 'tallas', 'cargos',
            ]);
    }

    public function test_catalogos_individuales_retornan_200(): void
    {
        $endpoints = [
            '/api/catalogos/unidades',
            '/api/catalogos/origenes',
            '/api/catalogos/grupos',
            '/api/catalogos/familias',
            '/api/catalogos/subfamilias',
            '/api/catalogos/tipos',
            '/api/catalogos/formatos',
            '/api/catalogos/generos',
            '/api/catalogos/colores',
            '/api/catalogos/tallas',
        ];

        foreach ($endpoints as $url) {
            $this->getJson($url)->assertOk();
        }
    }

    public function test_catalogos_retornan_datos_insertados(): void
    {
        \App\Models\PteUnidadNegocio::factory()->create(['nombre' => 'Uniformes']);
        \App\Models\PteGenero::factory()->create(['nombre' => 'M']);
        \App\Models\PteColor::factory()->create(['nombre' => 'Rojo']);

        $body = $this->getJson('/api/catalogos/todos')->assertOk()->json();

        $this->assertContains('Uniformes', array_column($body['unidades'], 'nombre'));
        $this->assertContains('Rojo', array_column($body['colores'], 'nombre'));
    }

    public function test_catalogos_usan_cache_en_segunda_llamada(): void
    {
        Cache::flush();
        \App\Models\PteFamilia::factory()->create(['nombre' => 'Ropa']);

        // Primera llamada: llena cache
        $r1 = $this->getJson('/api/catalogos/familias')->assertOk()->json();

        // Insertar más datos (no deberían verse si el cache está activo)
        \App\Models\PteFamilia::factory()->create(['nombre' => 'Equipamiento']);

        // Segunda llamada: desde cache (no ve el nuevo registro)
        $r2 = $this->getJson('/api/catalogos/familias')->assertOk()->json();

        $this->assertSame(array_column($r1, 'nombre'), array_column($r2, 'nombre'));
    }

    public function test_registro_insertado_por_sql_aparece_al_vencer_la_cache(): void
    {
        \App\Models\PteFamilia::factory()->create(['nombre' => 'Ropa']);
        $this->getJson('/api/catalogos/familias')->assertOk();

        // Inserción directa por SQL: no dispara eventos de modelo.
        \Illuminate\Support\Facades\DB::table('pte_familias')->insert([
            'id' => 77, 'nombre' => 'Calzado SQL', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->travel(30)->seconds();
        $this->assertNotContains('Calzado SQL', array_column($this->getJson('/api/catalogos/familias')->json(), 'nombre'));

        $this->travel(31)->seconds();
        $this->assertContains('Calzado SQL', array_column($this->getJson('/api/catalogos/familias')->json(), 'nombre'));
    }

    public function test_comando_refrescar_limpia_la_cache_de_catalogos(): void
    {
        \App\Models\PteFamilia::factory()->create(['nombre' => 'Ropa']);
        $this->getJson('/api/catalogos/todos')->assertOk();
        \App\Models\PteFamilia::factory()->create(['nombre' => 'Equipamiento']);

        $this->artisan('catalogos:refrescar')->assertSuccessful();

        foreach (\App\Http\Controllers\CatalogoController::CACHE_KEYS as $key) {
            $this->assertFalse(Cache::has($key), "La clave {$key} sigue en caché");
        }
        $this->assertContains('Equipamiento', array_column($this->getJson('/api/catalogos/todos')->json('familias'), 'nombre'));
    }

    public function test_subfamilias_incluyen_familia_id(): void
    {
        $familia = \App\Models\PteFamilia::factory()->create();
        \App\Models\PteSubfamilia::factory()->create(['familia_id' => $familia->id]);

        $body = $this->getJson('/api/catalogos/subfamilias')->assertOk()->json();
        $this->assertArrayHasKey('familia_id', $body[0]);
    }
}
