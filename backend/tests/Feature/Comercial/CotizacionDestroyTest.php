<?php

namespace Tests\Feature\Comercial;

use App\Models\Cotizacion;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CotizacionDestroyTest extends TestCase
{
    public function test_elimina_cotizacion_y_sus_detalles(): void
    {
        $this->seedEstadosCotizacion();
        $admin = $this->actingAsAdmin();
        $cot = Cotizacion::factory()->forUser($admin)->create();

        DB::table('detalle_cotizaciones')->insert([
            ['cotizacion_id' => $cot->id, 'sku' => null, 'producto' => 'X', 'cantidad' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['cotizacion_id' => $cot->id, 'sku' => null, 'producto' => 'Y', 'cantidad' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->deleteJson("/api/cotizaciones/{$cot->id}")
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseMissing('cotizaciones', ['id' => $cot->id]);
        $this->assertDatabaseCount('detalle_cotizaciones', 0);
    }
}
