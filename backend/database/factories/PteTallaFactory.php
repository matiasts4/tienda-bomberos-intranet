<?php
namespace Database\Factories;
use App\Models\PteTalla;
use Illuminate\Database\Eloquent\Factories\Factory;
class PteTallaFactory extends Factory {
    protected $model = PteTalla::class;
    public function definition(): array { return ['nombre' => fake()->unique()->randomElement(['XS','S','M','L','XL','XXL']), 'orden' => fake()->numberBetween(1,10), 'is_activo' => true]; }
}
