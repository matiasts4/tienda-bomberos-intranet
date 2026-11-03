<?php

namespace Database\Factories;

use App\Models\Cliente;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Cliente>
 */
class ClienteFactory extends Factory
{
    protected $model = Cliente::class;

    public function definition(): array
    {
        return [
            // Cliente.id es string max 15 (no auto-incremental)
            'id' => Str::upper(Str::random(10)),
            'nombre_empresa' => fake()->company(),
            'rut_empresa' => fake()->numerify('########-#'),
            'direccion' => fake()->streetAddress(),
            'giro' => 'Bomberos',
            'telefono' => fake()->phoneNumber(),
            'correo' => fake()->companyEmail(),
            'fecha_ingreso' => now()->toDateString(),
        ];
    }

    public function child(string $parentId): static
    {
        return $this->state(fn () => ['parent_id' => $parentId]);
    }
}
