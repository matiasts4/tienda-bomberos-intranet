<?php
namespace Database\Factories;
use App\Models\PteFamiliaTipo;
use Illuminate\Database\Eloquent\Factories\Factory;
class PteFamiliaTipoFactory extends Factory {
    protected $model = PteFamiliaTipo::class;
    public function definition(): array { return ['nombre' => fake()->unique()->word()]; }
}
