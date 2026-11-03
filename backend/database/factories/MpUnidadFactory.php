<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class MpUnidadFactory extends Factory
{
    protected $model = \App\Models\MpUnidad::class;

    public function definition(): array
    {
        $opts = [
            ['nombre' => 'Metros', 'abreviacion' => 'Mts'],
            ['nombre' => 'Kilos', 'abreviacion' => 'Kg'],
            ['nombre' => 'Unidades', 'abreviacion' => 'Ud'],
        ];

        return fake()->randomElement($opts);
    }
}
