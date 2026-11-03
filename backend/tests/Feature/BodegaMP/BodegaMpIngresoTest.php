<?php

namespace Tests\Feature\BodegaMP;

use App\Models\MpLote;
use App\Models\MpMaterial;
use App\Models\MpMovimiento;
use App\Models\MpProveedor;
use App\Models\MpTipo;
use Tests\TestCase;

class BodegaMpIngresoTest extends TestCase
{
    private function payload(MpProveedor $proveedor, MpMaterial $material, array $itemOverrides = []): array
    {
        return [
            'proveedor_id'   => $proveedor->id,
            'factura'        => 'FAC-001',
            'fecha_documento'=> now()->toDateString(),
            'items'          => [
                array_merge([
                    'material_id'    => $material->id,
                    'cantidad'       => 50,
                    'lote_proveedor' => 'LOTE-A1',
                ], $itemOverrides),
            ],
        ];
    }

    // ── validación ────────────────────────────────────────────────────────

    public function test_registrar_ingreso_valida_campos_requeridos(): void
    {
        $this->postJson('/api/bodega/ingreso', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['proveedor_id', 'factura', 'fecha_documento', 'items']);
    }

    // ── crear lote y movimiento ────────────────────────────────────────────

    public function test_registrar_ingreso_crea_lote_y_movimiento(): void
    {
        $proveedor = MpProveedor::factory()->create();
        $material  = MpMaterial::factory()->create();

        $this->postJson('/api/bodega/ingreso', $this->payload($proveedor, $material, ['cantidad' => 30]))
            ->assertOk()
            ->assertJsonPath('message', 'Ingreso registrado correctamente')
            ->assertJsonPath('total_bultos', 1);

        $lote = MpLote::where('material_id', $material->id)->first();
        $this->assertNotNull($lote);
        $this->assertSame(30.0, (float) $lote->cantidad_actual);
        $this->assertSame('LOTE-A1', $lote->codigo_lote_proveedor);

        $this->assertDatabaseHas('mp_movimientos', [
            'lote_id'         => $lote->id,
            'tipo_movimiento' => 'INGRESO_COMPRA',
        ]);
    }

    public function test_registrar_ingreso_multiples_items(): void
    {
        $proveedor = MpProveedor::factory()->create();
        $mat1 = MpMaterial::factory()->create();
        $mat2 = MpMaterial::factory()->create();

        $this->postJson('/api/bodega/ingreso', [
            'proveedor_id'    => $proveedor->id,
            'factura'         => 'FAC-002',
            'fecha_documento' => now()->toDateString(),
            'items'           => [
                ['material_id' => $mat1->id, 'cantidad' => 10, 'lote_proveedor' => 'L1'],
                ['material_id' => $mat2->id, 'cantidad' => 20, 'lote_proveedor' => 'L2'],
            ],
        ])->assertOk()->assertJsonPath('total_bultos', 2);

        $this->assertSame(2, MpLote::count());
        $this->assertSame(2, MpMovimiento::count());
    }

    // ── lote genérico para insumos ─────────────────────────────────────────

    public function test_registrar_ingreso_insumo_usa_lote_generico(): void
    {
        // El controller compara tipo_id == 2 (hardcodeado).
        // Crear Tela primero (id=1) e Insumo segundo (id=2) para que el check sea correcto.
        MpTipo::factory()->create(['nombre' => 'Tela']);
        $tipoInsumo = MpTipo::factory()->create(['nombre' => 'Insumo']); // id=2
        $proveedor  = MpProveedor::factory()->create();
        $material   = MpMaterial::factory()->create(['tipo_id' => $tipoInsumo->id]);

        // lote_proveedor vacío → controller debe generar 'GENERICO'
        $this->postJson('/api/bodega/ingreso', [
            'proveedor_id'    => $proveedor->id,
            'factura'         => 'FAC-003',
            'fecha_documento' => now()->toDateString(),
            'items'           => [
                ['material_id' => $material->id, 'cantidad' => 5, 'lote_proveedor' => ''],
            ],
        ])->assertOk();

        $lote = MpLote::where('material_id', $material->id)->first();
        $this->assertSame('GENERICO', $lote->codigo_lote_proveedor);
    }

    // ── auto-sync relación proveedor-material ─────────────────────────────

    public function test_registrar_ingreso_sincroniza_relacion_proveedor_material(): void
    {
        $proveedor = MpProveedor::factory()->create();
        $material  = MpMaterial::factory()->create();

        // Verificar que no existe relación previa
        $this->assertDatabaseMissing('mp_proveedor_productos', [
            'proveedor_id' => $proveedor->id,
            'material_id'  => $material->id,
        ]);

        $this->postJson('/api/bodega/ingreso', $this->payload($proveedor, $material))->assertOk();

        // Ahora debe existir
        $this->assertDatabaseHas('mp_proveedor_productos', [
            'proveedor_id' => $proveedor->id,
            'material_id'  => $material->id,
        ]);
    }
}
