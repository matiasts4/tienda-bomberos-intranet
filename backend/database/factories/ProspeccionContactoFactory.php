<?php

namespace Database\Factories;

use App\Models\Contacto;
use App\Models\ProspeccionTipo;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProspeccionContactoFactory extends Factory
{
    protected $model = \App\Models\ProspeccionContacto::class;

    public function definition(): array
    {
        return [
            'contacto_id'     => Contacto::factory(),
            'user_id'         => User::factory(),
            'tipo_id'         => fn () => ProspeccionTipo::where('slug', 'llamar_luego')->first()?->id
                                        ?? ProspeccionTipo::factory()->create(['slug' => 'llamar_luego'])->id,
            'notas'           => fake()->sentence(),
            'proxima_llamada' => null,
        ];
    }

    public function conTipo(string $slug): static
    {
        return $this->state(fn () => [
            'tipo_id' => ProspeccionTipo::where('slug', $slug)->first()?->id,
        ]);
    }

    public function conProximaLlamada(string $fecha = null): static
    {
        return $this->state(fn () => [
            'proxima_llamada' => $fecha ?? now()->addDays(3)->toDateString(),
        ]);
    }
}
