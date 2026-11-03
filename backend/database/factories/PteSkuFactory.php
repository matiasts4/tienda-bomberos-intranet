<?php

namespace Database\Factories;

use App\Models\PteColor;
use App\Models\PteTalla;
use App\Models\PteSku;
use App\Models\PteSkuGenerico;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PteSku>
 */
class PteSkuFactory extends Factory
{
    protected $model = PteSku::class;

    public function definition(): array
    {
        return [
            'sku'               => Str::upper(Str::random(15)),
            'activo'            => true,
            'nombre'            => fake()->words(2, true),
            'descripcion'       => fake()->sentence(),
            'precio_venta'      => fake()->numberBetween(5000, 100000),
            'stock_critico'     => 5,
            'color_id'          => PteColor::factory(),
            'talla_id'          => PteTalla::factory(),
        ];
    }

    public function inactivo(): static
    {
        return $this->state(fn () => ['activo' => false]);
    }
}
