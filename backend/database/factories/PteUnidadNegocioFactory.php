<?php
namespace Database\Factories;
use App\Models\PteUnidadNegocio;
use Illuminate\Database\Eloquent\Factories\Factory;
class PteUnidadNegocioFactory extends Factory {
    protected $model = PteUnidadNegocio::class;
    public function definition(): array { return ['nombre' => fake()->unique()->word()]; }
}
