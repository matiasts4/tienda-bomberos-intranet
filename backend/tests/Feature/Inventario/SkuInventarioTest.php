<?php

namespace Tests\Feature\Inventario;

use App\Models\PteSku;
use App\Models\PteSkuGenerico;
use App\Models\PteStock;
use Tests\TestCase;

class SkuInventarioTest extends TestCase
{
    public function test_reserva_agrupa_por_identidad_y_carga_solo_variantes_activas_de_la_plantilla(): void
    {
        $this->actingAsEjecutivo();
        $primera = PteSkuGenerico::create(['sku_base' => '111440000000000', 'nombre' => 'Camisa Oxford', 'activo' => true, 'estado_catalogo' => 'publicado']);
        $segunda = PteSkuGenerico::create(['sku_base' => '111440000010000', 'nombre' => 'Camisa Oxford', 'activo' => true, 'estado_catalogo' => 'publicado']);
        $activa = PteSku::factory()->create(['sku_generico_id' => $primera->id, 'activo' => true]);
        $inactiva = PteSku::factory()->create(['sku_generico_id' => $primera->id, 'activo' => false]);
        $otra = PteSku::factory()->create(['sku_generico_id' => $segunda->id, 'activo' => true]);
        foreach ([$activa, $inactiva, $otra] as $sku) {
            PteStock::factory()->conCantidad(5)->create(['sku' => $sku->sku]);
        }

        $response = $this->getJson('/api/inventario?sku_generico_id='.$primera->id)->assertOk();
        $response->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('pagination.data.0.sku', $activa->sku)
            ->assertJsonPath('pagination.data.0.sku_generico_id', $primera->id)
            ->assertJsonPath('pagination.data.0.nombre_generico', 'Camisa Oxford');

        $primera->update(['estado_catalogo' => 'archivado']);
        $this->getJson('/api/inventario?sku_generico_id='.$primera->id)->assertOk()->assertJsonPath('pagination.total', 0);
    }

    public function test_inventario_requiere_autenticacion(): void
    {
        $this->getJson('/api/inventario')->assertUnauthorized();
    }

    public function test_inventario_retorna_estructura_esperada(): void
    {
        $this->actingAsAdmin();
        $sku = PteSku::factory()->create(['activo' => true]);
        PteStock::factory()->conCantidad(10)->create(['sku' => $sku->sku]);

        $response = $this->getJson('/api/inventario')->assertOk();

        $response->assertJsonStructure(['pagination', 'stats']);
        $response->assertJsonStructure([
            'stats' => ['total_skus', 'agotados', 'criticos', 'bajo_stock'],
        ]);
    }

    public function test_inventario_excluye_skus_inactivos(): void
    {
        $this->actingAsAdmin();
        $activo   = PteSku::factory()->create(['activo' => true]);
        $inactivo = PteSku::factory()->create(['activo' => false]);
        PteStock::factory()->conCantidad(5)->create(['sku' => $activo->sku]);
        PteStock::factory()->conCantidad(5)->create(['sku' => $inactivo->sku]);

        $response = $this->getJson('/api/inventario')->assertOk();
        $skus = array_column($response->json('pagination.data'), 'sku');

        $this->assertContains($activo->sku, $skus);
        $this->assertNotContains($inactivo->sku, $skus);
    }

    public function test_inventario_busca_por_sku_y_nombre(): void
    {
        $this->actingAsAdmin();
        $sku1 = PteSku::factory()->create(['sku' => 'POLERA00000001', 'nombre' => 'Polera Azul', 'activo' => true]);
        $sku2 = PteSku::factory()->create(['activo' => true]);
        PteStock::factory()->conCantidad(1)->create(['sku' => $sku1->sku]);
        PteStock::factory()->conCantidad(1)->create(['sku' => $sku2->sku]);

        $response = $this->getJson('/api/inventario?search=Polera')->assertOk();
        $this->assertSame(1, $response->json('pagination.total'));
        $this->assertSame('POLERA00000001', $response->json('pagination.data.0.sku'));
    }

    public function test_inventario_busca_palabras_separadas_en_cualquier_orden(): void
    {
        $this->actingAsAdmin();
        $coincide = PteSku::factory()->create([
            'sku' => 'CAMISA00000001',
            'nombre' => 'Camisa Oxford MC Hombre',
            'activo' => true,
        ]);
        $noCoincide = PteSku::factory()->create([
            'sku' => 'CAMISA00000002',
            'nombre' => 'Camisa Oxford ML Hombre',
            'activo' => true,
        ]);
        PteStock::factory()->conCantidad(5)->create(['sku' => $coincide->sku]);
        PteStock::factory()->conCantidad(5)->create(['sku' => $noCoincide->sku]);

        $response = $this->getJson('/api/inventario?search=camisa%20MC')->assertOk();
        $skus = array_column($response->json('pagination.data'), 'sku');

        $this->assertContains($coincide->sku, $skus);
        $this->assertNotContains($noCoincide->sku, $skus);
    }

