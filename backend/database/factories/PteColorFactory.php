<?php
namespace Database\Factories;
use App\Models\PteColor;
use Illuminate\Database\Eloquent\Factories\Factory;
class PteColorFactory extends Factory {
    protected $model = PteColor::class;
    public function definition(): array { return ['nombre' => fake()->unique()->safeColorName(), 'is_activo' => true]; }
}
