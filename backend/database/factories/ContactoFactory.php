<?php

namespace Database\Factories;

use App\Models\Cliente;
use App\Models\Compania;
use App\Models\Contacto;
use App\Models\Cuerpo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contacto>
 */
class ContactoFactory extends Factory
{
    protected $model = Contacto::class;

    public function definition(): array
    {
        return [
            'cliente_id' => null,
            'nombre' => fake()->name(),
            'telefono' => fake()->phoneNumber(),
            'email' => fake()->safeEmail(),
            'cargo' => 'Comandante',
        ];
    }

    public function sinCliente(): static
    {
        return $this->state(fn () => ['cliente_id' => null]);
    }

    public function conCliente(): static
    {
        return $this->state(fn () => ['cliente_id' => Cliente::factory()]);
    }

    public function conCuerpo(Cuerpo $cuerpo): static
    {
        return $this->afterCreating(function (Contacto $contacto) use ($cuerpo) {
            $contacto->cuerpos()->attach($cuerpo->id);
        });
    }

    public function conCompania(Compania $compania): static
    {
        return $this->afterCreating(function (Contacto $contacto) use ($compania) {
            $contacto->companias()->attach($compania->id);
        });
    }
}
