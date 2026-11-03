<?php

namespace Database\Factories;

use App\Models\MpTipo;
use App\Models\MpUnidad;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MpMaterialFactory extends Factory
{
    protected $model = \App\Models\MpMaterial::class;

    public function definition(): array
    {
        return [
            'nombre_base'     => fake()->words(2, true),
            'codigo_interno'  => Str::upper(Str::random(8)),
            'tipo_id'         => MpTipo::factory(),
            'unidad_id'       => MpUnidad::factory(),
            'color_id'        => null,
            'ancho_id'        => null,
            'stock_minimo'    => 10,
            'activo'          => true,
        ];
    }

    /** Material de tipo Tela (tipo_id → tipo con nombre 'Tela', id normalmente 1) */
    public function tela(): static
    {
        return $this->state(fn () => ['tipo_id' => MpTipo::factory()->tela()]);
    }

    /** Material de tipo Insumo (tipo_id 2 en producción) */
    public function insumo(): static
    {
        return $this->state(fn () => ['tipo_id' => MpTipo::factory()->insumo()]);
    }
}
