<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class MpColorFactory extends Factory
{
    protected $model = \App\Models\MpColor::class;

    public function definition(): array
    {
        return [
            'nombre'     => fake()->unique()->colorName(),
            'codigo_hex' => fake()->hexColor(),
        ];
    }
}
