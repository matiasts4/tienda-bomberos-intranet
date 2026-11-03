<?php

namespace Tests\Feature\Inventario;

use App\Models\PteSku;
use App\Models\PteSkuGenerico;
use App\Models\PteStock;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BulkProductCreationTest extends TestCase
{
    private function payload(): array
    {
        $data = ['nombre' => 'Producto base de prueba', 'precio_venta' => 15000, 'stock_critico' => 0];
        foreach ([
            'unidad_negocio_id' => \App\Models\PteUnidadNegocio::class,
            'origen_id' => \App\Models\PteOrigen::class,
            'grp_familia_id' => \App\Models\PteGrpFamilia::class,
            'familia_id' => \App\Models\PteFamilia::class,
            'subfamilia_id' => \App\Models\PteSubfamilia::class,
            'familia_tipo_id' => \App\Models\PteFamiliaTipo::class,
            'familia_formato_id' => \App\Models\PteFamiliaFormato::class,
        ] as $key => $class) $data[$key] = $class::factory()->create()->id;
        $data['generos'] = [\App\Models\PteGenero::factory()->create(['nombre' => 'Unisex'])->id];
        $data['colores'] = \App\Models\PteColor::factory()->count(2)->create()->pluck('id')->all();
        $data['tallas'] = \App\Models\PteTalla::factory()->count(2)->create()->pluck('id')->all();
        return $data;
    }

    public function test_bulk_creates_catalogue_base_linked_variants_and_zero_stock(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);
        $data = $this->payload();
        $this->postJson('/api/productos/bulk', $data)->assertCreated()->assertJsonPath('resumen.creados', 4);
        $base = PteSkuGenerico::sole();
        $this->assertSame($data['nombre'], $base->nombre);
        $this->assertSame(4, PteSku::where('sku_generico_id', $base->id)->count());
        $this->assertSame(4, PteStock::where('cantidad', 0)->where('stock_critico', 0)->count());
    }

    public function test_existing_base_cannot_be_expanded_using_create_modal_endpoint(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);
        $data = $this->payload();
        $this->postJson('/api/productos/bulk', $data)->assertCreated();
        $data['colores'][] = \App\Models\PteColor::factory()->create()->id;
        $level = DB::transactionLevel();
        $response = $this->postJson('/api/productos/bulk', $data)->assertStatus(422);
        $this->assertStringContainsString('usa el Catálogo', $response->json('message'));
        $this->assertSame(4, PteSku::count());
        $this->assertSame(1, PteSkuGenerico::count());
        $this->assertSame($level, DB::transactionLevel());
    }

    public function test_bulk_creation_is_superadmin_only(): void
    {
        $this->postJson('/api/productos/bulk', [])->assertUnauthorized();
        foreach (['ejecutivo', 'bodega'] as $role) {
            $this->actingAsUser(['role' => $role]);
            $this->postJson('/api/productos/bulk', [])->assertForbidden();
        }
    }
}
