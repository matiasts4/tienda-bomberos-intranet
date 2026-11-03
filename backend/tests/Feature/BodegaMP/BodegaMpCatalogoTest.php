<?php

namespace Tests\Feature\BodegaMP;

use App\Models\MpMaterial;
use App\Models\MpProveedor;
use Tests\TestCase;

class BodegaMpCatalogoTest extends TestCase
{
    // ── formulario-datos ───────────────────────────────────────────────────

    public function test_formulario_datos_retorna_estructura_completa(): void
    {
        $response = $this->getJson('/api/bodega/formulario-datos')->assertOk();

        $response->assertJsonStructure([
            'proveedores',
            'colores',
            'unidades',
            'tipos',
            'materiales',
        ]);
    }

    public function test_formulario_datos_incluye_proveedores_creados(): void
    {
        MpProveedor::factory()->create(['nombre_fantasia' => 'Proveedor Test']);

        $body = $this->getJson('/api/bodega/formulario-datos')->assertOk()->json();

        $nombres = array_column($body['proveedores'], 'nombre_fantasia');
        $this->assertContains('Proveedor Test', $nombres);
    }

    // ── sync-catalogo ──────────────────────────────────────────────────────

    public function test_sync_catalogo_asocia_proveedor_a_material(): void
    {
        $proveedor = MpProveedor::factory()->create();
        $material  = MpMaterial::factory()->create();

        $this->postJson('/api/bodega/sync-catalogo', [
            'material_id'  => $material->id,
            'proveedor_id' => $proveedor->id,
            'sku_proveedor'=> 'SKU-EXT-001',
            'precio'       => 1500,
            'moneda'       => 'CLP',
        ])->assertOk()
          ->assertJsonPath('message', 'Catálogo actualizado correctamente');

        $this->assertDatabaseHas('mp_proveedor_productos', [
            'material_id'   => $material->id,
            'proveedor_id'  => $proveedor->id,
            'sku_proveedor' => 'SKU-EXT-001',
        ]);
    }

    public function test_sync_catalogo_valida_campos_requeridos(): void
    {
        $this->postJson('/api/bodega/sync-catalogo', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['material_id', 'proveedor_id', 'precio']);
    }

    // ── catalogo-completo ──────────────────────────────────────────────────

    public function test_catalogo_completo_retorna_relaciones(): void
    {
        $proveedor = MpProveedor::factory()->create(['nombre_fantasia' => 'Proveedor Alfa']);
        $material  = MpMaterial::factory()->create(['nombre_base' => 'Tela Beta']);

        // Crear relación directa
        \Illuminate\Support\Facades\DB::table('mp_proveedor_productos')->insert([
            'proveedor_id'    => $proveedor->id,
            'material_id'     => $material->id,
            'sku_proveedor'   => 'PROV-SKU-001',
            'precio_referencia' => 2500,
            'moneda'          => 'CLP',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $body = $this->getJson('/api/bodega/catalogo-completo')->assertOk()->json();

        $this->assertNotEmpty($body);
        $entry = collect($body)->firstWhere('material_nombre', 'Tela Beta');
        $this->assertNotNull($entry);
        $this->assertSame('Proveedor Alfa', $entry['proveedor_nombre']);
        $this->assertSame('PROV-SKU-001', $entry['sku_proveedor']);
    }
}
