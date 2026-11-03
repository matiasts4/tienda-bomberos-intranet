<?php

namespace Database\Factories;

use App\Models\Cotizacion;
use App\Models\DetalleCotizacion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DetalleCotizacion>
 */
class DetalleCotizacionFactory extends Factory
{
    protected $model = DetalleCotizacion::class;

    public function definition(): array
    {
        return [
            'cotizacion_id' => Cotizacion::factory(),
            'sku' => null,
            'producto' => fake()->word(),
            'cantidad' => fake()->numberBetween(1, 20),
            'precio_unitario' => 10000,
            'total_neto' => 10000,
            'is_personalizable' => true,
            'tipo_personalizacion' => 'embroidery',
            'n_item' => 1,
        ];
    }

    public function forCotizacion(Cotizacion $cot): static
    {
        return $this->state(fn () => ['cotizacion_id' => $cot->id]);
    }
}
