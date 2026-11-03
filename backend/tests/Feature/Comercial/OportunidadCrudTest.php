<?php

namespace Tests\Feature\Comercial;

use App\Models\Oportunidad;
use Tests\TestCase;

class OportunidadCrudTest extends TestCase
{
    public function test_show_devuelve_oportunidad_con_relaciones(): void
    {
        $this->seedEstadosOportunidad();
        $admin = $this->actingAsAdmin();
        $op = Oportunidad::factory()->create(['user_id' => $admin->id]);

        $this->getJson("/api/oportunidades/{$op->id}")
            ->assertOk()
            ->assertJsonPath('id', $op->id)
            ->assertJsonStructure(['id', 'cliente', 'estado']);
    }

    public function test_update_modifica_campos(): void
    {
        // dev-diego: 'canal' fue removido del validate de update.
        // Validamos sólo los campos que sí pasan por el update actual.
        $this->seedEstadosOportunidad();
        $admin = $this->actingAsAdmin();
        $op = Oportunidad::factory()->create(['user_id' => $admin->id]);

        $this->patchJson("/api/oportunidades/{$op->id}", [
            'referido_por' => 'Pedro',
        ])->assertOk();

        $op->refresh();
        $this->assertSame('Pedro', $op->referido_por);
    }

    public function test_destroy_elimina(): void
    {
        $this->seedEstadosOportunidad();
        $admin = $this->actingAsAdmin();
        $op = Oportunidad::factory()->create(['user_id' => $admin->id]);

        $this->deleteJson("/api/oportunidades/{$op->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('oportunidades', ['id' => $op->id]);
    }
}
