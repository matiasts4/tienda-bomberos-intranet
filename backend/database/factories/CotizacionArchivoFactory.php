<?php

namespace Database\Factories;

use App\Models\Cotizacion;
use App\Models\CotizacionArchivo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CotizacionArchivo>
 */
class CotizacionArchivoFactory extends Factory
{
    protected $model = CotizacionArchivo::class;

    public function definition(): array
    {
        return [
            'cotizacion_id' => Cotizacion::factory(),
            'tipo' => 'orden_venta',
            'nombre_archivo' => fake()->word() . '.pdf',
            'url' => '/storage/cotizaciones/test.pdf',
        ];
    }

    public function tipo(string $tipo): static
    {
        return $this->state(fn () => ['tipo' => $tipo]);
    }
}
