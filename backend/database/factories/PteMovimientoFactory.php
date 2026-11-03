<?php

namespace Database\Factories;

use App\Models\PteMovimiento;
use App\Models\PteSku;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PteMovimiento>
 */
class PteMovimientoFactory extends Factory
{
    protected $model = PteMovimiento::class;

    public function definition(): array
    {
        return [
            'sku'            => PteSku::factory(),
            'tipo_movimiento'=> 'INGRESO_BODEGA',
            'cantidad'       => fake()->numberBetween(1, 50),
            'saldo_anterior' => 0,
            'saldo_nuevo'    => fake()->numberBetween(1, 50),
            'fecha_hora'     => now(),
            'id_lote'        => (string) Str::uuid(),
        ];
    }

    public function ingreso(): static
    {
        return $this->state(fn () => ['tipo_movimiento' => 'INGRESO_BODEGA']);
    }

    public function salida(): static
    {
        return $this->state(fn () => ['tipo_movimiento' => 'SALIDA_VENTA']);
    }

    public function paraSku(PteSku $sku): static
    {
        return $this->state(fn () => ['sku' => $sku->sku]);
    }
}
