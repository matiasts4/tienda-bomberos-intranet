<?php

namespace Database\Factories;

use App\Models\PteSku;
use App\Models\PteStock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PteStock>
 */
class PteStockFactory extends Factory
{
    protected $model = PteStock::class;

    public function definition(): array
    {
        return [
            'sku'            => PteSku::factory(),
            'cantidad'       => fake()->numberBetween(0, 100),
            'reserved_stock' => 0,
            'stock_critico'  => 5,
        ];
    }

    public function conCantidad(int $cantidad): static
    {
        return $this->state(fn () => ['cantidad' => $cantidad]);
    }

    public function critico(): static
    {
        return $this->state(fn () => ['cantidad' => 2, 'stock_critico' => 5]);
    }

    public function agotado(): static
    {
        return $this->state(fn () => ['cantidad' => 0]);
    }
}
