<?php

namespace Tests\Feature;

use App\Models\PteColor;
use App\Models\PteFamilia;
use App\Models\PteFamiliaFormato;
use App\Models\PteFamiliaTipo;
use App\Models\PteGenero;
use App\Models\PteGrpFamilia;
use App\Models\PteMovimiento;
use App\Models\PteOrigen;
use App\Models\PteSku;
use App\Models\PteSkuGenerico;
use App\Models\PteStock;
use App\Models\PteSubfamilia;
use App\Models\PteTalla;
use App\Models\PteUnidadNegocio;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductCatalogLifecycleTest extends TestCase
{
    private function baseCatalogPayload(): array
    {
        return [
            'nombre' => 'Parka Antillanca Térmica',
            'precio_venta' => 45990,
            'unidad_negocio_id' => PteUnidadNegocio::factory()->create(['id' => 1])->id,
            'origen_id' => PteOrigen::factory()->create(['id' => 1])->id,
            'grp_familia_id' => PteGrpFamilia::factory()->create(['id' => 1])->id,
            'familia_id' => PteFamilia::factory()->create(['id' => 1])->id,
            'subfamilia_id' => PteSubfamilia::factory()->create(['id' => 10])->id,
            'familia_tipo_id' => PteFamiliaTipo::factory()->create(['id' => 20])->id,
            'familia_formato_id' => PteFamiliaFormato::factory()->create(['id' => 30])->id,
            'genero_id' => PteGenero::factory()->create(['id' => 1, 'nombre' => 'Hombre'])->id,
            'permite_personalizar' => true,
        ];
    }

    public function test_superadmin_can_create_template_starting_as_draft_without_variants_or_stock(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);
        $payload = $this->baseCatalogPayload();

        $response = $this->postJson('/api/sku-genericos', $payload)
            ->assertStatus(201)
            ->assertJsonPath('data.estado_catalogo', 'borrador')
            ->assertJsonPath('data.activo', false);

        $this->assertDatabaseHas('pte_sku_genericos', [
            'sku_base' => '111110203010000',
            'estado_catalogo' => 'borrador',
            'activo' => false,
        ]);

        // Cero variantes, cero stocks creados
        $this->assertSame(0, PteSku::count());
        $this->assertSame(0, PteStock::count());
    }

    public function test_non_superadmin_roles_receive_403_on_template_creation(): void
    {
        $payload = $this->baseCatalogPayload();

        foreach (['ejecutivo', 'bodega'] as $role) {
            $this->actingAsUser(['role' => $role]);
            $this->postJson('/api/sku-genericos', $payload)->assertForbidden();
        }
    }

    public function test_template_creation_supports_multiple_genders_atomically(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);
        $payload = $this->baseCatalogPayload();
        unset($payload['genero_id']);

        $g1 = PteGenero::query()->firstOrCreate(['id' => 1], ['nombre' => 'Hombre'])->id;
        $g2 = PteGenero::query()->firstOrCreate(['id' => 2], ['nombre' => 'Mujer'])->id;
        $payload['generos'] = [$g1, $g2];

        $response = $this->postJson('/api/sku-genericos', $payload)
            ->assertStatus(201)
            ->assertJsonPath('count', 2);

        $this->assertDatabaseHas('pte_sku_genericos', ['sku_base' => '111110203010000']);
        $this->assertDatabaseHas('pte_sku_genericos', ['sku_base' => '111110203020000']);
        $this->assertSame(0, PteSku::count());
    }

    public function test_publish_without_active_variants_returns_422(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);
        $generic = PteSkuGenerico::query()->create([
            'sku_base' => '111110203010000',
            'nombre' => 'Casco F1',
            'precio_venta' => 99990,
            'estado_catalogo' => 'borrador',
            'activo' => false,
        ]);

        $this->postJson("/api/sku-genericos/{$generic->id}/publish")
            ->assertStatus(422)
            ->assertJsonValidationErrors('publicar');

        $this->assertDatabaseHas('pte_sku_genericos', [
            'id' => $generic->id,
            'estado_catalogo' => 'borrador',
            'activo' => false,
        ]);
    }

    public function test_publish_with_at_least_one_active_variant_succeeds_and_becomes_available_for_quotes(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);
        $color = PteColor::query()->create(['id' => 1, 'nombre' => 'Rojo', 'is_activo' => true]);
        $talla = PteTalla::query()->create(['id' => 1, 'nombre' => 'L', 'orden' => 3, 'is_activo' => true]);

        $generic = PteSkuGenerico::query()->create([
            'sku_base' => '111110203010000',
            'nombre' => 'Casco F1',
            'precio_venta' => 99990,
            'estado_catalogo' => 'borrador',
            'activo' => false,
        ]);

        // Agregar una variante activa
        PteSku::query()->create([
            'sku' => '111110203010101',
            'sku_generico_id' => $generic->id,
            'nombre' => 'Casco F1 Rojo L',
            'color_id' => $color->id,
            'talla_id' => $talla->id,
            'activo' => true,
        ]);

        $this->postJson("/api/sku-genericos/{$generic->id}/publish")
            ->assertOk()
            ->assertJsonPath('data.estado_catalogo', 'publicado')
            ->assertJsonPath('data.activo', true);

        $this->assertDatabaseHas('pte_sku_genericos', [
            'id' => $generic->id,
            'estado_catalogo' => 'publicado',
            'activo' => true,
        ]);

        // Ahora debe aparecer en comercial-skus
        $this->getJson('/api/comercial-skus')
            ->assertOk()
            ->assertJsonFragment(['sku' => '111110203010000']);
    }

    public function test_archive_generic_preserves_stock_movements_and_deactivates_variants(): void
    {
        $user = $this->actingAsUser(['role' => 'superadmin']);
        $color = PteColor::query()->create(['id' => 1, 'nombre' => 'Rojo', 'is_activo' => true]);
        $talla = PteTalla::query()->create(['id' => 1, 'nombre' => 'L', 'orden' => 3, 'is_activo' => true]);

        $generic = PteSkuGenerico::query()->create([
            'sku_base' => '111110203010000',
            'nombre' => 'Chaqueta Bombero',
            'precio_venta' => 70000,
            'estado_catalogo' => 'publicado',
            'activo' => true,
        ]);

        $variant = PteSku::query()->create([
            'sku' => '111110203010101',
            'sku_generico_id' => $generic->id,
            'nombre' => 'Chaqueta Bombero Rojo L',
            'color_id' => $color->id,
            'talla_id' => $talla->id,
            'activo' => true,
        ]);

        PteStock::query()->create([
            'sku' => $variant->sku,
            'cantidad' => 15,
            'reserved_stock' => 3,
            'stock_critico' => 5,
        ]);

        PteMovimiento::query()->create([
            'sku' => $variant->sku,
            'fecha_hora' => now(),
            'tipo_movimiento' => 'INVENTARIO_INICIAL',
            'usuario_id' => $user->id,
            'cantidad' => 15,
            'saldo_anterior' => 0,
            'saldo_nuevo' => 15,
        ]);

        // 1. Consultar preview de impacto
        $this->getJson("/api/sku-genericos/{$generic->id}/archive-preview")
            ->assertOk()
            ->assertJsonPath('variantes_afectadas', 1)
            ->assertJsonPath('stock_fisico_total', 15)
            ->assertJsonPath('stock_reservado', 3);

        // 2. Archivar plantilla con motivo
        $this->deleteJson("/api/sku-genericos/{$generic->id}", [
            'archive_reason' => 'Fin de temporada institucional',
        ])->assertOk();

        // 3. Verificaciones de integridad
        $this->assertDatabaseHas('pte_sku_genericos', [
            'id' => $generic->id,
            'estado_catalogo' => 'archivado',
            'activo' => false,
            'archived_by' => $user->id,
            'archive_reason' => 'Fin de temporada institucional',
        ]);
        $this->assertDatabaseHas('pte_skus', [
            'sku' => $variant->sku,
            'activo' => false,
        ]);
        $this->assertDatabaseHas('pte_stocks', [
            'sku' => $variant->sku,
            'cantidad' => 15,
            'reserved_stock' => 3,
        ]);
        $this->assertDatabaseHas('pte_movimientos', [
            'sku' => $variant->sku,
            'cantidad' => 15,
        ]);

        // Desaparece del cotizador
        $this->getJson('/api/comercial-skus')
            ->assertOk()
            ->assertJsonMissing(['sku' => '111110203010000']);
    }

    public function test_reactivating_generic_resets_to_draft_and_does_not_reactivate_variants(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);
        $color = PteColor::query()->create(['id' => 1, 'nombre' => 'Azul', 'is_activo' => true]);
        $talla = PteTalla::query()->create(['id' => 1, 'nombre' => 'M', 'orden' => 2, 'is_activo' => true]);

        $generic = PteSkuGenerico::query()->create([
            'sku_base' => '111110203010000',
            'nombre' => 'Polera Bomberos',
            'estado_catalogo' => 'archivado',
            'activo' => false,
            'archived_at' => now(),
            'archive_reason' => 'Archivo temporal',
        ]);

        $variant = PteSku::query()->create([
            'sku' => '111110203010101',
            'sku_generico_id' => $generic->id,
            'nombre' => 'Polera Azul M',
            'color_id' => $color->id,
            'talla_id' => $talla->id,
            'activo' => false,
        ]);

        $this->postJson("/api/sku-genericos/{$generic->id}/reactivate")
            ->assertOk()
            ->assertJsonPath('data.estado_catalogo', 'borrador')
            ->assertJsonPath('data.activo', false);

        $this->assertDatabaseHas('pte_sku_genericos', [
            'id' => $generic->id,
            'estado_catalogo' => 'borrador',
            'activo' => false,
            'archived_at' => null,
            'archive_reason' => null,
        ]);

        // La variante permanece archivada
        $this->assertDatabaseHas('pte_skus', [
            'sku' => $variant->sku,
            'activo' => false,
        ]);
    }

    public function test_batch_variant_archive_and_reactivation(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);
        $color = PteColor::query()->create(['id' => 1, 'nombre' => 'Azul', 'is_activo' => true]);
        $talla1 = PteTalla::query()->create(['id' => 1, 'nombre' => 'S', 'orden' => 1, 'is_activo' => true]);
        $talla2 = PteTalla::query()->create(['id' => 2, 'nombre' => 'M', 'orden' => 2, 'is_activo' => true]);

        $generic = PteSkuGenerico::query()->create([
            'sku_base' => '111110203010000',
            'nombre' => 'Guantes Rescate',
            'estado_catalogo' => 'publicado',
            'activo' => true,
        ]);

        $v1 = PteSku::query()->create([
            'sku' => '111110203010101',
            'sku_generico_id' => $generic->id,
            'nombre' => 'Guante S',
            'color_id' => $color->id,
            'talla_id' => $talla1->id,
            'activo' => true,
        ]);
        $v2 = PteSku::query()->create([
            'sku' => '111110203010102',
            'sku_generico_id' => $generic->id,
            'nombre' => 'Guante M',
            'color_id' => $color->id,
            'talla_id' => $talla2->id,
            'activo' => true,
        ]);

        // 1. Archivar en lote ambas variantes
        $this->postJson("/api/sku-genericos/{$generic->id}/variants/archive", [
            'skus' => [$v1->sku, $v2->sku],
        ])->assertOk()->assertJsonPath('archivadas', 2);

        $this->assertDatabaseHas('pte_skus', ['sku' => $v1->sku, 'activo' => false]);
        $this->assertDatabaseHas('pte_skus', ['sku' => $v2->sku, 'activo' => false]);

        // 2. Reactivar selectivamente sólo v1
        $this->postJson("/api/sku-genericos/{$generic->id}/variants/reactivate", [
            'skus' => [$v1->sku],
        ])->assertOk()->assertJsonPath('reactivadas', 1);

        $this->assertDatabaseHas('pte_skus', ['sku' => $v1->sku, 'activo' => true]);
        $this->assertDatabaseHas('pte_skus', ['sku' => $v2->sku, 'activo' => false]);
    }

    public function test_audit_artisan_command_detects_integrity_anomalies_and_exits_with_failure(): void
    {
        // 1. Crear anomalía: variante sin genérico padre (huérfana)
        $color = PteColor::query()->create(['id' => 1, 'nombre' => 'Rojo', 'is_activo' => true]);
        $talla = PteTalla::query()->create(['id' => 1, 'nombre' => 'U', 'orden' => 1, 'is_activo' => true]);

        PteSku::query()->create([
            'sku' => 'HUERFANO_VAR_01',
            'sku_generico_id' => null,
            'nombre' => 'Variante sin Padre',
            'color_id' => $color->id,
            'talla_id' => $talla->id,
            'activo' => true,
        ]);

        $this->artisan('pte:audit-catalog')
            ->expectsOutputToContain('ANOMALÍA CRÍTICA')
            ->assertExitCode(1);
    }

    public function test_catalog_admin_index_supports_pagination_and_state_filtering(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);

        PteSkuGenerico::query()->create([
            'sku_base' => 'BORR_11100000000',
            'nombre' => 'Producto Borrador',
            'estado_catalogo' => 'borrador',
            'activo' => false,
        ]);
        PteSkuGenerico::query()->create([
            'sku_base' => 'PUB_22200000000',
            'nombre' => 'Producto Publicado',
            'estado_catalogo' => 'publicado',
            'activo' => true,
        ]);
        PteSkuGenerico::query()->create([
            'sku_base' => 'ARCH_33300000000',
            'nombre' => 'Producto Archivado',
            'estado_catalogo' => 'archivado',
            'activo' => false,
        ]);

        // Filtrar por borradores
        $this->getJson('/api/sku-genericos/admin?estado=borrador')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.sku_base', 'BORR_11100000000');

        // Filtrar por publicados
        $this->getJson('/api/sku-genericos/admin?estado=publicado')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.sku_base', 'PUB_22200000000');

        // Filtrar por archivados
        $this->getJson('/api/sku-genericos/admin?estado=archivado')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.sku_base', 'ARCH_33300000000');

        // Todos
        $this->getJson('/api/sku-genericos/admin?estado=todos')
            ->assertOk()
            ->assertJsonPath('total', 3);
    }

    public function test_publish_with_activate_variants_flag_auto_activates_variants_and_publishes(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);
        $color = PteColor::query()->create(['id' => 88, 'nombre' => 'Negro', 'is_activo' => true]);
        $talla = PteTalla::query()->create(['id' => 88, 'nombre' => 'XL', 'orden' => 4, 'is_activo' => true]);

        $generic = PteSkuGenerico::query()->create([
            'sku_base' => '111110203099900',
            'nombre' => 'Parka Rescate',
            'precio_venta' => 89990,
            'estado_catalogo' => 'borrador',
            'activo' => false,
        ]);

        $variant = PteSku::query()->create([
            'sku' => '111110203099901',
            'sku_generico_id' => $generic->id,
            'nombre' => 'Parka Rescate Negro XL',
            'color_id' => $color->id,
            'talla_id' => $talla->id,
            'activo' => false, // Inactiva
        ]);

        // Sin activate_variants falla con 422
        $this->postJson("/api/sku-genericos/{$generic->id}/publish")
            ->assertStatus(422);

        // Con activate_variants activa la variante y publica
        $this->postJson("/api/sku-genericos/{$generic->id}/publish", ['activate_variants' => true])
            ->assertOk()
            ->assertJsonPath('data.estado_catalogo', 'publicado')
            ->assertJsonPath('data.activo', true);

        $this->assertDatabaseHas('pte_skus', [
            'sku' => $variant->sku,
            'activo' => true,
        ]);
    }

    public function test_archived_generic_variants_do_not_appear_in_inventario_stock_viewer(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);
        $color = PteColor::query()->create(['id' => 77, 'nombre' => 'Verde', 'is_activo' => true]);
        $talla = PteTalla::query()->create(['id' => 77, 'nombre' => 'M', 'orden' => 2, 'is_activo' => true]);

        $generic = PteSkuGenerico::query()->create([
            'sku_base' => '111110203077700',
            'nombre' => 'Overol Antillanca',
            'precio_venta' => 59990,
            'estado_catalogo' => 'archivado',
            'activo' => false,
        ]);

        $variant = PteSku::query()->create([
            'sku' => '111110203077701',
            'sku_generico_id' => $generic->id,
            'nombre' => 'Overol Antillanca Verde M',
            'color_id' => $color->id,
            'talla_id' => $talla->id,
            'activo' => true, // Aunque la variante estuviera en activo, el genérico archivado debe excluirla
        ]);

        $response = $this->getJson('/api/inventario?search=Overol Antillanca')
            ->assertOk();

        $data = $response->json('pagination.data');
        $skus = collect($data)->pluck('sku')->all();
        $this->assertNotContains('111110203077701', $skus);
    }

    public function test_update_draft_template_preserves_draft_status_and_active_variants(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);
        $color = PteColor::query()->create(['id' => 91, 'nombre' => 'Blanco', 'is_activo' => true]);
        $talla = PteTalla::query()->create(['id' => 91, 'nombre' => '3XL', 'orden' => 6, 'is_activo' => true]);

        $generic = PteSkuGenerico::query()->create([
            'sku_base' => '111110203099990',
            'nombre' => 'Producto Borrador Test',
            'precio_venta' => 15000,
            'estado_catalogo' => 'borrador',
            'activo' => false,
            'permite_personalizar' => true,
        ]);

        $variant = PteSku::query()->create([
            'sku' => '111110203099991',
            'sku_generico_id' => $generic->id,
            'nombre' => 'Producto Borrador Test Blanco 3XL',
            'color_id' => $color->id,
            'talla_id' => $talla->id,
            'activo' => true,
        ]);

        // Simula la llamada del modal al guardar detalles (incluso si enviara activo: false)
        $response = $this->putJson("/api/sku-genericos/{$generic->id}", [
            'nombre' => 'Producto Borrador Test Editado',
            'precio_venta' => 18000,
            'permite_personalizar' => false,
            'activo' => false,
        ])->assertOk();

        // El producto debe seguir siendo borrador, NO archivado
        $this->assertDatabaseHas('pte_sku_genericos', [
            'id' => $generic->id,
            'nombre' => 'Producto Borrador Test Editado',
            'estado_catalogo' => 'borrador',
            'activo' => false,
            'permite_personalizar' => false,
            'archived_at' => null,
        ]);

        // La variante DEBE seguir activa
        $this->assertDatabaseHas('pte_skus', [
            'sku' => $variant->sku,
            'activo' => true,
        ]);

        // Ahora debe poder publicarse directamente sin error 422
        $this->postJson("/api/sku-genericos/{$generic->id}/publish")
            ->assertOk()
            ->assertJsonPath('data.estado_catalogo', 'publicado')
            ->assertJsonPath('data.activo', true);

        $this->assertDatabaseHas('pte_sku_genericos', [
            'id' => $generic->id,
            'estado_catalogo' => 'publicado',
            'activo' => true,
            'permite_personalizar' => false,
        ]);
    }

    public function test_permite_personalizar_can_be_set_to_false_on_creation(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);
        $payload = $this->baseCatalogPayload();
        $payload['permite_personalizar'] = false;

        $response = $this->postJson('/api/sku-genericos', $payload)
            ->assertStatus(201)
            ->assertJsonPath('data.permite_personalizar', false);

        $this->assertDatabaseHas('pte_sku_genericos', [
            'sku_base' => '111110203010000',
            'permite_personalizar' => false,
        ]);
    }
}

