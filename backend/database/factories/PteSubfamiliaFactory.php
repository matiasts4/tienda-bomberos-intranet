<?php
namespace Database\Factories;
use App\Models\PteSubfamilia;
use Illuminate\Database\Eloquent\Factories\Factory;
class PteSubfamiliaFactory extends Factory {
    protected $model = PteSubfamilia::class;
    public function definition(): array { return ['nombre' => fake()->unique()->word(), 'familia_id' => null]; }
}
