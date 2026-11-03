<?php

namespace Tests\Feature\BodegaMP;

use App\Models\MpLote;
use App\Models\MpMaterial;
use App\Models\MpMovimiento;
use App\Models\MpTipo;
use App\Models\MpUnidad;
use Tests\TestCase;

class BodegaMpMaterialTest extends TestCase
{
    // ── helpers ────────────────────────────────────────────────────────────

    private function makeMaterial(array $overrides = []): MpMaterial
    {
        return MpMaterial::factory()->create($overrides);
    }

    // ── index ──────────────────────────────────────────────────────────────

    public function test_listar_materiales_retorna_datos_transformados(): void
    {
        $tipo   = MpTipo::factory()->create(['nombre' => 'Tela']);
        $unidad = MpUnidad::factory()->create(['abreviacion' => 'Mts']);
        $mat    = MpMaterial::factory()->create([
            'nombre_base'    => 'Techni-Shell',
            'codigo_interno' => 'MAT001',
            'tipo_id'        => $tipo->id,
            'unidad_id'      => $unidad->id,
            'stock_minimo'   => 5,
        ]);
        MpLote::factory()->conCantidad(20)->create([
            'material_id' => $mat->id,
            'estado'      => 'DISPONIBLE',
        ]);

        $response = $this->getJson('/api/bodega/materiales')->assertOk();
        $items    = $response->json('transformed_data') ?? $response->json('data');

        $this->assertNotEmpty($items);
        $item = collect($items)->firstWhere('sku', 'MAT001');
        $this->assertNotNull($item);
        $this->assertSame('Techni-Shell', $item['nombre']);
        $this->assertSame('Tela', $item['tipo']);
        $this->assertSame(20.0, (float) $item['stock_total']);
        $this->assertFalse($item['alerta_stock']); // 20 > stock_minimo=5
    }

    public function test_listar_materiales_alerta_cuando_stock_bajo(): void
    {
        $mat = MpMaterial::factory()->create(['stock_minimo' => 50]);
        MpLote::factory()->conCantidad(5)->create([
            'material_id' => $mat->id,
            'estado'      => 'DISPONIBLE',
        ]);

        $response = $this->getJson('/api/bodega/materiales')->assertOk();
        $items    = $response->json('transformed_data') ?? $response->json('data');
        $item     = collect($items)->firstWhere('id', $mat->id);

        $this->assertTrue($item['alerta_stock']); // 5 <= 50
    }

    public function test_listar_materiales_filtra_por_tipo(): void
    {
        $tela   = MpTipo::factory()->create(['nombre' => 'Tela']);
        $insumo = MpTipo::factory()->create(['nombre' => 'Insumo']);
        MpMaterial::factory()->create(['tipo_id' => $tela->id]);
        MpMaterial::factory()->create(['tipo_id' => $insumo->id]);

        $response = $this->getJson('/api/bodega/materiales?tipo=Tela')->assertOk();
        $items    = $response->json('transformed_data') ?? $response->json('data');

        foreach ($items as $item) {
            $this->assertSame('Tela', $item['tipo']);
        }
        $this->assertCount(1, $items);
    }

    public function test_listar_materiales_busca_por_nombre(): void
    {
        MpMaterial::factory()->create(['nombre_base' => 'Algodón Crudo']);
        MpMaterial::factory()->create(['nombre_base' => 'Techni-Shell Azul']);

        $response = $this->getJson('/api/bodega/materiales?search=Techni')->assertOk();
        $items    = $response->json('transformed_data') ?? $response->json('data');

        $this->assertCount(1, $items);
        $this->assertStringContainsString('Techni', $items[0]['nombre']);
    }

    // ── store material ─────────────────────────────────────────────────────

    public function test_crear_material_valida_campos_requeridos(): void
    {
        $this->postJson('/api/bodega/crear-material', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nombre_base', 'tipo_id', 'unidad_id']);
    }

    public function test_crear_material_valida_codigo_unico(): void
    {
        $tipo   = MpTipo::factory()->create();
        $unidad = MpUnidad::factory()->create();
        MpMaterial::factory()->create(['codigo_interno' => 'DUP001', 'tipo_id' => $tipo->id, 'unidad_id' => $unidad->id]);

        $this->postJson('/api/bodega/crear-material', [
            'nombre_base'    => 'Tela Duplicada',
            'codigo_interno' => 'DUP001',
            'tipo_id'        => $tipo->id,
            'unidad_id'      => $unidad->id,
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['codigo_interno']);
    }

    public function test_crear_material_exitoso(): void
    {
        $tipo   = MpTipo::factory()->create();
        $unidad = MpUnidad::factory()->create();

        $this->postJson('/api/bodega/crear-material', [
            'nombre_base'    => 'Tela Nueva',
            'codigo_interno' => 'TELA001',
            'tipo_id'        => $tipo->id,
            'unidad_id'      => $unidad->id,
            'stock_minimo'   => 20,
        ])->assertOk()
          ->assertJsonPath('message', 'Material creado');

        $this->assertDatabaseHas('mp_materiales', [
            'nombre_base'    => 'Tela Nueva',
            'codigo_interno' => 'TELA001',
        ]);
    }

    // ── update material ────────────────────────────────────────────────────

    public function test_actualizar_stock_minimo(): void
    {
        $mat = MpMaterial::factory()->create(['stock_minimo' => 10]);

        $this->putJson("/api/bodega/material/{$mat->id}", ['stock_minimo' => 50])
            ->assertOk()
            ->assertJsonPath('message', 'Material actualizado');

        $this->assertSame(50, $mat->fresh()->stock_minimo);
    }

    // ── destroy material ───────────────────────────────────────────────────

    public function test_eliminar_material_elimina_lotes_y_movimientos(): void
    {
        $mat  = MpMaterial::factory()->create();
        $lote = MpLote::factory()->conCantidad(10)->create(['material_id' => $mat->id]);

        $this->deleteJson("/api/bodega/material/{$mat->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Material eliminado correctamente');

        $this->assertDatabaseMissing('mp_materiales', ['id' => $mat->id]);
        $this->assertDatabaseMissing('mp_lotes', ['id' => $lote->id]);
    }
}
