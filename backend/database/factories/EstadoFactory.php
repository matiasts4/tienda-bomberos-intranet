<?php

namespace Database\Factories;

use App\Models\Estado;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Estado>
 */
class EstadoFactory extends Factory
{
    protected $model = Estado::class;

    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->words(2, true),
            'color' => fake()->hexColor(),
            'scope' => 'cotizacion',
            'orden' => 1,
        ];
    }

    public function cotizacion(string $nombre): static
    {
        return $this->state(fn () => ['nombre' => $nombre, 'scope' => 'cotizacion']);
    }

    public function oportunidad(string $nombre): static
    {
        return $this->state(fn () => ['nombre' => $nombre, 'scope' => 'oportunidad']);
    }
}
