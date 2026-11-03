<?php

namespace Database\Factories;

use App\Models\ComercialSkuGenerico;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ComercialSkuGenerico>
 */
class ComercialSkuGenericoFactory extends Factory
{
    protected $model = ComercialSkuGenerico::class;

    public function definition(): array
    {
        return [
            'sku' => Str::upper(Str::random(10)),
            'nombre' => fake()->words(2, true),
        ];
    }
}
