<?php
namespace Database\Factories;
use App\Models\PteFamilia;
use Illuminate\Database\Eloquent\Factories\Factory;
class PteFamiliaFactory extends Factory {
    protected $model = PteFamilia::class;
    public function definition(): array { return ['nombre' => fake()->unique()->word()]; }
}
