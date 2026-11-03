<?php

namespace Tests\Feature\BodegaMP;

use App\Models\MpLote;
use App\Models\MpMaterial;
use App\Models\MpProveedor;
use Tests\TestCase;

class BodegaMpProveedorTest extends TestCase
{
    // ── index ──────────────────────────────────────────────────────────────

    public function test_listar_proveedores_retorna_lista(): void
    {
        MpProveedor::factory()->count(3)->create();

        $this->getJson('/api/bodega/proveedores')
            ->assertOk()
            ->assertJsonCount(3);
    }

    // ── store ──────────────────────────────────────────────────────────────

    public function test_crear_proveedor_valida_nombre_requerido(): void
    {
        $this->postJson('/api/bodega/crear-proveedor', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nombre_fantasia']);
    }

    public function test_crear_proveedor_exitoso(): void
    {
        $this->postJson('/api/bodega/crear-proveedor', [
            'nombre_fantasia'  => 'Textiles Chile',
            'rut'              => '12.345.678-9',
            'contacto_nombre'  => 'Juan Pérez',
            'contacto_email'   => 'juan@textiles.cl',
            'contacto_telefono'=> '+56912345678',
        ])->assertOk()
          ->assertJsonPath('message', 'Proveedor creado');

        $this->assertDatabaseHas('mp_proveedores', ['nombre_fantasia' => 'Textiles Chile']);
    }

    // ── update ─────────────────────────────────────────────────────────────

    public function test_actualizar_proveedor(): void
    {
        $proveedor = MpProveedor::factory()->create(['nombre_fantasia' => 'Viejo Nombre']);

        $this->putJson("/api/bodega/proveedor/{$proveedor->id}", [
            'nombre_fantasia' => 'Nuevo Nombre',
        ])->assertOk()
          ->assertJsonPath('message', 'Proveedor actualizado');

        $this->assertSame('Nuevo Nombre', $proveedor->fresh()->nombre_fantasia);
    }

    // ── destroy ────────────────────────────────────────────────────────────

    public function test_eliminar_proveedor_bloquea_si_tiene_lotes(): void
    {
        $proveedor = MpProveedor::factory()->create();
        $material  = MpMaterial::factory()->create();
        MpLote::factory()->create([
            'material_id'  => $material->id,
            'proveedor_id' => $proveedor->id,
        ]);

        $this->deleteJson("/api/bodega/proveedor/{$proveedor->id}")
            ->assertStatus(422)
            ->assertJsonPath('message', 'No se puede eliminar el proveedor porque tiene lotes de mercadería asociados.');
    }

    public function test_eliminar_proveedor_exitoso(): void
    {
        $proveedor = MpProveedor::factory()->create();

        $this->deleteJson("/api/bodega/proveedor/{$proveedor->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Proveedor eliminado correctamente');

        $this->assertDatabaseMissing('mp_proveedores', ['id' => $proveedor->id]);
    }
}
