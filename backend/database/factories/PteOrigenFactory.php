<?php
namespace Database\Factories;
use App\Models\PteOrigen;
use Illuminate\Database\Eloquent\Factories\Factory;
class PteOrigenFactory extends Factory {
    protected $model = PteOrigen::class;
    public function definition(): array { return ['nombre' => fake()->unique()->word()]; }
}