    public function test_inventario_entiende_abreviaturas_sin_confundir_mc_y_ml(): void
    {
        $this->actingAsAdmin();
        $mangaCorta = PteSku::factory()->create([
            'sku' => 'BLUSA000000001',
            'nombre' => 'Blusa Oxford Manga Corta Mujer',
            'activo' => true,
        ]);
        $mangaLarga = PteSku::factory()->create([
            'sku' => 'BLUSA000000002',
            'nombre' => 'Blusa Oxford Manga Larga Mujer',
            'activo' => true,
        ]);
        PteStock::factory()->conCantidad(5)->create(['sku' => $mangaCorta->sku]);
        PteStock::factory()->conCantidad(5)->create(['sku' => $mangaLarga->sku]);

        $response = $this->getJson('/api/inventario?search=blusa%20m%2Fc')->assertOk();
        $skus = array_column($response->json('pagination.data'), 'sku');

        $this->assertContains($mangaCorta->sku, $skus);
        $this->assertNotContains($mangaLarga->sku, $skus);
    }

    public function test_reporte_usa_la_misma_busqueda_inteligente(): void
    {
        $this->actingAsAdmin();
        $sku = PteSku::factory()->create([
            'sku' => 'CHAQUETA000001',
            'nombre' => 'Chaqueta Forestal ML Hombre',
            'activo' => true,
        ]);
        PteStock::factory()->conCantidad(5)->create(['sku' => $sku->sku]);

        $response = $this->getJson('/api/inventario/reporte?search=chaqueta%20hombre')->assertOk();

        $this->assertSame([$sku->sku], array_column($response->json(), 'sku'));
    }

    public function test_inventario_filtra_agotados(): void
    {
        $this->actingAsAdmin();
        $con    = PteSku::factory()->create(['activo' => true]);
        $sinStock = PteSku::factory()->create(['activo' => true]);
        PteStock::factory()->conCantidad(10)->create(['sku' => $con->sku]);
        PteStock::factory()->agotado()->create(['sku' => $sinStock->sku]);

        $response = $this->getJson('/api/inventario?stock_filter=agotados')->assertOk();
        $skus = array_column($response->json('pagination.data'), 'sku');
        $this->assertContains($sinStock->sku, $skus);
        $this->assertNotContains($con->sku, $skus);
    }

    public function test_inventario_filtra_criticos(): void
    {
        $this->actingAsAdmin();
        $critico = PteSku::factory()->create(['activo' => true]);
        $normal  = PteSku::factory()->create(['activo' => true]);
        PteStock::factory()->create(['sku' => $critico->sku, 'cantidad' => 2, 'stock_critico' => 10]);
        PteStock::factory()->create(['sku' => $normal->sku, 'cantidad' => 50, 'stock_critico' => 5]);

        $response = $this->getJson('/api/inventario?stock_filter=criticos')->assertOk();
        $skus = array_column($response->json('pagination.data'), 'sku');
        $this->assertContains($critico->sku, $skus);
        $this->assertNotContains($normal->sku, $skus);
    }

    public function test_reporte_inventario_retorna_array_plano(): void
    {
        $this->actingAsAdmin();
        $sku = PteSku::factory()->create(['activo' => true]);
        PteStock::factory()->conCantidad(3)->create(['sku' => $sku->sku]);

        $response = $this->getJson('/api/inventario/reporte')->assertOk();

        $this->assertIsArray($response->json());
        $first = $response->json(0);
        $this->assertArrayHasKey('sku', $first);
        $this->assertArrayHasKey('cantidad_fisica', $first);
        $this->assertArrayHasKey('estado', $first);
    }

    public function test_reporte_marca_estado_disponible_o_sin_stock(): void
    {
        $this->actingAsAdmin();
        $conStock     = PteSku::factory()->create(['activo' => true]);
        $sinStock     = PteSku::factory()->create(['activo' => true]);
        PteStock::factory()->conCantidad(5)->create(['sku' => $conStock->sku]);
        PteStock::factory()->agotado()->create(['sku' => $sinStock->sku]);

        $body = $this->getJson('/api/inventario/reporte')->assertOk()->json();
        $find = fn($s) => collect($body)->firstWhere('sku', $s);

        $this->assertSame('Disponible', $find($conStock->sku)['estado']);
        $this->assertSame('Sin Stock', $find($sinStock->sku)['estado']);
    }
}
