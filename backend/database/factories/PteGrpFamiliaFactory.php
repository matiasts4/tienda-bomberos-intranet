<?php
namespace Database\Factories;
use App\Models\PteGrpFamilia;
use Illuminate\Database\Eloquent\Factories\Factory;
class PteGrpFamiliaFactory extends Factory {
    protected $model = PteGrpFamilia::class;
    public function definition(): array { return ['nombre' => fake()->unique()->word()]; }
}
