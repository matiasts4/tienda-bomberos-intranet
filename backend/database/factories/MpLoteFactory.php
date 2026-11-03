<?php

namespace Database\Factories;

use App\Models\MpMaterial;
use App\Models\MpProveedor;
use Illuminate\Database\Eloquent\Factories\Factory;

class MpLoteFactory extends Factory
{
    protected $model = \App\Models\MpLote::class;

    public function definition(): array
    {
        $cantidad = fake()->randomFloat(2, 5, 200);

        return [
            'material_id'           => MpMaterial::factory(),
            'proveedor_id'          => null,
            'codigo_barra_unico'    => 'R-' . fake()->unique()->numerify('####'),
            'codigo_lote_proveedor' => strtoupper(fake()->bothify('LOTE-??##')),
            'factura_referencia'    => fake()->numerify('FAC-####'),
            'fecha_ingreso'         => now()->subDays(fake()->numberBetween(1, 90)),
            'cantidad_inicial'      => $cantidad,
            'cantidad_actual'       => $cantidad,
            'cantidad_reservada'    => 0,
            'ubicacion'             => 'BODEGA-RECEPCION',
            'estado'                => 'DISPONIBLE',
        ];
    }

    public function conCantidad(float $cantidad): static
    {
        return $this->state(fn () => [
            'cantidad_inicial' => $cantidad,
            'cantidad_actual'  => $cantidad,
        ]);
    }

    public function agotado(): static
    {
        return $this->state(fn () => [
            'cantidad_actual' => 0,
            'estado'          => 'AGOTADO',
        ]);
    }

    public function paraProveedor(MpProveedor $proveedor): static
    {
        return $this->state(fn () => ['proveedor_id' => $proveedor->id]);
    }
}
