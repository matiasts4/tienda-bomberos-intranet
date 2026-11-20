<?php

namespace Database\Factories;

use App\Models\Cotizacion;
use App\Models\OrdenCorte;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrdenCorteFactory extends Factory
{
    protected $model = OrdenCorte::class;

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

    public function forCotizacion(Cotizacion $cotizacion): static
    {
        return $this->state(fn () => [
            'cotizacion_id' => $cotizacion->id,
            'cuerpo_id' => $cotizacion->cuerpo_id ?? $cotizacion->compania?->cuerpo_id,
            'compania_id' => $cotizacion->compania_id,
            'client' => $cotizacion->nombre_cliente,
            'fecha_compromiso' => $cotizacion->fecha_entrega,
        ]);
    }

    public function inStage(string $stage): static
    {
        return $this->state(fn () => [
            'status' => $stage,
            'progress' => OrdenCorte::STAGE_PROGRESS[$stage] ?? 0,
        ]);
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
