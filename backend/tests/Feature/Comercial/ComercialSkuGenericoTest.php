<?php

namespace Tests\Feature\Comercial;

use App\Models\PteColor;
use App\Models\PteSku;
use App\Models\PteSkuGenerico;
use App\Models\PteTalla;
use Tests\TestCase;

class ComercialSkuGenericoTest extends TestCase
{
    private function createPublishedGeneric(string $skuBase, string $nombre, bool $activeVariant = true): PteSkuGenerico
    {
        $color = PteColor::query()->firstOrCreate(['id' => 1], ['nombre' => 'Negro', 'is_activo' => true]);
        $talla = PteTalla::query()->firstOrCreate(['id' => 1], ['nombre' => 'M', 'orden' => 1, 'is_activo' => true]);

        $generic = PteSkuGenerico::create([
            'sku_base' => $skuBase,
            'nombre' => $nombre,
            'estado_catalogo' => 'publicado',
            'activo' => true,
        ]);

        if ($activeVariant) {
            PteSku::create([
                'sku' => $skuBase . '0101',
                'sku_generico_id' => $generic->id,
                'nombre' => $nombre . ' Variante',
                'color_id' => $color->id,
                'talla_id' => $talla->id,
                'activo' => true,
            ]);
        }

        return $generic;
    }

    public function test_lista_skus_genericos_ordenados_por_nombre(): void
    {
        $this->actingAsAdmin();
        $this->createPublishedGeneric('Z0010000000', 'Zapato');
        $this->createPublishedGeneric('A0010000000', 'Arnés');
        $this->createPublishedGeneric('C0010000000', 'Casco');

        $response = $this->getJson('/api/comercial-skus')->assertOk();

        $nombres = array_column($response->json(), 'nombre');
        $this->assertSame(['Arnés', 'Casco', 'Zapato'], $nombres);
    }

    public function test_filtra_por_search_en_sku_o_nombre(): void
    {
        $this->actingAsAdmin();
        $this->createPublishedGeneric('CASC1230000', 'Casco Estructural');
        $this->createPublishedGeneric('BTAS9990000', 'Botas');
        $this->createPublishedGeneric('XYZ00000000', 'Casco Forestal');

        // Search por nombre
        $response = $this->getJson('/api/comercial-skus?search=Casco')->assertOk();
        $this->assertCount(2, $response->json());

        // Search por sku_base
        $response = $this->getJson('/api/comercial-skus?search=BTAS')->assertOk();
        $this->assertCount(1, $response->json());
        $this->assertSame('Botas', $response->json('0.nombre'));
    }

    public function test_borradores_y_productos_sin_variantes_activas_no_aparecen_en_comercial(): void
    {
        $this->actingAsAdmin();

        // 1. Borrador con variante activa
        $color = PteColor::query()->firstOrCreate(['id' => 1], ['nombre' => 'Negro', 'is_activo' => true]);
        $talla = PteTalla::query()->firstOrCreate(['id' => 1], ['nombre' => 'M', 'orden' => 1, 'is_activo' => true]);

        $borrador = PteSkuGenerico::create([
            'sku_base' => 'BORR0000000',
            'nombre' => 'Plantilla Borrador',
            'estado_catalogo' => 'borrador',
            'activo' => false,
        ]);
        PteSku::create([
            'sku' => 'BORR00000000101',
            'sku_generico_id' => $borrador->id,
            'nombre' => 'Borrador Variante',
            'color_id' => $color->id,
            'talla_id' => $talla->id,
            'activo' => true,
        ]);

        // 2. Publicado pero sin variantes activas
        $this->createPublishedGeneric('PUB_SIN_VAR', 'Publicado Sin Variantes', false);

        // 3. Publicado con variante activa
        $this->createPublishedGeneric('PUB_OK', 'Producto Listo para Cotizar', true);

        $response = $this->getJson('/api/comercial-skus')->assertOk();
        $skus = array_column($response->json(), 'sku');

        $this->assertSame(['PUB_OK'], $skus);
    }

    public function test_lista_vacia_si_no_hay_skus(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/comercial-skus')
            ->assertOk()
            ->assertExactJson([]);
    }
}
