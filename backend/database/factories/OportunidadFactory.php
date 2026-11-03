<?php

namespace Database\Factories;

use App\Models\Cliente;
use App\Models\Estado;
use App\Models\Oportunidad;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Oportunidad>
 */
class OportunidadFactory extends Factory
{
    protected $model = Oportunidad::class;

    public function definition(): array
    {
        return [
            'fecha_ingreso' => now()->toDateString(),
            'canal' => 'web',
            'cliente_id' => Cliente::factory(),
            'user_id' => User::factory(),
            'estado_id' => Estado::factory()->oportunidad('Nueva'),
        ];
    }

    public function withEstado(Estado $estado): static
    {
        return $this->state(fn () => ['estado_id' => $estado->id]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn () => ['user_id' => $user->id]);
    }

    public function forCliente(?Cliente $cliente): static
    {
        return $this->state(fn () => ['cliente_id' => $cliente?->id]);
    }
}
