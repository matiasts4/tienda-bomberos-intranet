<?php
namespace Database\Factories;
use App\Models\PteGenero;
use Illuminate\Database\Eloquent\Factories\Factory;
class PteGeneroFactory extends Factory {
    protected $model = PteGenero::class;
    public function definition(): array { return ['nombre' => fake()->unique()->randomElement(['M','F','U']), 'is_activo' => true]; }
}
