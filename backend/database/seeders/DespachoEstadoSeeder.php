<?php

namespace Database\Seeders;

use App\Models\Estado;
use Illuminate\Database\Seeder;

class DespachoEstadoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $states = [
            [
                'nombre' => 'Por despachar',
                'color' => '#6b7280', // Gray
                'scope' => 'despacho',
                'orden' => 1
            ],
            [
                'nombre' => 'Listo para despacho',
                'color' => '#f59e0b', // Amber
                'scope' => 'despacho',
                'orden' => 2
            ],
            [
                'nombre' => 'En tránsito',
                'color' => '#3b82f6', // Blue
                'scope' => 'despacho',
                'orden' => 3
            ],
            [
                'nombre' => 'Entregado',
                'color' => '#10b981', // Emerald
                'scope' => 'despacho',
                'orden' => 4
            ],
            [
                'nombre' => 'Anulado / Devuelto',
                'color' => '#ef4444', // Red
                'scope' => 'despacho',
                'orden' => 5
            ],
        ];

        foreach ($states as $state) {
            Estado::updateOrCreate(
                ['nombre' => $state['nombre'], 'scope' => 'despacho'],
                $state
            );
        }
    }
}
