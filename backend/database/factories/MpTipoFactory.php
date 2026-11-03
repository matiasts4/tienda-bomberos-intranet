<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class MpTipoFactory extends Factory
{
    protected $model = \App\Models\MpTipo::class;

    public function definition(): array
    {
        return [
            'nombre' => fake()->randomElement(['Tela', 'Insumo', 'Hilo', 'Etiqueta']),
        ];
    }

    public function tela(): static
    {
        return $this->state(fn () => ['nombre' => 'Tela']);
    }

    public function insumo(): static
    {
        return $this->state(fn () => ['nombre' => 'Insumo']);
    }
}
