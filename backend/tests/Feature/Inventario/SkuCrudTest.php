<?php

namespace Tests\Feature\Inventario;

use App\Models\PteMovimiento;
use App\Models\PteSku;
use App\Models\PteStock;
use Tests\TestCase;

class SkuCrudTest extends TestCase
{
    // ── helpers ──────────────────────────────────────────────────────────

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'sku'               => 'TST' . str_pad((string) rand(1, 99999), 5, '0', STR_PAD_LEFT),
            'nombre'            => 'Casco Test',
            'precio_venta'      => 15000,
            'cantidad'          => 0,
            'stock_critico'     => 5,
            'unidad_negocio_id' => \App\Models\PteUnidadNegocio::factory()->create()->id,
            'origen_id'         => \App\Models\PteOrigen::factory()->create()->id,
            'grp_familia_id'    => \App\Models\PteGrpFamilia::factory()->create()->id,
            'familia_id'        => \App\Models\PteFamilia::factory()->create()->id,
            'subfamilia_id'     => \App\Models\PteSubfamilia::factory()->create()->id,
            'familia_tipo_id'   => \App\Models\PteFamiliaTipo::factory()->create()->id,
            'familia_formato_id'=> \App\Models\PteFamiliaFormato::factory()->create()->id,
            'genero_id'         => \App\Models\PteGenero::factory()->create()->id,
            'color_id'          => \App\Models\PteColor::factory()->create()->id,
            'talla_id'          => \App\Models\PteTalla::factory()->create()->id,
        ], $overrides);
    }

    // ── store ─────────────────────────────────────────────────────────────

    public function test_store_requiere_autenticacion(): void
    {
        $this->postJson('/api/productos', [])->assertUnauthorized();
    }

    public function test_store_falla_sin_nombre(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);
        $payload = $this->payload();
        unset($payload['nombre']);

        $this->postJson('/api/productos', $payload)->assertStatus(422);
    }

    public function test_store_falla_sin_sku(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);
        $payload = $this->payload();
        unset($payload['sku']);
        $transactionLevel = \Illuminate\Support\Facades\DB::transactionLevel();
        $this->postJson('/api/productos', $payload)->assertStatus(400);
        $this->assertSame($transactionLevel, \Illuminate\Support\Facades\DB::transactionLevel());
    }

    public function test_store_crea_producto_stock_y_movimiento_inicial(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);
        // Usar género 'Generico' para que el controller no concatene sufijo al nombre
        $genero = \App\Models\PteGenero::factory()->create(['nombre' => 'Generico']);
        $payload = $this->payload([
            'cantidad' => 10,
            'sku' => 'SKUINICIAL01234',
            'genero_id' => $genero->id,
        ]);

        $this->postJson('/api/productos', $payload)->assertCreated()
            ->assertJsonPath('message', 'Producto creado con éxito');

        $this->assertDatabaseHas('pte_skus', ['sku' => 'SKUINICIAL01234', 'nombre' => 'Casco Test']);
        $this->assertDatabaseHas('pte_stocks', ['sku' => 'SKUINICIAL01234', 'cantidad' => 10]);
        $this->assertDatabaseHas('pte_movimientos', [
            'sku' => 'SKUINICIAL01234',
            'tipo_movimiento' => 'INVENTARIO_INICIAL',
            'cantidad' => 10,
        ]);
    }

    public function test_store_sin_cantidad_no_crea_movimiento_inicial(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);
        $payload = $this->payload(['cantidad' => 0, 'sku' => 'SKUSINMOV000001']);

        $this->postJson('/api/productos', $payload)->assertCreated();
        $this->assertSame(0, PteMovimiento::count());
    }

    public function test_store_rechaza_sku_duplicado(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);
        $payload = $this->payload(['sku' => 'SKUDUPLICADO001']);

        $this->postJson('/api/productos', $payload)->assertCreated();
        $this->postJson('/api/productos', $payload)->assertStatus(422)
            ->assertJsonPath('message', '¡Atención! El SKU SKUDUPLICADO001 ya existe en el inventario. No se pueden crear duplicados.');
    }

    // ── show ──────────────────────────────────────────────────────────────

    public function test_show_retorna_producto_con_stock(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);
        $sku = PteSku::factory()->create(['sku' => 'SKUSHOW0000001']);
        PteStock::factory()->conCantidad(20)->create(['sku' => $sku->sku]);

        $this->getJson("/api/productos/{$sku->sku}")
            ->assertOk()
            ->assertJsonPath('sku', 'SKUSHOW0000001');
    }

    public function test_show_retorna_404_si_no_existe(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);
        $this->getJson('/api/productos/NOEXISTE')->assertStatus(404);
    }

    // ── update ────────────────────────────────────────────────────────────

    public function test_update_modifica_nombre_y_precio(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);
        $sku = PteSku::factory()->create(['sku' => 'SKUUPDATE00001']);
        PteStock::factory()->conCantidad(10)->create(['sku' => $sku->sku]);

        $this->putJson("/api/productos/{$sku->sku}", [
            'nombre'        => 'Nombre Nuevo',
            'precio_venta'  => 99000,
            'cantidad'      => 10, // sin cambio de stock
            'stock_critico' => 5,
        ])->assertOk();

        $this->assertSame('Nombre Nuevo', $sku->fresh()->nombre);
        $this->assertSame(99000, $sku->fresh()->precio_venta);
    }

    public function test_update_registra_movimiento_ajuste_cuando_cambia_stock(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);
        $sku = PteSku::factory()->create(['sku' => 'SKUAJUSTE00001']);
        PteStock::factory()->conCantidad(10)->create(['sku' => $sku->sku]);

        $this->putJson("/api/productos/{$sku->sku}", [
            'nombre'        => $sku->nombre,
            'cantidad'      => 25,
            'stock_critico' => 5,
        ])->assertOk();

        $this->assertDatabaseHas('pte_movimientos', [
            'sku'            => $sku->sku,
            'tipo_movimiento'=> 'AJUSTE_MANUAL',
            'cantidad'       => 15, // diferencia absoluta
            'saldo_anterior' => 10,
            'saldo_nuevo'    => 25,
        ]);
        $this->assertSame(25, PteStock::where('sku', $sku->sku)->value('cantidad'));
    }

    public function test_update_no_registra_movimiento_si_stock_no_cambia(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);
        $sku = PteSku::factory()->create(['sku' => 'SKUSINMOV00001']);
        PteStock::factory()->conCantidad(5)->create(['sku' => $sku->sku]);

        $this->putJson("/api/productos/{$sku->sku}", [
            'nombre'        => 'Nombre B',
            'cantidad'      => 5, // misma cantidad
            'stock_critico' => 5,
        ])->assertOk();

        $this->assertSame(0, PteMovimiento::count());
    }

    // ── destroy ───────────────────────────────────────────────────────────

    public function test_destroy_archiva_producto_sin_eliminar_stock(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);
        $sku = PteSku::factory()->create(['sku' => 'SKUDESTROY0001']);
        PteStock::factory()->create(['sku' => $sku->sku]);

        $this->deleteJson("/api/productos/{$sku->sku}")
            ->assertOk()
            ->assertJsonPath('archived', true);
        $this->assertDatabaseHas('pte_skus', ['sku' => $sku->sku, 'activo' => false]);
        $this->assertDatabaseHas('pte_stocks', ['sku' => $sku->sku]);
    }

    public function test_destroy_archiva_y_preserva_producto_con_movimientos(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);
        $sku = PteSku::factory()->create(['sku' => 'SKUMOVIMIENTO1']);
        PteMovimiento::factory()->paraSku($sku)->create();

        $this->deleteJson("/api/productos/{$sku->sku}")
            ->assertOk()
            ->assertJsonPath('archived', true);

        $this->assertDatabaseHas('pte_skus', ['sku' => $sku->sku, 'activo' => false]);
        $this->assertDatabaseHas('pte_movimientos', ['sku' => $sku->sku]);
    }

    // ── deactivate ────────────────────────────────────────────────────────

    public function test_deactivate_marca_activo_false(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);
        $sku = PteSku::factory()->create(['sku' => 'SKUDESACT0001', 'activo' => true]);

        $this->patchJson("/api/productos/{$sku->sku}/deactivate")->assertOk();
        $this->assertFalse((bool) $sku->fresh()->activo);
    }
}
