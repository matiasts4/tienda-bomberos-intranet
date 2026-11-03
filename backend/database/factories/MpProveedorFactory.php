<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class MpProveedorFactory extends Factory
{
    protected $model = \App\Models\MpProveedor::class;

    public function definition(): array
    {
        return [
            'nombre_fantasia'  => fake()->company(),
            'razon_social'     => fake()->company() . ' SpA',
            'rut_empresa'      => fake()->numerify('##.###.###-#'),
            'contacto_nombre'  => fake()->name(),
            'telefono'         => fake()->phoneNumber(),
            'email'            => fake()->companyEmail(),
        ];
    }
}
