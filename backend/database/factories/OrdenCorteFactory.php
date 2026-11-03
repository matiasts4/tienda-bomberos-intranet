<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class OrdenCorteFactory extends Factory
{
    protected $model = \App\Models\OrdenCorte::class;

    public function definition(): array
    {
        static $counter = 999;
        $counter++;

        return [
            'code'             => (string) $counter,
            'client'           => fake()->company(),
            'selected_product' => fake()->randomElement(['Parka Bombero', 'Casco NFPA', 'Traje Hazmat']),
            'status'           => 'order-received',
            'notes'            => fake()->sentence(),
            'estimated_days'   => fake()->numberBetween(5, 30),
            'progress'         => 0,
        ];
    }

    public function enCorte(): static
    {
        return $this->state(fn () => ['status' => 'in-cutting', 'progress' => 30]);
    }

    public function completada(): static
    {
        return $this->state(fn () => ['status' => 'completed', 'progress' => 100]);
    }
}
