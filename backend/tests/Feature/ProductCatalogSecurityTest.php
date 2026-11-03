<?php

namespace Tests\Feature;

use App\Models\PteColor;
use App\Models\PteSku;
use App\Models\PteSkuGenerico;
use App\Models\PteStock;
use App\Models\PteTalla;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class ProductCatalogSecurityTest extends TestCase
{
    public function test_commercial_and_warehouse_roles_cannot_call_any_product_catalog_mutation(): void
    {
        $mutations = [
            ['postJson', '/api/productos', []],
            ['postJson', '/api/productos/bulk', []],
            ['putJson', '/api/productos/111111111110101', []],
            ['deleteJson', '/api/productos/111111111110101', []],
            ['patchJson', '/api/productos/111111111110101/deactivate', []],
            ['postJson', '/api/sku-genericos', []],
            ['putJson', '/api/sku-genericos/1', []],
            ['deleteJson', '/api/sku-genericos/1', []],
            ['postJson', '/api/sku-genericos/1/publish', []],
            ['postJson', '/api/sku-genericos/1/reactivate', []],
            ['postJson', '/api/sku-genericos/1/upload', []],
            ['postJson', '/api/sku-genericos/1/add-variants', []],
            ['postJson', '/api/sku-genericos/1/variants/archive', []],
            ['postJson', '/api/sku-genericos/1/variants/reactivate', []],
        ];

        foreach (['ejecutivo', 'bodega'] as $role) {
            $user = $this->actingAsUser(['role' => $role]);
            Sanctum::actingAs($user);

            foreach ($mutations as [$method, $uri, $payload]) {
                $this->{$method}($uri, $payload)->assertForbidden();
            }
        }
    }

    public function test_delete_variant_archives_without_deleting_product_or_stock(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);
        [$generic, $color, $size] = $this->catalogFixture();
        $variant = $this->createVariant($generic, $color, $size, true, true);

        $this->deleteJson("/api/productos/{$variant->sku}")
            ->assertOk()
            ->assertJson(['archived' => true]);

        $this->assertDatabaseHas('pte_skus', ['sku' => $variant->sku, 'activo' => false]);
        $this->assertDatabaseHas('pte_stocks', ['sku' => $variant->sku, 'cantidad' => 7]);
    }

    public function test_archiving_generic_atomically_archives_all_variants_and_preserves_stock(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);
        [$generic, $color, $size] = $this->catalogFixture();
        $first = $this->createVariant($generic, $color, $size, true, true);
        $otherSize = PteTalla::query()->create(['id' => 2, 'nombre' => 'M', 'orden' => 2, 'is_activo' => true]);
        $second = $this->createVariant($generic, $color, $otherSize, true, true);

        $this->deleteJson("/api/sku-genericos/{$generic->id}")->assertOk();

        $this->assertDatabaseHas('pte_sku_genericos', ['id' => $generic->id, 'activo' => false]);
        $this->assertDatabaseHas('pte_skus', ['sku' => $first->sku, 'activo' => false]);
        $this->assertDatabaseHas('pte_skus', ['sku' => $second->sku, 'activo' => false]);
        $this->assertDatabaseCount('pte_stocks', 2);
    }

    public function test_archived_variant_is_reactivated_and_missing_stock_is_repaired(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);
        [$generic, $color, $size] = $this->catalogFixture();
        $variant = $this->createVariant($generic, $color, $size, false, false);

        $this->postJson("/api/sku-genericos/{$generic->id}/add-variants", [
            'colores' => [$color->id],
            'tallas' => [$size->id],
        ])->assertOk()->assertJsonPath('resumen.reactivados', 1);

        $this->assertDatabaseHas('pte_skus', ['sku' => $variant->sku, 'activo' => true]);
        $this->assertDatabaseHas('pte_stocks', ['sku' => $variant->sku, 'cantidad' => 0]);
    }

    public function test_adding_same_active_variant_is_idempotent(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);
        [$generic, $color, $size] = $this->catalogFixture();
        $this->createVariant($generic, $color, $size, true, true);
        $payload = ['colores' => [$color->id], 'tallas' => [$size->id]];

        $this->postJson("/api/sku-genericos/{$generic->id}/add-variants", $payload)
            ->assertOk()
            ->assertJsonPath('resumen.duplicados', 1);
        $this->postJson("/api/sku-genericos/{$generic->id}/add-variants", $payload)
            ->assertOk()
            ->assertJsonPath('resumen.duplicados', 1);

        $this->assertDatabaseCount('pte_skus', 1);
        $this->assertDatabaseCount('pte_stocks', 1);
    }

    public function test_sku_collision_with_another_generic_returns_409_without_partial_writes(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);
        [$generic, $color, $size] = $this->catalogFixture();
        $other = PteSkuGenerico::query()->create([
            'sku_base' => '222222222220000',
            'nombre' => 'Otro producto',
            'genero_id' => 1,
            'activo' => true,
        ]);
        PteSku::query()->create([
            'sku' => '111111111110101',
            'sku_generico_id' => $other->id,
            'nombre' => 'Colisión',
            'precio_venta' => 1000,
            'color_id' => $color->id,
            'talla_id' => $size->id,
            'activo' => true,
        ]);

        $this->postJson("/api/sku-genericos/{$generic->id}/add-variants", [
            'colores' => [$color->id],
            'tallas' => [$size->id],
        ])->assertConflict()->assertJsonFragment(['message' => 'El SKU 111111111110101 ya pertenece a otro producto o combinación.']);

        $this->assertDatabaseCount('pte_skus', 1);
        $this->assertDatabaseCount('pte_stocks', 0);
    }

    public function test_noncanonical_sku_for_same_combination_returns_409_without_reactivation(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);
        [$generic, $color, $size] = $this->catalogFixture();
        $variant = PteSku::query()->create([
            'sku' => 'LEGACY-COMBINATION',
            'sku_generico_id' => $generic->id,
            'nombre' => 'Variante histórica',
            'precio_venta' => $generic->precio_venta,
            'color_id' => $color->id,
            'talla_id' => $size->id,
            'activo' => false,
        ]);

        $this->postJson("/api/sku-genericos/{$generic->id}/add-variants", [
            'colores' => [$color->id],
            'tallas' => [$size->id],
        ])->assertConflict()->assertJsonFragment([
            'message' => 'La combinación seleccionada ya existe con el SKU no canónico LEGACY-COMBINATION.',
        ]);

        $this->assertDatabaseHas('pte_skus', ['sku' => $variant->sku, 'activo' => false]);
        $this->assertDatabaseMissing('pte_skus', ['sku' => '111111111110101']);
        $this->assertDatabaseCount('pte_stocks', 0);
    }

    public function test_variant_creation_rolls_back_and_hides_internal_exception_when_stock_creation_fails(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);
        [$generic, $color, $size] = $this->catalogFixture();
        $eventName = 'eloquent.creating: '.PteStock::class;
        Event::listen($eventName, function () {
            throw new RuntimeException('sensitive database detail');
        });

        try {
            $response = $this->postJson("/api/sku-genericos/{$generic->id}/add-variants", [
                'colores' => [$color->id],
                'tallas' => [$size->id],
            ]);
        } finally {
            Event::forget($eventName);
        }

        $response->assertStatus(500)
            ->assertJson(['message' => 'No se pudieron agregar las variantes. Inténtalo nuevamente.'])
            ->assertJsonMissing(['message' => 'sensitive database detail']);
        $this->assertDatabaseCount('pte_skus', 0);
        $this->assertDatabaseCount('pte_stocks', 0);
    }

    public function test_inactive_catalog_ids_are_rejected(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);
        [$generic, $color, $size] = $this->catalogFixture();
        $color->update(['is_activo' => false]);

        $this->postJson("/api/sku-genericos/{$generic->id}/add-variants", [
            'colores' => [$color->id],
            'tallas' => [$size->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('colores');

        $this->assertDatabaseCount('pte_skus', 0);
    }

    public function test_inactive_generic_rejects_variants_and_never_leaves_active_children(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);
        [$generic, $color, $size] = $this->catalogFixture();
        $generic->update(['activo' => false]);

        $this->postJson("/api/sku-genericos/{$generic->id}/add-variants", [
            'colores' => [$color->id],
            'tallas' => [$size->id],
        ])->assertConflict();

        $this->assertDatabaseMissing('pte_skus', [
            'sku_generico_id' => $generic->id,
            'activo' => true,
        ]);
    }

    public function test_reactivating_generic_does_not_silently_reactivate_variants(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);
        [$generic, $color, $size] = $this->catalogFixture();
        $variant = $this->createVariant($generic, $color, $size, false, true);
        $generic->update(['activo' => false]);

        // Un archivado no se publica por edición: quedaría activo con 0 variantes activas.
        $this->putJson("/api/sku-genericos/{$generic->id}", ['activo' => true])
            ->assertStatus(422)
            ->assertJsonValidationErrors('publicar');
        $this->assertDatabaseHas('pte_sku_genericos', ['id' => $generic->id, 'activo' => false, 'estado_catalogo' => 'archivado']);

        // La vía formal reactiva como borrador y tampoco reactiva variantes.
        $this->postJson("/api/sku-genericos/{$generic->id}/reactivate")->assertOk();

        $this->assertDatabaseHas('pte_sku_genericos', ['id' => $generic->id, 'estado_catalogo' => 'borrador']);
        $this->assertDatabaseHas('pte_skus', ['sku' => $variant->sku, 'activo' => false]);
    }

    public function test_archived_variant_remains_addressable_by_history_but_is_excluded_from_active_inventory(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);
        [$generic, $color, $size] = $this->catalogFixture();
        $variant = $this->createVariant($generic, $color, $size, false, true);

        $this->getJson("/api/productos/{$variant->sku}")
            ->assertOk()
            ->assertJsonPath('sku', $variant->sku)
            ->assertJsonPath('activo', false);
        $this->getJson('/api/inventario')
            ->assertOk()
            ->assertJsonCount(0, 'pagination.data');
    }

    /** @return array{PteSkuGenerico, PteColor, PteTalla} */
    private function catalogFixture(): array
    {
        $color = PteColor::query()->create(['id' => 1, 'nombre' => 'Azul', 'is_activo' => true]);
        $size = PteTalla::query()->create(['id' => 1, 'nombre' => 'S', 'orden' => 1, 'is_activo' => true]);
        $generic = PteSkuGenerico::query()->create([
            'sku_base' => '111111111110000',
            'nombre' => 'Camisa Oxford',
            'precio_venta' => 14990,
            'genero_id' => 1,
            'activo' => true,
        ]);

        return [$generic, $color, $size];
    }

    private function createVariant(
        PteSkuGenerico $generic,
        PteColor $color,
        PteTalla $size,
        bool $active,
        bool $withStock
    ): PteSku {
        $sku = substr($generic->sku_base, 0, 11)
            .str_pad((string) $color->id, 2, '0', STR_PAD_LEFT)
            .str_pad((string) $size->id, 2, '0', STR_PAD_LEFT);
        $variant = PteSku::query()->create([
            'sku' => $sku,
            'sku_generico_id' => $generic->id,
            'nombre' => "{$generic->nombre} {$color->nombre} {$size->nombre}",
            'precio_venta' => $generic->precio_venta,
            'color_id' => $color->id,
            'talla_id' => $size->id,
            'activo' => $active,
        ]);

        if ($withStock) {
            PteStock::query()->create([
                'sku' => $variant->sku,
                'cantidad' => 7,
                'reserved_stock' => 2,
                'stock_critico' => 5,
            ]);
        }

        return $variant;
    }
}
