<?php

namespace Tests\Feature\Comercial;

use App\Models\Cotizacion;
use Tests\TestCase;

class CotizacionUpdateTest extends TestCase
{
    public function test_actualiza_campos_basicos(): void
    {
        $this->seedEstadosCotizacion();
        $admin = $this->actingAsAdmin();
        $cot = Cotizacion::factory()->forUser($admin)->create();

        $this->patchJson("/api/cotizaciones/{$cot->id}", [
            'observaciones' => 'Nueva observación',
            'condiciones_pago' => '30 días',
        ])->assertOk();

        $cot->refresh();
        $this->assertSame('Nueva observación', $cot->observaciones);
        $this->assertSame('30 días', $cot->condiciones_pago);
    }

    public function test_recalcula_totales_con_iva_19_porciento(): void
    {
        $this->seedEstadosCotizacion();
        $admin = $this->actingAsAdmin();
        $cot = Cotizacion::factory()->forUser($admin)->create();

        $this->patchJson("/api/cotizaciones/{$cot->id}", [
            'detalles' => [
                [
                    'producto' => 'Casco',
                    'cantidad' => 2,
                    'precio_unitario' => 10000,
                    'total_neto' => 20000,
                ],
                [
                    'producto' => 'Botas',
                    'cantidad' => 1,
                    'precio_unitario' => 50000,
                    'total_neto' => 50000,
                ],
            ],
        ])->assertOk();

        $cot->refresh();
        $this->assertSame(70000, (int) $cot->total_neto);
        $this->assertSame(13300, (int) $cot->iva); // 70000 * 0.19
        $this->assertSame(83300, (int) $cot->total);
    }

    public function test_parsea_montos_con_formato_chileno(): void
    {
        $this->seedEstadosCotizacion();
        $admin = $this->actingAsAdmin();
        $cot = Cotizacion::factory()->forUser($admin)->create();

        $this->patchJson("/api/cotizaciones/{$cot->id}", [
            'detalles' => [
                [
                    'producto' => 'Casco',
                    'cantidad' => 1,
                    'precio_unitario' => '12.597',
                    'total_neto' => '12.597',
                ],
            ],
        ])->assertOk();

        $cot->refresh();
        $this->assertSame(12597, (int) $cot->total_neto);
    }

    public function test_rechaza_numero_duplicado(): void
    {
        $this->seedEstadosCotizacion();
        $admin = $this->actingAsAdmin();
        Cotizacion::factory()->forUser($admin)->create(['numero' => 'CT-0001']);
        $cot = Cotizacion::factory()->forUser($admin)->create(['numero' => 'CT-0002']);

        $this->patchJson("/api/cotizaciones/{$cot->id}", [
            'numero' => 'CT-0001',
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['numero']);
    }

    public function test_permite_mantener_su_propio_numero(): void
    {
        $this->seedEstadosCotizacion();
        $admin = $this->actingAsAdmin();
        $cot = Cotizacion::factory()->forUser($admin)->create(['numero' => 'CT-0001']);

        $this->patchJson("/api/cotizaciones/{$cot->id}", [
            'numero' => 'CT-0001',
            'observaciones' => 'algo',
        ])->assertOk();
    }

    public function test_reemplaza_detalles_completos(): void
    {
        $this->seedEstadosCotizacion();
        $admin = $this->actingAsAdmin();
        $cot = Cotizacion::factory()->forUser($admin)->create();

        // 1ª pasada con 2 items
        $this->patchJson("/api/cotizaciones/{$cot->id}", [
            'detalles' => [
                ['producto' => 'A', 'cantidad' => 1, 'precio_unitario' => 1000, 'total_neto' => 1000],
                ['producto' => 'B', 'cantidad' => 1, 'precio_unitario' => 2000, 'total_neto' => 2000],
            ],
        ])->assertOk();

        $this->assertDatabaseCount('detalle_cotizaciones', 2);

        // 2ª pasada deja solo 1
        $this->patchJson("/api/cotizaciones/{$cot->id}", [
            'detalles' => [
                ['producto' => 'C', 'cantidad' => 1, 'precio_unitario' => 5000, 'total_neto' => 5000],
            ],
        ])->assertOk();

        $this->assertDatabaseCount('detalle_cotizaciones', 1);
        $this->assertDatabaseHas('detalle_cotizaciones', ['producto' => 'C']);
    }

    public function test_rechaza_detalle_sin_sku_y_sin_producto(): void
    {
        $this->seedEstadosCotizacion();
        $admin = $this->actingAsAdmin();
        $cot = Cotizacion::factory()->forUser($admin)->create();

        $this->patchJson("/api/cotizaciones/{$cot->id}", [
            'detalles' => [
                ['cantidad' => 1, 'precio_unitario' => 1000],
            ],
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['detalles.0.sku', 'detalles.0.producto']);
    }
}
