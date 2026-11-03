<?php
namespace Database\Factories;
use App\Models\PteFamiliaFormato;
use Illuminate\Database\Eloquent\Factories\Factory;
class PteFamiliaFormatoFactory extends Factory {
    protected $model = PteFamiliaFormato::class;
    public function definition(): array { return ['nombre' => fake()->unique()->word()]; }
}
