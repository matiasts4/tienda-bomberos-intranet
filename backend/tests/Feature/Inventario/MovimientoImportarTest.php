<?php

namespace Tests\Feature\Inventario;

use App\Models\PteSku;
use App\Models\PteStock;
use App\Models\PteMovimiento;
use Tests\TestCase;

class MovimientoImportarTest extends TestCase
{
    private function catalogos(): array
    {
        return [
            'unidad_negocio_id'  => \App\Models\PteUnidadNegocio::factory()->create()->id,
            'origen_id'          => \App\Models\PteOrigen::factory()->create()->id,
            'grp_familia_id'     => \App\Models\PteGrpFamilia::factory()->create()->id,
            'familia_id'         => \App\Models\PteFamilia::factory()->create()->id,
            'subfamilia_id'      => \App\Models\PteSubfamilia::factory()->create()->id,
            'familia_tipo_id'    => \App\Models\PteFamiliaTipo::factory()->create()->id,
            'familia_formato_id' => \App\Models\PteFamiliaFormato::factory()->create()->id,
            'genero_id'          => \App\Models\PteGenero::factory()->create()->id,
            'color_id'           => \App\Models\PteColor::factory()->create()->id,
            'talla_id'           => \App\Models\PteTalla::factory()->create()->id,
        ];
    }

    public function test_importar_requiere_autenticacion(): void
    {
        $this->postJson('/api/movimientos/importar', [])->assertUnauthorized();
    }

    public function test_importar_valida_estructura_del_payload(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);

        $this->postJson('/api/movimientos/importar', [])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['productos']]);
    }

    public function test_importar_crea_sku_y_stock_nuevos(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);
        $cat = $this->catalogos();
        // El SKU debe tener ≥ 14 chars (el controller parsea IDs de la cadena)
        $sku = 'IMPORTNEWSKU001';

        // El controller busca el genérico con sku_base = substr(sku, 0, 11) . '0000'
        $skuBase = substr($sku, 0, 11) . '0000'; // 'IMPORTNEWSK0000'
        \App\Models\PteSkuGenerico::create([
            'sku_base' => $skuBase,
            'nombre'   => 'Polera Importada',
            'activo'   => true,
        ]);

        $this->postJson('/api/movimientos/importar', [
            'productos' => [[
                'sku'      => $sku,
                'cantidad' => 25,
                'nombre'   => 'Polera Importada',
                ...$cat,
            ]],
        ])->assertOk();

        $this->assertDatabaseHas('pte_skus', ['sku' => $sku]);
        $this->assertSame(25, (int) PteStock::where('sku', $sku)->value('cantidad'));
        $this->assertDatabaseHas('pte_movimientos', [
            'sku'            => $sku,
            'tipo_movimiento'=> 'INVENTARIO_INICIAL',
        ]);
    }

    public function test_importar_actualiza_stock_de_sku_existente(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);
        $cat = $this->catalogos();
        $sku = PteSku::factory()->create(['sku' => 'IMPORT000002']);
        PteStock::factory()->conCantidad(5)->create(['sku' => $sku->sku]);

        $this->postJson('/api/movimientos/importar', [
            'productos' => [[
                'sku'      => 'IMPORT000002',
                'cantidad' => 30,
                ...$cat,
            ]],
        ])->assertOk();

        $this->assertSame(30, (int) PteStock::where('sku', 'IMPORT000002')->value('cantidad'));
        $this->assertDatabaseHas('pte_movimientos', [
            'sku'            => 'IMPORT000002',
            'tipo_movimiento'=> 'CAMBIOS_EXCEL',
        ]);
    }

    public function test_importar_comparte_mismo_id_lote_entre_productos(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);
        $cat = $this->catalogos();

        // SKUs de ≥ 14 chars para que el controller los acepte como nuevos
        // Pre-crear genérico necesario (ambos SKUs comparten sku_base)
        \App\Models\PteSkuGenerico::create([
            'sku_base' => substr('LOTE00000000001', 0, 11) . '0000',
            'nombre'   => 'P1',
            'activo'   => true,
        ]);

        $this->postJson('/api/movimientos/importar', [
            'productos' => [
                ['sku' => 'LOTE00000000001', 'cantidad' => 10, 'nombre' => 'P1', ...$cat],
                ['sku' => 'LOTE00000000002', 'cantidad' => 5,  'nombre' => 'P2', ...$cat],
            ],
        ])->assertOk();

        $lotes = PteMovimiento::whereIn('sku', ['LOTE00000000001', 'LOTE00000000002'])
            ->pluck('id_lote')
            ->unique();

        $this->assertCount(1, $lotes, 'Ambos movimientos deben compartir el mismo id_lote');
    }

    public function test_importar_ignora_si_cantidad_igual_a_stock_actual(): void
    {
        $this->actingAsUser(['role' => 'superadmin']);
        $cat = $this->catalogos();
        $sku = PteSku::factory()->create(['sku' => 'IMPORT000003']);
        PteStock::factory()->conCantidad(10)->create(['sku' => $sku->sku]);

        $this->postJson('/api/movimientos/importar', [
            'productos' => [['sku' => 'IMPORT000003', 'cantidad' => 10, ...$cat]],
        ])->assertOk();

        $this->assertSame(0, PteMovimiento::where('sku', 'IMPORT000003')->count());
    }
}
