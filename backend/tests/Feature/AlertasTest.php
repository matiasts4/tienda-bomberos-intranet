<?php

namespace Tests\Feature;

use App\Models\PteColor;
use App\Models\PteSku;
use App\Models\PteSkuGenerico;
use App\Models\PteStock;
use App\Models\PteTalla;
use Tests\TestCase;

class AlertasTest extends TestCase
{
    public function test_get_criticos_excludes_archived_products_and_computes_kpis(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);

        $color = PteColor::query()->create(['id' => 1, 'nombre' => 'Azul', 'is_activo' => true]);
        $talla = PteTalla::query()->create(['id' => 1, 'nombre' => 'M', 'orden' => 2, 'is_activo' => true]);

        // 1. Producto Publicado y Variante Activa con Stock 0 (Crítico válido)
        $publicado = PteSkuGenerico::query()->create([
            'sku_base' => '111110203010000',
            'nombre' => 'Parka Rescate Publicada',
            'estado_catalogo' => 'publicado',
            'activo' => true,
        ]);
        $skuPub = PteSku::query()->create([
            'sku' => '111110203010101',
            'sku_generico_id' => $publicado->id,
            'nombre' => 'Parka Rescate Azul M',
            'color_id' => $color->id,
            'talla_id' => $talla->id,
            'activo' => true,
        ]);
        PteStock::query()->create([
            'sku' => $skuPub->sku,
            'cantidad' => 0,
            'stock_critico' => 10,
        ]);

        // 2. Producto Archivado con Stock 0 (Debe ser EXCLUIDO de alertas)
        $archivado = PteSkuGenerico::query()->create([
            'sku_base' => '111110203020000',
            'nombre' => 'Parka Antigua Archivada',
            'estado_catalogo' => 'archivado',
            'activo' => false,
        ]);
        $skuArch = PteSku::query()->create([
            'sku' => '111110203020101',
            'sku_generico_id' => $archivado->id,
            'nombre' => 'Parka Antigua Azul M',
            'color_id' => $color->id,
            'talla_id' => $talla->id,
            'activo' => false,
        ]);
        PteStock::query()->create([
            'sku' => $skuArch->sku,
            'cantidad' => 0,
            'stock_critico' => 10,
        ]);

        $response = $this->getJson('/api/alertas/criticos')
            ->assertOk()
            ->assertJsonStructure([
                'kpis' => [
                    'total_pte',
                    'agotados_pte',
                    'riesgo_inminente_pte',
                    'stock_bajo_pte',
                    'estancados_pte',
                    'deficit_unidades_pte',
                    'total_mp',
                    'criticos_mp',
                    'deficit_unidades_mp',
                ],
                'pte' => [
                    'current_page',
                    'data',
                    'last_page',
                    'total',
                ],
                'mp',
                'config',
            ]);

        $data = $response->json('pte.data');
        $skus = collect($data)->pluck('sku')->all();

        // Verifica que el SKU publicado sí aparece
        $this->assertContains('111110203010101', $skus);
        // Verifica que el SKU archivado NO aparece
        $this->assertNotContains('111110203020101', $skus);

        // Verifica KPIs
        $this->assertEquals(1, $response->json('kpis.total_pte'));
        $this->assertEquals(1, $response->json('kpis.agotados_pte'));
        $this->assertEquals(10, $response->json('kpis.deficit_unidades_pte'));
    }

    public function test_get_criticos_supports_search_and_filtro_severidad(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);

        $color = PteColor::query()->create(['id' => 10, 'nombre' => 'Negro', 'is_activo' => true]);
        $talla = PteTalla::query()->create(['id' => 10, 'nombre' => 'L', 'orden' => 3, 'is_activo' => true]);

        $generic = PteSkuGenerico::query()->create([
            'sku_base' => '111110203030000',
            'nombre' => 'Overol Forestal Pro',
            'estado_catalogo' => 'publicado',
            'activo' => true,
        ]);

        $v1 = PteSku::query()->create([
            'sku' => '111110203030101',
            'sku_generico_id' => $generic->id,
            'nombre' => 'Overol Forestal Pro Negro L',
            'color_id' => $color->id,
            'talla_id' => $talla->id,
            'activo' => true,
        ]);

        PteStock::query()->create([
            'sku' => $v1->sku,
            'cantidad' => 0,
            'stock_critico' => 5,
        ]);

        // Búsqueda coincidente
        $resFound = $this->getJson('/api/alertas/criticos?search=Forestal')
            ->assertOk();
        $this->assertEquals(1, $resFound->json('pte.total'));

        // Búsqueda no coincidente
        $resNone = $this->getJson('/api/alertas/criticos?search=InexistenteXYZ')
            ->assertOk();
        $this->assertEquals(0, $resNone->json('pte.total'));

        // Filtro severidad: agotados
        $resAgotados = $this->getJson('/api/alertas/criticos?filtro_severidad=agotados')
            ->assertOk();
        $this->assertEquals(1, $resAgotados->json('pte.total'));

        // Filtro severidad: criticos (< 7 días, pero con stock > 0)
        $resCriticos = $this->getJson('/api/alertas/criticos?filtro_severidad=criticos')
            ->assertOk();
        $this->assertEquals(0, $resCriticos->json('pte.total'));
    }
}
